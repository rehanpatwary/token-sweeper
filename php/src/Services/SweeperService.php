<?php

namespace Multicoin\TokenSweeper\Services;

use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Multicoin\TokenSweeper\Events\SweepCompleted;
use Multicoin\TokenSweeper\Events\SweepStarted;
use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Models\PendingSweep;
use Multicoin\TokenSweeper\Models\SweepLog;
use Multicoin\TokenSweeper\Models\Token;
use Multicoin\TokenSweeper\Support\ChainRpc;
use Multicoin\TokenSweeper\Support\Erc20;
use Multicoin\TokenSweeper\Support\MasterNonceLock;
use Multicoin\TokenSweeper\Support\Quantity;

class SweeperService
{
    public function __construct(
        protected WalletService $walletService,
        protected TransactionSignerService $signer
    ) {
    }

    public function processSweep(string $depositAddress, string $tokenAddress, int $chainId): bool
    {
        $lock = Cache::lock($this->sweepLockKey($depositAddress, $tokenAddress, $chainId), 120);

        try {
            return $lock->block(10, function () use ($depositAddress, $tokenAddress, $chainId) {
                return $this->performSweep($depositAddress, $tokenAddress, $chainId);
            });
        } catch (LockTimeoutException $e) {
            Log::info('Sweep already in progress', [
                'deposit_address' => $depositAddress,
                'token_address' => $tokenAddress,
                'chain_id' => $chainId,
            ]);

            return true;
        }
    }

    protected function performSweep(string $depositAddress, string $tokenAddress, int $chainId): bool
    {
        if ($this->inProgressSweep($depositAddress, $tokenAddress, $chainId)) {
            Log::info('Sweep already in progress', [
                'deposit_address' => $depositAddress,
                'token_address' => $tokenAddress,
                'chain_id' => $chainId,
            ]);

            return true;
        }

        $sweep = null;

        try {
            $chain = Chain::where('chain_id', $chainId)->firstOrFail();
            $token = $this->findToken($tokenAddress, $chainId);
            $this->assertHotWallet($chain);

            $web3 = ChainRpc::connect($chain);
            Log::info("Starting sweep for {$depositAddress} on {$chain->name}");

            $amount = $this->tokenBalance($web3, $token->contract_address, $depositAddress);
            if (Quantity::compare($amount, '0') === 0) {
                throw new \RuntimeException('No tokens to sweep');
            }

            $sweep = $this->claimSweep($depositAddress, $token, $chainId, $amount);
            event(new SweepStarted($sweep));

            $sweep->update(['status' => 'funding']);
            Log::info("Step 1: Funding address with {$chain->native_symbol} for gas");

            $fundingTxHash = $this->fundAddressWithGas($depositAddress, $chain, $web3);
            if ($fundingTxHash !== 'sufficient_balance') {
                Log::info("Funded. TxHash: {$fundingTxHash}");
                $sweep->update(['funding_tx_hash' => $fundingTxHash]);
                if ($web3->waitForConfirmation($fundingTxHash) !== true) {
                    throw new \RuntimeException("Funding transaction reverted: {$fundingTxHash}");
                }
            }

            $delay = (int) config('token-sweeper.monitoring.post_funding_delay_seconds', 2);
            if ($delay > 0) {
                sleep($delay);
            }

            $sweep->update(['status' => 'sweeping']);
            $amount = $this->tokenBalance($web3, $token->contract_address, $depositAddress);
            if (Quantity::compare($amount, '0') === 0) {
                throw new \RuntimeException('No tokens to sweep');
            }
            $sweep->update(['amount' => $amount]);

            Log::info("Step 2: Sweeping {$token->symbol} tokens");
            $sweepTxHash = $this->sweepTokens($depositAddress, $token->contract_address, $amount, $chain, $web3);
            Log::info("Tokens swept. TxHash: {$sweepTxHash}");

            $sweep->update(['sweep_tx_hash' => $sweepTxHash]);
            if ($web3->waitForConfirmation($sweepTxHash) !== true) {
                throw new \RuntimeException("Sweep transaction reverted: {$sweepTxHash}");
            }

            $sweep->markAsCompleted($sweepTxHash);

            SweepLog::create([
                'sweep_id' => $sweep->id,
                'chain_id' => $chainId,
                'deposit_address' => $depositAddress,
                'token_address' => $token->contract_address,
                'amount' => $amount,
                'funding_tx_hash' => $fundingTxHash !== 'sufficient_balance' ? $fundingTxHash : null,
                'sweep_tx_hash' => $sweepTxHash,
                'status' => 'completed',
            ]);

            event(new SweepCompleted($sweep));
            Log::info('Sweep completed successfully');

            $this->sweepRemainingGas($depositAddress, $chain, $web3);

            return true;
        } catch (\Throwable $e) {
            Log::error("Sweep failed: {$e->getMessage()}", [
                'deposit_address' => $depositAddress,
                'token_address' => $tokenAddress,
                'chain_id' => $chainId,
            ]);

            if ($sweep !== null) {
                $sweep->markAsFailed($e->getMessage());
                $sweep->incrementRetry();
            }

            return false;
        }
    }

