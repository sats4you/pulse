<?php

declare(strict_types=1);

namespace Sats4you\Pulse\Tests\FirstSats\Payment;

use PHPUnit\Framework\TestCase;
use Sats4you\Pulse\FirstSats\Payment\Bolt11SignatureVerifier;

final class Bolt11SignatureVerifierTest extends TestCase
{
    private const CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';
    private const INVOICE = 'lnbc20u1pvjluezhp58yjmdan79s6qqdhdzgynm4zwqd5d7xmw5fk98klysy043l2ahrqspp5qqqsyqcyq5rqwzqfqqqsyqcyq5rqwzqfqqqsyqcyq5rqwzqfqypqfppqw508d6qejxtdg4y5r3zarvary0c5xw7kxqrrsssp5m6kmam774klwlh4dhmhaatd7al02m0h0m6kmam774klwlh4dhmhs9qypqqqcqpf3cwux5979a8j28d4ydwahx00saa68wq3az7v9jdgzkghtxnkf3z5t7q5suyq2dl9tqwsap8j0wptc82cpyvey9gf6zyylzrm60qtcqsq7egtsq';

    public function testMatchesRecoveredSignerAgainstExplicitPayeeKey(): void
    {
        [$hrp, $unsignedData, $signature] = $this->parts(self::INVOICE);
        $payeeKey = $this->bytesToWords(hex2bin('03e7156ae33b0a208d0744199163177e909e80176e55d97a2f221ede0f934dd9ad'));

        self::assertTrue((new Bolt11SignatureVerifier())->verify($hrp, $unsignedData, $signature, $payeeKey));

        // Change key material while keeping the final padding bit canonical.
        $payeeKey[count($payeeKey) - 2] ^= 1;
        self::assertFalse((new Bolt11SignatureVerifier())->verify($hrp, $unsignedData, $signature, $payeeKey));
    }

    /** @return array{string, list<int>, list<int>} */
    private function parts(string $invoice): array
    {
        $separator = strrpos($invoice, '1');
        self::assertNotFalse($separator);
        $hrp = substr($invoice, 0, $separator);
        $values = array_map(fn(string $char): int => (int) strpos(self::CHARSET, $char), str_split(substr($invoice, $separator + 1)));
        $data = array_slice($values, 0, -6);
        return [$hrp, array_slice($data, 0, -104), array_slice($data, -104)];
    }

    /** @return list<int> */
    private function bytesToWords(string $bytes): array
    {
        $accumulator = 0;
        $bits = 0;
        $words = [];
        foreach (str_split($bytes) as $byte) {
            $accumulator = ($accumulator << 8) | ord($byte);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $words[] = ($accumulator >> $bits) & 31;
            }
            $accumulator &= (1 << $bits) - 1;
        }
        if ($bits > 0) $words[] = ($accumulator << (5 - $bits)) & 31;
        return $words;
    }
}
