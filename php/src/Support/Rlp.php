<?php

namespace Multicoin\TokenSweeper\Support;

class Rlp
{
    public static function encodeInteger(string|int $value): string
    {
        $decimal = Quantity::toDecimal($value);
        if (bccomp($decimal, '0', 0) === 0) {
            return self::encodeBytes('');
        }

        return self::encodeBytes(Quantity::decimalToHex($decimal, false));
    }

    public static function encodeBytes(string $hex): string
    {
        $hex = strtolower(str_replace('0x', '', $hex));
        if ($hex === '') {
            return '80';
        }

        if (strlen($hex) % 2 !== 0) {
            throw new \InvalidArgumentException('RLP byte string must have an even number of hex digits');
        }

        if (!preg_match('/^[0-9a-f]*$/', $hex)) {
            throw new \InvalidArgumentException('RLP byte string must be hex');
        }

        if (strlen($hex) === 2 && hexdec($hex) < 128) {
            return $hex;
        }

        return self::lengthPrefix(intdiv(strlen($hex), 2), 0x80) . $hex;
    }

    /**
     * @param array<int, string> $encodedItems
     */
    public static function encodeList(array $encodedItems): string
    {
        $payload = implode('', $encodedItems);

        return self::lengthPrefix(intdiv(strlen($payload), 2), 0xc0) . $payload;
    }

    private static function lengthPrefix(int $length, int $offset): string
    {
        if ($length < 56) {
            return str_pad(dechex($length + $offset), 2, '0', STR_PAD_LEFT);
        }

        $lengthHex = dechex($length);
        if (strlen($lengthHex) % 2 === 1) {
            $lengthHex = '0' . $lengthHex;
        }

        $prefix = $offset + 55 + intdiv(strlen($lengthHex), 2);

        return str_pad(dechex($prefix), 2, '0', STR_PAD_LEFT) . $lengthHex;
    }
}
