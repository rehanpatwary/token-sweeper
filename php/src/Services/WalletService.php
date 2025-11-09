<?php

namespace Multicoin\TokenSweeper\Services;

use Elliptic\EC;
use kornrunner\Keccak;
use Illuminate\Support\Facades\Crypt;
use Multicoin\TokenSweeper\Models\DepositAddress;
use Multicoin\TokenSweeper\Models\Chain;

class WalletService
{
    protected EC $ec;

    public function __construct()
    {
        $this->ec = new EC('secp256k1');
    }

    public function generateWallet(): array
    {
        $key = $this->ec->genKeyPair();
        $privateKey = $key->getPrivate()->toString(16);
        $privateKey = str_pad($privateKey, 64, '0', STR_PAD_LEFT);

        $publicKey = $key->getPublic()->encode('hex');
        $publicKey = substr($publicKey, 2);

        $hash = Keccak::hash(hex2bin($publicKey), 256);
        $address = '0x' . substr($hash, -40);

        return [
            'address' => $address,
            'privateKey' => '0x' . $privateKey
        ];
    }

    public function createDepositAddress(int $userId, int $chainId): string
    {
        // Check if address already exists
        $existing = DepositAddress::forUser($userId)
            ->forChain($chainId)
            ->first();

        if ($existing) {
            return $existing->address;
        }

        $wallet = $this->generateWallet();

        DepositAddress::create([
            'user_id' => $userId,
            'chain_id' => $chainId,
            'address' => $wallet['address'],
            'private_key_encrypted' => Crypt::encryptString($wallet['privateKey']),
        ]);

        return $wallet['address'];
    }

    public function getPrivateKey(string $address, int $chainId): string
    {
        $depositAddress = DepositAddress::where('address', $address)
            ->where('chain_id', $chainId)
            ->firstOrFail();

        return Crypt::decryptString($depositAddress->private_key_encrypted);
    }

    public function getMasterWallet(int $chainId): array
    {
        $chain = Chain::where('chain_id', $chainId)->firstOrFail();

        return [
            'address' => $chain->master_wallet_address,
            'privateKey' => Crypt::decryptString($chain->master_private_key_encrypted),
        ];
    }

    public function getUserDepositAddresses(int $userId): \Illuminate\Database\Eloquent\Collection
    {
        return DepositAddress::with('chain')
            ->forUser($userId)
            ->get();
    }

    public function getAllDepositAddresses(?int $chainId = null): \Illuminate\Database\Eloquent\Collection
    {
        $query = DepositAddress::query();

        if ($chainId) {
            $query->forChain($chainId);
        }

        return $query->get();
    }
}
