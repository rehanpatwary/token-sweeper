<?php

namespace Multicoin\TokenSweeper\Commands;

use Illuminate\Console\Command;
use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Services\Web3Service;

class CheckBalanceCommand extends Command
{
    protected $signature = 'sweeper:balance {address} {token?} {chain_id=1}';
    protected $description = 'Check balance of an address';

    public function handle(): int
    {
        $address = $this->argument('address');
        $tokenAddress = $this->argument('token');
        $chainId = (int) $this->argument('chain_id');

        $chain = Chain::where('chain_id', $chainId)->firstOrFail();
        $web3 = new Web3Service($chain->rpc_url, $chainId);

        // Check native balance
        $nativeBalance = hexdec($web3->getBalance($address));
        $nativeFormatted = $nativeBalance / 1e18;

        $this->info("Chain: {$chain->name}");
        $this->info("Address: {$address}");
        $this->info("Native Balance: {$nativeFormatted} {$chain->native_symbol}");

        if ($tokenAddress) {
            $balanceHex = $web3->callContract(
                $tokenAddress,
                '0x70a08231' . str_pad(str_replace('0x', '', $address), 64, '0', STR_PAD_LEFT)
            );

            $tokenBalance = hexdec($balanceHex);
            $this->info("Token Balance: {$tokenBalance} (raw)");
        }

        return self::SUCCESS;
    }
}
