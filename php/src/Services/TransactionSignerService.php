<?php

namespace Multicoin\TokenSweeper\Services;

use Elliptic\EC;
use kornrunner\Keccak;
use Multicoin\TokenSweeper\Support\Rlp;

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

        $signingPayload = [
            Rlp::encodeInteger($transaction['nonce']),
            Rlp::encodeInteger($transaction['gasPrice']),
            Rlp::encodeInteger($transaction['gasLimit']),
            $this->encodeAddress($transaction['to'] ?? ''),
            Rlp::encodeInteger($transaction['value']),
            $this->encodeData($transaction['data'] ?? ''),
        ];

        if ($chainId > 0) {
            $signingPayload[] = Rlp::encodeInteger($chainId);
            $signingPayload[] = Rlp::encodeBytes('');
            $signingPayload[] = Rlp::encodeBytes('');
        }

        $encoded = Rlp::encodeList($signingPayload);
        $hash = Keccak::hash(hex2bin($encoded), 256);

        $key = $this->ec->keyFromPrivate($privateKey, 'hex');
        $signature = $key->sign($hash, ['canonical' => true]);

        $recovery = (int) $signature->recoveryParam;
        $v = $chainId > 0 ? $recovery + ($chainId * 2 + 35) : $recovery + 27;

        $signedPayload = [
            Rlp::encodeInteger($transaction['nonce']),
            Rlp::encodeInteger($transaction['gasPrice']),
            Rlp::encodeInteger($transaction['gasLimit']),
            $this->encodeAddress($transaction['to'] ?? ''),
            Rlp::encodeInteger($transaction['value']),
            $this->encodeData($transaction['data'] ?? ''),
            Rlp::encodeInteger($v),
            Rlp::encodeInteger('0x' . $signature->r->toString(16)),
            Rlp::encodeInteger('0x' . $signature->s->toString(16)),
        ];

        return '0x' . Rlp::encodeList($signedPayload);
    }

    protected function encodeAddress(string $address): string
    {
        $address = strtolower(str_replace('0x', '', trim($address)));
        if ($address === '') {
            return Rlp::encodeBytes('');
        }

        if (!preg_match('/^[0-9a-f]{40}$/', $address)) {
            throw new \InvalidArgumentException('Invalid transaction address');
        }

        return Rlp::encodeBytes($address);
    }

    protected function encodeData(string $data): string
    {
        $data = strtolower(trim($data));
        if ($data === '0x') {
            $data = '';
        }

        return Rlp::encodeBytes($data);
    }
}
