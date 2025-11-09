<?php

namespace Multicoin\TokenSweeper\Commands;

use Illuminate\Console\Command;
use Multicoin\TokenSweeper\Services\WalletService;

class GenerateDepositAddressCommand extends Command
{
    protected $signature = 'sweeper:generate-address {user_id} {chain_id}';
    protected $description = 'Generate deposit address for a user on specific chain';

    public function handle(WalletService $walletService): int
    {
        $userId = (int) $this->argument('user_id');
        $chainId = (int) $this->argument('chain_id');

        $this->info("Generating deposit address for user {$userId} on chain {$chainId}...");

        $address = $walletService->createDepositAddress($userId, $chainId);

        $this->info("✅ Deposit address created: {$address}");

        return self::SUCCESS;
    }
}
