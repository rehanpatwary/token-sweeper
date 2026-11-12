<?php

// src/Services/MonitorService.php

namespace Multicoin\TokenSweeper\Services;

use Illuminate\Support\Facades\{Cache, Log};
use Multicoin\TokenSweeper\Events\DepositDetected;
use Multicoin\TokenSweeper\Models\{Chain, DepositAddress, Token};
use Multicoin\TokenSweeper\Support\ChainRpc;
use Multicoin\TokenSweeper\Support\Erc20;
use Multicoin\TokenSweeper\Support\Quantity;

class MonitorService
{
    public function __construct(
        protected SweeperService $sweeper,
        protected WalletService $walletService
    ) {
    }

    public function startMonitoring(): void
    {
        Log::info("Starting multi-chain token monitor");

        $activeChains = Chain::active()->get();

        foreach ($activeChains as $chain) {
            Log::info("Monitoring {$chain->name} (Chain ID: {$chain->chain_id})");
        }

        // Check existing balances first
        $this->checkExistingBalances();

        // Start monitoring loop
        while (true) {
            foreach ($activeChains as $chain) {
                $this->monitorChain($chain);
            }

            sleep(config('token-sweeper.monitoring.check_interval', 5));
        }
    }

    public function monitorChain(Chain $chain): void
    {
        try {
            $web3 = ChainRpc::connect($chain);
            $currentBlock = Quantity::toInt($web3->getBlockNumber());

            $cacheKey = "last_block_{$chain->chain_id}";
            $lastBlock = Cache::get($cacheKey, $currentBlock - 1);

            if ($lastBlock >= $currentBlock) {
                return;
            }

            $fromBlock = (int) $lastBlock + 1;

            $tokens = Token::active()->forChain($chain->chain_id)->get();
            $depositAddresses = DepositAddress::forChain($chain->chain_id)->get();
            $addressMap = $depositAddresses->pluck('address')->map(fn ($a) => strtolower($a))->toArray();

            foreach ($tokens as $token) {
                $this->monitorToken($token, $chain, $web3, $fromBlock, $currentBlock, $addressMap);
            }

            Cache::put($cacheKey, $currentBlock, now()->addHours(24));

        } catch (\Exception $e) {
            Log::error("Error monitoring chain {$chain->chain_id}: {$e->getMessage()}");
        }
    }

    protected function monitorToken(
        Token $token,
        Chain $chain,
        Web3Service $web3,
        int $fromBlock,
        int $currentBlock,
        array $addressMap
    ): void {
        $transferTopic = '0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef';

        $logs = $web3->getLogs([
            'fromBlock' => '0x' . dechex($fromBlock),
            'toBlock' => '0x' . dechex($currentBlock),
            'address' => $token->contract_address,
            'topics' => [$transferTopic],
        ]);

        foreach ($logs as $log) {
            if (!isset($log['topics'][2]) || !is_string($log['topics'][2]) || !isset($log['data']) || !is_string($log['data'])) {
                continue;
            }

            $toAddress = '0x' . substr($log['topics'][2], -40);

            if (in_array(strtolower($toAddress), $addressMap, true)) {
                $amount = Quantity::hexToDecimal($log['data']);

                Log::info("Deposit detected on {$chain->name}", [
                    'token' => $token->symbol,
                    'to' => $toAddress,
                    'amount' => $amount,
                    'decimals' => $token->decimals,
                    'tx_hash' => $log['transactionHash'] ?? null,
                ]);

                event(new DepositDetected($toAddress, $token->contract_address, $chain->chain_id, $amount));

                // Process sweep
                $this->sweeper->processSweep($toAddress, $token->contract_address, $chain->chain_id);
            }
        }
    }

    public function checkExistingBalances(): void
    {
        Log::info("Checking existing balances");

        $chains = Chain::active()->get();

        foreach ($chains as $chain) {
            $web3 = ChainRpc::connect($chain);
            $tokens = Token::active()->forChain($chain->chain_id)->get();
            $depositAddresses = DepositAddress::forChain($chain->chain_id)->get();

            foreach ($depositAddresses as $deposit) {
                foreach ($tokens as $token) {
                    try {
                        $balance = Quantity::hexToDecimal($web3->callContract(
                            $token->contract_address,
                            Erc20::balanceOfData($deposit->address)
                        ));

                        if (Quantity::compare($balance, '0') === 1) {
                            Log::info("Found existing balance: {$deposit->address} has {$balance} base units of {$token->symbol} on {$chain->name}");

                            $this->sweeper->processSweep($deposit->address, $token->contract_address, $chain->chain_id);
                        }

                    } catch (\Exception $e) {
                        Log::error("Error checking balance: {$e->getMessage()}");
                    }
                }
            }
        }

        Log::info("Existing balance check completed");
    }
}
