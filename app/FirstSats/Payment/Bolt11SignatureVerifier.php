<?php

declare(strict_types=1);

namespace Sats4you\Pulse\FirstSats\Payment;

use phpseclib3\Math\BigInteger;

final class Bolt11SignatureVerifier
{
    private const FIELD_PRIME = 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEFFFFFC2F';
    private const GROUP_ORDER = 'FFFFFFFFFFFFFFFFFFFFFFFFFFFFFFFEBAAEDCE6AF48A03BBFD25E8CD0364141';
    private const GENERATOR_X = '79BE667EF9DCBBAC55A06295CE870B07029BFCDB2DCE28D959F2815B16F81798';
    private const GENERATOR_Y = '483ADA7726A3C4655DA4FBFC0E1108A8FD17B448A68554199C47D08FFB10D4B8';

    /**
     * @param list<int> $unsignedData
     * @param list<int> $signatureWords
     * @param list<int>|null $payeeKeyWords
     */
    public function verify(string $hrp, array $unsignedData, array $signatureWords, ?array $payeeKeyWords): bool
    {
        $signature = $this->convertBits($signatureWords, 5, 8, false);
        if ($signature === null || strlen($signature) !== 65) return false;

        $recoveryId = ord($signature[64]);
        if ($recoveryId > 3) return false;

        $r = new BigInteger(substr($signature, 0, 32), 256);
        $s = new BigInteger(substr($signature, 32, 32), 256);
        $n = $this->number(self::GROUP_ORDER);
        $zero = new BigInteger(0);
        if ($r->compare($zero) <= 0 || $r->compare($n) >= 0 || $s->compare($zero) <= 0 || $s->compare($n) >= 0) return false;

        // BOLT11 requires low-S signatures when an explicit payee key is present.
        if ($payeeKeyWords !== null && $s->compare($n->divide(new BigInteger(2))[0]) > 0) return false;

        $message = $this->convertBits($unsignedData, 5, 8, true);
        if ($message === null) return false;
        $hash = hash('sha256', $hrp . $message, true);

        $publicKey = $this->recoverPublicKey(new BigInteger($hash, 256), $r, $s, $recoveryId);
        if ($publicKey === null) return false;

        if ($payeeKeyWords === null) return true;
        $encodedPayeeKey = $this->convertBits($payeeKeyWords, 5, 8, false);

        return $encodedPayeeKey !== null
            && strlen($encodedPayeeKey) === 33
            && hash_equals($encodedPayeeKey, $this->compress($publicKey));
    }

    /** @return array{BigInteger, BigInteger}|null */
    private function recoverPublicKey(BigInteger $e, BigInteger $r, BigInteger $s, int $recoveryId): ?array
    {
        $p = $this->number(self::FIELD_PRIME);
        $n = $this->number(self::GROUP_ORDER);
        $x = $r->add($n->multiply(new BigInteger($recoveryId >> 1)));
        if ($x->compare($p) >= 0) return null;

        $alpha = $this->mod($x->multiply($x)->multiply($x)->add(new BigInteger(7)), $p);
        $exponent = $p->add(new BigInteger(1))->divide(new BigInteger(4))[0];
        $y = $alpha->modPow($exponent, $p);
        if (!$this->mod($y->multiply($y), $p)->equals($alpha)) return null;
        if (($y->isOdd() ? 1 : 0) !== ($recoveryId & 1)) $y = $p->subtract($y);
        $pointR = [$x, $y];

        // secp256k1 has cofactor 1, so every valid curve point is in G's subgroup.
        $generator = [$this->number(self::GENERATOR_X), $this->number(self::GENERATOR_Y)];
        $inverse = $r->modInverse($n);
        if ($inverse === false) return null;
        $coefficientR = $this->mod($inverse->multiply($s), $n);
        $coefficientG = $this->mod($inverse->multiply($this->mod($e, $n))->negate(), $n);

        return $this->add(
            $this->multiply($coefficientR, $pointR, $p),
            $this->multiply($coefficientG, $generator, $p),
            $p,
        );
    }

    /**
     * @param array{BigInteger, BigInteger}|null $left
     * @param array{BigInteger, BigInteger}|null $right
     * @return array{BigInteger, BigInteger}|null
     */
    private function add(?array $left, ?array $right, BigInteger $p): ?array
    {
        if ($left === null) return $right;
        if ($right === null) return $left;
        [$x1, $y1] = $left;
        [$x2, $y2] = $right;

        if ($x1->equals($x2)) {
            if ($this->mod($y1->add($y2), $p)->equals(new BigInteger(0))) return null;
            $numerator = (new BigInteger(3))->multiply($x1)->multiply($x1);
            $denominator = (new BigInteger(2))->multiply($y1);
        } else {
            $numerator = $y2->subtract($y1);
            $denominator = $x2->subtract($x1);
        }

        $inverse = $this->mod($denominator, $p)->modInverse($p);
        if ($inverse === false) return null;
        $slope = $this->mod($numerator->multiply($inverse), $p);
        $x3 = $this->mod($slope->multiply($slope)->subtract($x1)->subtract($x2), $p);
        $y3 = $this->mod($slope->multiply($x1->subtract($x3))->subtract($y1), $p);

        return [$x3, $y3];
    }