    protected function fundAddressWithGas(string $depositAddress, Chain $chain, Web3Service $web3): string
    {
        $balance = $web3->getBalance($depositAddress);
        if (Quantity::compare($balance, $chain->gas_amount_wei) >= 0) {
            return 'sufficient_balance';
        }

        $lock = MasterNonceLock::acquire((int) $chain->chain_id);

        return $lock->block(15, function () use ($depositAddress, $chain, $web3) {
            $current = $web3->getBalance($depositAddress);
            if (Quantity::compare($current, $chain->gas_amount_wei) >= 0) {
                return 'sufficient_balance';
            }

            $masterWallet = $this->walletService->getMasterWallet($chain->chain_id);
            $transaction = [
                'nonce' => Quantity::toInt($web3->getTransactionCount($masterWallet['address'])),
                'gasPrice' => Quantity::toInt($web3->gasPrice()),
                'gasLimit' => 21000,
                'to' => $depositAddress,
                'value' => $chain->gas_amount_wei,
                'data' => '',
            ];

            $signedTx = $this->signer->signTransaction($transaction, $masterWallet['privateKey'], $chain->chain_id);

            return $web3->sendRawTransaction($signedTx);
        });
    }

    protected function sweepTokens(
        string $depositAddress,
        string $tokenAddress,
        string $amount,
        Chain $chain,
        Web3Service $web3
    ): string {
        $privateKey = $this->walletService->getPrivateKey($depositAddress, $chain->chain_id);
        $transaction = [
            'nonce' => Quantity::toInt($web3->getTransactionCount($depositAddress)),
            'gasPrice' => Quantity::toInt($web3->gasPrice()),
            'gasLimit' => $chain->gas_limit_token_transfer,
            'to' => $tokenAddress,
            'value' => '0x0',
            'data' => Erc20::transferData($chain->hot_wallet_address, $amount),
        ];

        $signedTx = $this->signer->signTransaction($transaction, $privateKey, $chain->chain_id);

        return $web3->sendRawTransaction($signedTx);
    }

