<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Payment;

use DateTimeImmutable;
use DomainException;

final class Bolt11Inspector
{
    private const CHARSET = 'qpzry9x8gf2tvdw0s3jn54khce6mua7l';

    public function __construct(private readonly Bolt11SignatureVerifier $signatureVerifier = new Bolt11SignatureVerifier()) {}

    public function inspect(string $input, int $expectedSats, DateTimeImmutable $now): PaymentRequest
    {
        $invoice = trim($input);
        if (str_starts_with(strtolower($invoice), 'lightning:')) $invoice = substr($invoice, 10);
        if ($invoice === '' || strlen($invoice) > 5000 || (strtolower($invoice) !== $invoice && strtoupper($invoice) !== $invoice)) throw new DomainException('format');
        $invoice = strtolower($invoice); $separator = strrpos($invoice, '1');
        if ($separator === false || $separator < 5) throw new DomainException('format');
        $hrp = substr($invoice, 0, $separator); $encoded = substr($invoice, $separator + 1);
        if (!preg_match('/^lnbc([1-9][0-9]*)([munp]?)$/', $hrp, $m)) throw new DomainException('network');
        $values = [];
        foreach (str_split($encoded) as $char) { $v = strpos(self::CHARSET, $char); if ($v === false) throw new DomainException('format'); $values[] = $v; }
        if (count($values) < 117 || $this->polymod(array_merge($this->expandHrp($hrp), $values)) !== 1) throw new DomainException('checksum');
        $data = array_slice($values, 0, -6); $signature = array_slice($data, -104); $unsignedData = array_slice($data, 0, -104); $fields = array_slice($data, 7, -104);
        if (count($signature) !== 104) throw new DomainException('format');
        $amountMsat = $this->amountMsat($m[1], $m[2]);
        if ($amountMsat !== $expectedSats * 1000) throw new DomainException('amount');
        $timestamp = 0; foreach (array_slice($data, 0, 7) as $v) $timestamp = ($timestamp << 5) | $v;
        $expiry = 3600; $seen = []; $payeeKey = null;
        for ($i=0, $n=count($fields); $i<$n;) {
            if ($i + 3 > $n) throw new DomainException('format');
            $type = self::CHARSET[$fields[$i++]]; $length = ($fields[$i++] << 5) | $fields[$i++];
            if ($i + $length > $n) throw new DomainException('format');
            $value = array_slice($fields, $i, $length); $i += $length; $seen[$type] = ($seen[$type] ?? 0) + 1;
            if (in_array($type, ['p','s','h'], true) && $length !== 52) throw new DomainException('format');
            if ($type === 'n') { if ($length !== 53) throw new DomainException('format'); $payeeKey = $value; }
            if ($type === 'x') { $expiry = 0; foreach ($value as $v) $expiry = ($expiry << 5) | $v; }
        }
        if (($seen['p'] ?? 0) !== 1 || ($seen['s'] ?? 0) !== 1 || (($seen['d'] ?? 0) + ($seen['h'] ?? 0)) !== 1 || ($seen['x'] ?? 0) > 1) throw new DomainException('format');
        if (($seen['n'] ?? 0) > 1 || !$this->signatureVerifier->verify($hrp, $unsignedData, $signature, $payeeKey)) throw new DomainException('signature');
        if ($timestamp > $now->getTimestamp() + 300) throw new DomainException('time');
        $expires = (new DateTimeImmutable('@' . ($timestamp + $expiry)))->setTimezone($now->getTimezone());
        if ($expires->getTimestamp() < $now->getTimestamp() + 600) throw new DomainException('expiry');
        return new PaymentRequest('', $invoice, $expectedSats, $expires, $now);
    }

    private function amountMsat(string $digits, string $multiplier): int
    {
        if (strlen($digits) > 15) throw new DomainException('amount'); $n=(int)$digits;
        return match($multiplier) {'m'=>$n*100000000,'u'=>$n*100000,'n'=>$n*100,'p'=>$n%10===0?intdiv($n,10):throw new DomainException('amount'),default=>$n*100000000000};
    }
    /** @return list<int> */ private function expandHrp(string $hrp): array { $a=[]; foreach(str_split($hrp) as $c)$a[]=ord($c)>>5; $a[]=0; foreach(str_split($hrp) as $c)$a[]=ord($c)&31; return $a; }
    /** @param list<int> $values */ private function polymod(array $values): int { $c=1; foreach($values as $v){$top=$c>>25;$c=(($c&0x1ffffff)<<5)^$v; foreach([0x3b6a57b2,0x26508e6d,0x1ea119fa,0x3d4233dd,0x2a1462b3] as $i=>$g)if(($top>>$i)&1)$c^=$g;} return $c; }
}
