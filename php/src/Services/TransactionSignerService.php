<?php

namespace Multicoin\TokenSweeper\Services;

use Elliptic\EC;
use kornrunner\Keccak;

class TransactionSignerService
{
    protected EC $ec;

    public function __construct()
    {
        $this->ec = new EC('secp256k1');
    }

    public function signTransaction(array $transaction, string $privateKey, int $chainId): string
    {
        $privateKey = str_replace('0x', '', $privateKey);

        $raw = [
            $this->encodeNumber($transaction['nonce']),
            $this->encodeNumber($transaction['gasPrice']),
            $this->encodeNumber($transaction['gasLimit']),
            $transaction['to'] ? $this->encodeAddress($transaction['to']) : '',
            $this->encodeNumber($transaction['value']),
            $transaction['data'] ?? '',
        ];

        if ($chainId) {
            $raw[] = $this->encodeNumber($chainId);
            $raw[] = '';
            $raw[] = '';
        }

        $encoded = $this->rlpEncode($raw);
        $hash = Keccak::hash(hex2bin($encoded), 256);

        $key = $this->ec->keyFromPrivate($privateKey, 'hex');
        $signature = $key->sign($hash, ['canonical' => true]);

        $r = str_pad($signature->r->toString(16), 64, '0', STR_PAD_LEFT);
        $s = str_pad($signature->s->toString(16), 64, '0', STR_PAD_LEFT);
        $v = dechex($signature->recoveryParam + ($chainId ? $chainId * 2 + 35 : 27));

        $signedRaw = [
            $this->encodeNumber($transaction['nonce']),
            $this->encodeNumber($transaction['gasPrice']),
            $this->encodeNumber($transaction['gasLimit']),
            $transaction['to'] ? $this->encodeAddress($transaction['to']) : '',
            $this->encodeNumber($transaction['value']),
            $transaction['data'] ?? '',
            $v,
            '0x' . $r,
            '0x' . $s
        ];

        return '0x' . $this->rlpEncode($signedRaw);
    }

    protected function encodeNumber($number): string
    {
        if (is_string($number)) {
            $number = str_replace('0x', '', $number);
            return '0x' . ltrim($number, '0') ?: '0';
        }
        return '0x' . dechex($number);
    }

    protected function encodeAddress(string $address): string
    {
        return strtolower(str_replace('0x', '', $address));
    }

    protected function rlpEncode($input): string
    {
        if (is_array($input)) {
            $output = '';
            foreach ($input as $item) {
                $output .= $this->rlpEncode($item);
            }
            return $this->encodeLength(strlen($output) / 2, 192) . $output;
        } else {
            $input = str_replace('0x', '', $input);
            if (strlen($input) === 0) {
                return '80';
            } elseif (strlen($input) === 2 && hexdec($input) < 128) {
                return $input;
            } else {
                return $this->encodeLength(strlen($input) / 2, 128) . $input;
            }
        }
    }

    protected function encodeLength(int $len, int $offset): string
    {
        if ($len < 56) {
            return dechex($len + $offset);
        } else {
            $hexLen = dechex($len);
            return dechex(strlen($hexLen) / 2 + $offset + 55) . $hexLen;
        }
    }
}