    protected function sweepRemainingGas(string $depositAddress, Chain $chain, Web3Service $web3): void
    {
        try {
            $balance = Quantity::toDecimal($web3->getBalance($depositAddress));
            $gasPrice = Quantity::toDecimal($web3->gasPrice());
            $gasCost = Quantity::mul('21000', $gasPrice);
            $threshold = bcdiv(bcmul($gasCost, '11', 0), '10', 0);

            if (bccomp($balance, $threshold, 0) !== 1) {
                return;
            }

            $amountToSend = Quantity::sub($balance, $gasCost);
            if (bccomp($amountToSend, '0', 0) !== 1) {
                return;
            }

            $privateKey = $this->walletService->getPrivateKey($depositAddress, $chain->chain_id);
            $masterWallet = $this->walletService->getMasterWallet($chain->chain_id);
            $transaction = [
                'nonce' => Quantity::toInt($web3->getTransactionCount($depositAddress)),
                'gasPrice' => Quantity::toInt($gasPrice),
                'gasLimit' => 21000,
                'to' => $masterWallet['address'],
                'value' => $amountToSend,
                'data' => '',
            ];

            $signedTx = $this->signer->signTransaction($transaction, $privateKey, $chain->chain_id);
            $web3->sendRawTransaction($signedTx);

            Log::info("Swept remaining gas: {$amountToSend} wei {$chain->native_symbol}");
        } catch (\Throwable $e) {
            Log::warning("Could not sweep remaining gas: {$e->getMessage()}");
        }
    }

    protected function tokenBalance(Web3Service $web3, string $tokenAddress, string $depositAddress): string
    {
        return Quantity::hexToDecimal($web3->callContract($tokenAddress, Erc20::balanceOfData($depositAddress)));
    }

    protected function findToken(string $tokenAddress, int $chainId): Token
    {
        return Token::query()
            ->where('chain_id', $chainId)
            ->whereRaw('lower(contract_address) = ?', [strtolower($tokenAddress)])
            ->firstOrFail();
    }

    protected function assertHotWallet(Chain $chain): void
    {
        if (!Erc20::isAddress($chain->hot_wallet_address)) {
            throw new \InvalidArgumentException('Hot wallet address is missing or invalid');
        }
    }

    protected function inProgressSweep(string $depositAddress, string $tokenAddress, int $chainId): bool
    {
        return $this->sweepQuery($depositAddress, $tokenAddress, $chainId)
            ->whereIn('status', ['pending', 'funding', 'sweeping'])
            ->where('updated_at', '>=', now()->subMinutes($this->staleSweepMinutes()))
            ->exists();
    }

    protected function claimSweep(string $depositAddress, Token $token, int $chainId, string $amount): PendingSweep
    {
        $maxRetries = (int) config('token-sweeper.monitoring.max_retry_attempts', 3);
        $existing = $this->sweepQuery($depositAddress, $token->contract_address, $chainId)
            ->where(function ($query) use ($maxRetries) {
                $query->where(function ($failed) use ($maxRetries) {
                    $failed->where('status', 'failed')
                        ->where('retry_count', '<', $maxRetries);
                })->orWhere(function ($stale) {
                    $stale->whereIn('status', ['pending', 'funding', 'sweeping'])
                        ->where('updated_at', '<', now()->subMinutes($this->staleSweepMinutes()));
                });
            })
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            $existing->update([
                'status' => 'pending',
                'token_symbol' => $token->symbol,
                'amount' => $amount,
                'error_message' => null,
            ]);

            return $existing;
        }

        return PendingSweep::create([
            'deposit_address' => $depositAddress,
            'chain_id' => $chainId,
            'token_address' => $token->contract_address,
            'token_symbol' => $token->symbol,
            'amount' => $amount,
            'status' => 'pending',
        ]);
    }

    protected function sweepQuery(string $depositAddress, string $tokenAddress, int $chainId)
    {
        return PendingSweep::query()
            ->where('chain_id', $chainId)
            ->whereRaw('lower(deposit_address) = ?', [strtolower($depositAddress)])
            ->whereRaw('lower(token_address) = ?', [strtolower($tokenAddress)]);
    }

    protected function staleSweepMinutes(): int
    {
        $minutes = config('token-sweeper.monitoring.stale_sweep_minutes', 15);

        return is_numeric($minutes) ? (int) $minutes : 15;
    }

    protected function sweepLockKey(string $depositAddress, string $tokenAddress, int $chainId): string
    {
        return 'sweeper:' . $chainId . ':' . strtolower($depositAddress) . ':' . strtolower($tokenAddress);
    }
}