    /**
     * @param array{BigInteger, BigInteger}|null $point
     * @return array{BigInteger, BigInteger}|null
     */
    private function multiply(BigInteger $scalar, ?array $point, BigInteger $p): ?array
    {
        if ($point === null) return null;
        $result = null;
        foreach (str_split($scalar->toBits()) as $bit) {
            $result = $this->doubleJacobian($result, $p);
            if ($bit === '1') $result = $this->addAffineToJacobian($result, $point, $p);
        }
        return $this->toAffine($result, $p);
    }

    /**
     * @param array{BigInteger, BigInteger, BigInteger}|null $point
     * @return array{BigInteger, BigInteger, BigInteger}|null
     */
    private function doubleJacobian(?array $point, BigInteger $p): ?array
    {
        if ($point === null || $point[1]->equals(new BigInteger(0))) return null;
        [$x, $y, $z] = $point;
        $ySquared = $this->mod($y->multiply($y), $p);
        $s = $this->mod((new BigInteger(4))->multiply($x)->multiply($ySquared), $p);
        $m = $this->mod((new BigInteger(3))->multiply($x)->multiply($x), $p);
        $x3 = $this->mod($m->multiply($m)->subtract((new BigInteger(2))->multiply($s)), $p);
        $yFourth = $this->mod($ySquared->multiply($ySquared), $p);
        $y3 = $this->mod($m->multiply($s->subtract($x3))->subtract((new BigInteger(8))->multiply($yFourth)), $p);
        $z3 = $this->mod((new BigInteger(2))->multiply($y)->multiply($z), $p);
        return [$x3, $y3, $z3];
    }

    /**
     * @param array{BigInteger, BigInteger, BigInteger}|null $jacobian
     * @param array{BigInteger, BigInteger} $affine
     * @return array{BigInteger, BigInteger, BigInteger}|null
     */
    private function addAffineToJacobian(?array $jacobian, array $affine, BigInteger $p): ?array
    {
        if ($jacobian === null) return [$affine[0], $affine[1], new BigInteger(1)];
        [$x1, $y1, $z1] = $jacobian;
        [$x2, $y2] = $affine;
        $z1Squared = $this->mod($z1->multiply($z1), $p);
        $u2 = $this->mod($x2->multiply($z1Squared), $p);
        $s2 = $this->mod($y2->multiply($z1Squared)->multiply($z1), $p);
        $h = $this->mod($u2->subtract($x1), $p);
        $difference = $this->mod($s2->subtract($y1), $p);
        if ($h->equals(new BigInteger(0))) {
            return $difference->equals(new BigInteger(0)) ? $this->doubleJacobian($jacobian, $p) : null;
        }
        $i = $this->mod((new BigInteger(4))->multiply($h)->multiply($h), $p);
        $j = $this->mod($h->multiply($i), $p);
        $r = $this->mod((new BigInteger(2))->multiply($difference), $p);
        $v = $this->mod($x1->multiply($i), $p);
        $x3 = $this->mod($r->multiply($r)->subtract($j)->subtract((new BigInteger(2))->multiply($v)), $p);
        $y3 = $this->mod($r->multiply($v->subtract($x3))->subtract((new BigInteger(2))->multiply($y1)->multiply($j)), $p);
        $z3 = $this->mod((new BigInteger(2))->multiply($z1)->multiply($h), $p);
        return [$x3, $y3, $z3];
    }

    /**
     * @param array{BigInteger, BigInteger, BigInteger}|null $point
     * @return array{BigInteger, BigInteger}|null
     */
    private function toAffine(?array $point, BigInteger $p): ?array
    {
        if ($point === null) return null;
        [$x, $y, $z] = $point;
        $inverse = $z->modInverse($p);
        if ($inverse === false) return null;
        $inverseSquared = $this->mod($inverse->multiply($inverse), $p);
        return [
            $this->mod($x->multiply($inverseSquared), $p),
            $this->mod($y->multiply($inverseSquared)->multiply($inverse), $p),
        ];
    }

    /** @param array{BigInteger, BigInteger} $point */
    private function compress(array $point): string
    {
        $x = str_pad($point[0]->toBytes(), 32, "\0", STR_PAD_LEFT);
        return ($point[1]->isOdd() ? "\x03" : "\x02") . $x;
    }

    private function mod(BigInteger $number, BigInteger $modulus): BigInteger
    {
        $remainder = $number->divide($modulus)[1];
        return $remainder->compare(new BigInteger(0)) < 0 ? $remainder->add($modulus) : $remainder;
    }

    private function number(string $hex): BigInteger
    {
        return new BigInteger($hex, 16);
    }

    /** @param list<int> $values */
    private function convertBits(array $values, int $fromBits, int $toBits, bool $pad): ?string
    {
        $accumulator = 0;
        $bits = 0;
        $maximum = (1 << $toBits) - 1;
        $output = '';
        foreach ($values as $value) {
            if ($value < 0 || ($value >> $fromBits) !== 0) return null;
            $accumulator = ($accumulator << $fromBits) | $value;
            $bits += $fromBits;
            while ($bits >= $toBits) {
                $bits -= $toBits;
                $output .= chr(($accumulator >> $bits) & $maximum);
            }
            $accumulator &= (1 << $bits) - 1;
        }
        if ($pad && $bits > 0) {
            $output .= chr(($accumulator << ($toBits - $bits)) & $maximum);
        } elseif (!$pad && ($bits >= $fromBits || (($accumulator << ($toBits - $bits)) & $maximum) !== 0)) {
            return null;
        }
        return $output;
    }
}
