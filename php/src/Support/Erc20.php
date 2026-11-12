<?php

namespace Multicoin\TokenSweeper\Support;

class Erc20
{
    public static function balanceOfData(string $address): string
    {
        return '0x70a08231' . self::encodeAddressWord($address);
    }

    public static function transferData(string $to, string|int $amount): string
    {
        return '0xa9059cbb' . self::encodeAddressWord($to) . Quantity::decimalToHex($amount, false, 32);
    }

    public static function encodeAddressWord(string $address): string
    {
        $hex = strtolower(str_replace('0x', '', trim($address)));
        if (!preg_match('/^[0-9a-f]{40}$/', $hex)) {
            throw new \InvalidArgumentException('Invalid Ethereum address');
        }

        return str_pad($hex, 64, '0', STR_PAD_LEFT);
    }

    public static function isAddress(string $address): bool
    {
        return (bool) preg_match('/^0x[0-9a-fA-F]{40}$/', trim($address));
    }
}
