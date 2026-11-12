<?php

namespace Multicoin\TokenSweeper;

use Illuminate\Database\Eloquent\Collection;
use Multicoin\TokenSweeper\Services\SweeperService;
use Multicoin\TokenSweeper\Services\WalletService;

class TokenSweeperManager
{
    public function __construct(
        protected WalletService $wallets,
        protected SweeperService $sweeper,
    ) {
    }

    public function createDepositAddress(int $userId, int $chainId): string
    {
        return $this->wallets->createDepositAddress($userId, $chainId);
    }

    public function getUserDepositAddresses(int $userId): Collection
    {
        return $this->wallets->getUserDepositAddresses($userId);
    }

    public function generateWallet(): array
    {
        return $this->wallets->generateWallet();
    }

    public function getPrivateKey(string $address, int $chainId): string
    {
        return $this->wallets->getPrivateKey($address, $chainId);
    }

    public function processSweep(string $depositAddress, string $tokenAddress, int $chainId): bool
    {
        return $this->sweeper->processSweep($depositAddress, $tokenAddress, $chainId);
    }
}
