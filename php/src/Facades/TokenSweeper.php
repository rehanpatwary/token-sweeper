<?php

namespace Multicoin\TokenSweeper\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static string createDepositAddress(int $userId, int $chainId)
 * @method static \Illuminate\Database\Eloquent\Collection getUserDepositAddresses(int $userId)
 * @method static bool processSweep(string $depositAddress, string $tokenAddress, int $chainId)
 * @method static array generateWallet()
 * @method static string getPrivateKey(string $address, int $chainId)
 *
 * @see \Multicoin\TokenSweeper\Services\WalletService
 * @see \Multicoin\TokenSweeper\Services\SweeperService
 */
class TokenSweeper extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'token-sweeper';
    }
}
