<?php

namespace Multicoin\TokenSweeper\Commands;

use Illuminate\Console\Command;
use Multicoin\TokenSweeper\Models\{Chain, Token};
use Illuminate\Support\Facades\Crypt;

class SeedChainsCommand extends Command
{
    protected $signature = 'sweeper:seed';
    protected $description = 'Seed default chains and tokens';

    public function handle(): int
    {
        $this->info('Seeding chains and tokens...');

        $chains = config('token-sweeper.default_chains', []);

        foreach ($chains as $chainData) {
            Chain::updateOrCreate(
                ['chain_id' => $chainData['chain_id']],
                $chainData
            );

            $this->info("✅ Seeded chain: {$chainData['name']}");
        }

        $tokens = config('token-sweeper.default_tokens', []);

        foreach ($tokens as $tokenData) {
            Token::updateOrCreate(
                [
                    'chain_id' => $tokenData['chain_id'],
                    'contract_address' => $tokenData['contract_address']
                ],
                $tokenData
            );

            $this->info("✅ Seeded token: {$tokenData['symbol']} on chain {$tokenData['chain_id']}");
        }

        $this->info('');
        $this->info('✅ Seeding completed!');

        return self::SUCCESS;
    }
}
