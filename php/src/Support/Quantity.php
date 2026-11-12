<?php

namespace Multicoin\TokenSweeper\Support;

class Quantity
{
    public static function toDecimal(string|int $value): string
    {
        if (is_int($value)) {
            if ($value < 0) {
                throw new \InvalidArgumentException('Quantity cannot be negative');
            }

            return (string) $value;
        }

        $value = trim($value);
        if ($value === '') {
            throw new \InvalidArgumentException('Quantity cannot be empty');
        }

        if (str_starts_with($value, '0x') || str_starts_with($value, '0X')) {
            return self::hexToDecimal($value);
        }

        if (!preg_match('/^(0|[1-9][0-9]*)$/', $value)) {
            throw new \InvalidArgumentException('Invalid decimal quantity');
        }

        return $value;
    }

    public static function hexToDecimal(string $hex): string
    {
        $hex = strtolower($hex);
        if (str_starts_with($hex, '0x')) {
            $hex = substr($hex, 2);
        }

        if (!preg_match('/^[0-9a-f]*$/', $hex)) {
            throw new \InvalidArgumentException('Invalid hex quantity');
        }

        $hex = ltrim($hex, '0');
        if ($hex === '') {
            return '0';
        }

        $decimal = '0';
        foreach (str_split($hex) as $digit) {
            $decimal = bcadd(bcmul($decimal, '16'), (string) hexdec($digit));
        }

        return $decimal;
    }

    public static function decimalToHex(string|int $decimal, bool $withPrefix = true, ?int $padBytes = null): string
    {
        $decimal = self::toDecimal($decimal);

        if (bccomp($decimal, '0') === 0) {
            if ($padBytes === null) {
                return $withPrefix ? '0x0' : '0';
            }

            $hex = str_repeat('0', $padBytes * 2);

            return ($withPrefix ? '0x' : '') . $hex;
        }

        $hex = '';
        $value = $decimal;
        while (bccomp($value, '0') === 1) {
            $hex = dechex((int) bcmod($value, '16')) . $hex;
            $value = bcdiv($value, '16', 0);
        }

        if ($padBytes !== null) {
            $width = $padBytes * 2;
            if (strlen($hex) > $width) {
                throw new \InvalidArgumentException('Quantity exceeds the requested width');
            }
            $hex = str_pad($hex, $width, '0', STR_PAD_LEFT);
        } elseif (strlen($hex) % 2 === 1) {
            $hex = '0' . $hex;
        }

        return ($withPrefix ? '0x' : '') . $hex;
    }

    public static function compare(string|int $left, string|int $right): int
    {
        return bccomp(self::toDecimal($left), self::toDecimal($right));
    }

    public static function add(string|int $left, string|int $right): string
    {
        return bcadd(self::toDecimal($left), self::toDecimal($right));
    }

    public static function sub(string|int $left, string|int $right): string
    {
        return bcsub(self::toDecimal($left), self::toDecimal($right));
    }

    public static function mul(string|int $left, string|int $right): string
    {
        return bcmul(self::toDecimal($left), self::toDecimal($right));
    }

    public static function toInt(string|int $value): int
    {
        $decimal = self::toDecimal($value);
        if (bccomp($decimal, (string) PHP_INT_MAX) === 1) {
            throw new \InvalidArgumentException('Quantity does not fit in a PHP integer');
        }

        return (int) $decimal;
    }
}
