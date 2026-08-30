<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Payment;

use DateTimeImmutable;
use DomainException;
use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\FirstSats\Payment\Bolt11Inspector;

final class Bolt11InspectorTest extends TestCase
{
    private const CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
    private const INVOICE = 'lnbc20u1pvjluezhp58yjmdan79s6qqdhdzgynm4zwqd5d7xmw5fk98klysy043l2ahrqspp5qqqsyqcyq5rqwzqfqqqsyqcyq5rqwzqfqqqsyqcyq5rqwzqfqypqfppqw508d6qejxtdg4y5r3zarvary0c5xw7kxqrrsssp5m6kmam774klwlh4dhmhaatd7al02m0h0m6kmam774klwlh4dhmhs9qypqqqcqpf3cwux5979a8j28d4ydwahx00saa68wq3az7v9jdgzkghtxnkf3z5t7q5suyq2dl9tqwsap8j0wptc82cpyvey9gf6zyylzrm60qtcqsq7egtsq';

    public function testReadsMainnetAmountAndExpiryFromOfficialBoltExample(): void
    {
        $result=(new Bolt11Inspector())->inspect(self::INVOICE,2000,new DateTimeImmutable('@1496314700'));
        self::assertSame(2000,$result->satsAmount);
        self::assertSame(1496318258,$result->expiresAt->getTimestamp());
    }

    public function testRejectsWrongAmountAndExpiredInvoice(): void
    {
        $this->expectException(DomainException::class);
        (new Bolt11Inspector())->inspect(self::INVOICE,5000,new DateTimeImmutable('@1496314700'));
    }

    public function testRejectsInvalidCompactSignatureEvenWithValidBech32Checksum(): void
    {
        $separator = strrpos(self::INVOICE, '1');
        self::assertNotFalse($separator);
        $hrp = substr(self::INVOICE, 0, $separator);
        $values = array_map(fn(string $char): int => (int) strpos(self::CHARSET, $char), str_split(substr(self::INVOICE, $separator + 1)));
        $data = array_slice($values, 0, -6);

        // The last byte is the recovery id. Value 4 is outside the permitted range 0..3.
        $data[count($data) - 2] = 0;
        $data[count($data) - 1] = 4;
        $tampered = $hrp . '1' . $this->encodeWithChecksum($hrp, $data);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('signature');
        (new Bolt11Inspector())->inspect($tampered, 2000, new DateTimeImmutable('@1496314700'));
    }

    /** @param list<int> $data */
    private function encodeWithChecksum(string $hrp, array $data): string
    {
        $values = array_merge($data, [0, 0, 0, 0, 0, 0]);
        $polymod = $this->polymod(array_merge($this->expandHrp($hrp), $values)) ^ 1;
        $checksum = [];
        for ($i = 0; $i < 6; $i++) $checksum[] = ($polymod >> (5 * (5 - $i))) & 31;
        return implode('', array_map(fn(int $value): string => self::CHARSET[$value], array_merge($data, $checksum)));
    }

    /** @return list<int> */
    private function expandHrp(string $hrp): array
    {
        $values = [];
        foreach (str_split($hrp) as $char) $values[] = ord($char) >> 5;
        $values[] = 0;
        foreach (str_split($hrp) as $char) $values[] = ord($char) & 31;
        return $values;
    }

    /** @param list<int> $values */
    private function polymod(array $values): int
    {
        $checksum = 1;
        foreach ($values as $value) {
            $top = $checksum >> 25;
            $checksum = (($checksum & 0x1ffffff) << 5) ^ $value;
            foreach ([0x3b6a57b2,0x26508e6d,0x1ea119fa,0x3d4233dd,0x2a1462b3] as $index => $generator) {
                if (($top >> $index) & 1) $checksum ^= $generator;
            }
        }
        return $checksum;
    }
}
