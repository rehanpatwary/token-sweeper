<?php

namespace Multicoin\TokenSweeper\Commands;

use Illuminate\Console\Command;
use Multicoin\TokenSweeper\Services\SweeperService;

class ProcessSweepCommand extends Command
{
    protected $signature = 'sweeper:sweep {address} {token} {chain_id}';
    protected $description = 'Manually process a token sweep';

    public function handle(SweeperService $sweeper): int
    {
        $address = $this->argument('address');
        $token = $this->argument('token');
        $chainId = (int) $this->argument('chain_id');

        $this->info("Processing sweep for {$address}...");

        $result = $sweeper->processSweep($address, $token, $chainId);

        if ($result) {
            $this->info("✅ Sweep completed successfully!");
            return self::SUCCESS;
        }

        $this->error("❌ Sweep failed!");
        return self::FAILURE;
    }
}
