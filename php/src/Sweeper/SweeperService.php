<?php
// src/Services/SweeperService.php

namespace Multicoin\TokenSweeper\Services;

use Multicoin\TokenSweeper\Models\{Chain, Token, PendingSweep, SweepLog};
use Multicoin\TokenSweeper\Events\{SweepStarted, SweepCompleted};
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SweeperService
{
    public function __construct(
        protected WalletService $walletService,
        protected TransactionSignerService $signer
    ) {}

    public function processSweep(string $depositAddress, string $tokenAddress, int $chainId): bool
    {
        $chain = Chain::where('chain_id', $chainId)->firstOrFail();
        $web3 = new Web3Service($chain->rpc_url, $chainId);

        Log::info("Starting sweep for {$depositAddress} on {$chain->name}");

        try {
            $token = Token::where('contract_address', $tokenAddress)
                ->where('chain_id', $chainId)
                ->firstOrFail();

            // Create sweep record
            $sweep = PendingSweep::create([
                'deposit_address' => $depositAddress,
                'chain_id' => $chainId,
                'token_address' => $tokenAddress,
                'token_symbol' => $token->symbol,
                'amount' => '0',
                'status' => 'pending',
            ]);

            event(new SweepStarted($sweep));

            // Step 1: Fund address with gas
            $sweep->update(['status' => 'funding']);
            Log::info("Step 1: Funding address with {$chain->native_symbol} for gas");

            $fundingTxHash = $this->fundAddressWithGas($depositAddress, $chain, $web3);

            if ($fundingTxHash !== 'sufficient_balance') {
                Log::info("Funded. TxHash: {$fundingTxHash}");
                $sweep->update(['funding_tx_hash' => $fundingTxHash]);
                $web3->waitForConfirmation($fundingTxHash);
            }

            sleep(2);

            // Step 2: Sweep tokens
            $sweep->update(['status' => 'sweeping']);
            Log::info("Step 2: Sweeping {$token->symbol} tokens");

            $sweepTxHash = $this->sweepTokens($depositAddress, $tokenAddress, $chain, $web3);
            Log::info("Tokens swept. TxHash: {$sweepTxHash}");

            $sweep->update(['sweep_tx_hash' => $sweepTxHash]);
            $web3->waitForConfirmation($sweepTxHash);

            // Mark as completed
            $sweep->markAsCompleted($sweepTxHash);

            // Create log
            SweepLog::create([
                'sweep_id' => $sweep->id,
                'chain_id' => $chainId,
                'deposit_address' => $depositAddress,
                'token_address' => $tokenAddress,
                'amount' => '0',
                'funding_tx_hash' => $fundingTxHash !== 'sufficient_balance' ? $fundingTxHash : null,
                'sweep_tx_hash' => $sweepTxHash,
                'status' => 'completed',
            ]);

            event(new SweepCompleted($sweep));

            Log::info("Sweep completed successfully!");

            // Optional: Sweep remaining gas
            $this->sweepRemainingGas($depositAddress, $chain, $web3);

            return true;

        } catch (\Exception $e) {
            Log::error("Sweep failed: {$e->getMessage()}", [
                'deposit_address' => $depositAddress,
                'token_address' => $tokenAddress,
                'chain_id' => $chainId,
            ]);

            if (isset($sweep)) {
                $sweep->markAsFailed($e->getMessage());
                $sweep->incrementRetry();
            }

            return false;
        }
    }

    protected function fundAddressWithGas(string $depositAddress, Chain $chain, Web3Service $web3): string
    {
        $balance = hexdec($web3->getBalance($depositAddress));
        $gasAmount = $chain->gas_amount_wei;

        if ($balance >= hexdec($gasAmount)) {
            return 'sufficient_balance';
        }

        $masterWallet = $this->walletService->getMasterWallet($chain->chain_id);
        $nonce = hexdec($web3->getTransactionCount($masterWallet['address']));
        $gasPrice = hexdec($web3->gasPrice());

        $transaction = [
            'nonce' => $nonce,
            'gasPrice' => $gasPrice,
            'gasLimit' => 21000,
            'to' => $depositAddress,
            'value' => $gasAmount,
            'data' => ''
        ];

        $signedTx = $this->signer->signTransaction($transaction, $masterWallet['privateKey'], $chain->chain_id);
        return $web3->sendRawTransaction($signedTx);
    }

    protected function sweepTokens(string $depositAddress, string $tokenAddress, Chain $chain, Web3Service $web3): string
    {
        $privateKey = $this->walletService->getPrivateKey($depositAddress, $chain->chain_id);

        // Get token balance
        $balanceHex = $web3->callContract(
            $tokenAddress,
            '0x70a08231' . str_pad(str_replace('0x', '', $depositAddress), 64, '0', STR_PAD_LEFT)
        );

        $balance = hexdec($balanceHex);

        if ($balance === 0) {
            throw new \Exception('No tokens to sweep');
        }

        // Build transfer data
        $transferData = '0xa9059cbb' .
            str_pad(str_replace('0x', '', $chain->hot_wallet_address), 64, '0', STR_PAD_LEFT) .
            str_pad(dechex($balance), 64, '0', STR_PAD_LEFT);

        $nonce = hexdec($web3->getTransactionCount($depositAddress));
        $gasPrice = hexdec($web3->gasPrice());

        $transaction = [
            'nonce' => $nonce,
            'gasPrice' => $gasPrice,
            'gasLimit' => $chain->gas_limit_token_transfer,
            'to' => $tokenAddress,
            'value' => '0x0',
            'data' => $transferData
        ];

        $signedTx = $this->signer->signTransaction($transaction, $privateKey, $chain->chain_id);
        return $web3->sendRawTransaction($signedTx);
    }

    protected function sweepRemainingGas(string $depositAddress, Chain $chain, Web3Service $web3): void
    {
        try {
            $balance = hexdec($web3->getBalance($depositAddress));
            $gasPrice = hexdec($web3->gasPrice());
            $gasCost = 21000 * $gasPrice;

            if ($balance > $gasCost * 1.1) {
                $privateKey = $this->walletService->getPrivateKey($depositAddress, $chain->chain_id);
                $masterWallet = $this->walletService->getMasterWallet($chain->chain_id);
                $nonce = hexdec($web3->getTransactionCount($depositAddress));

                $amountToSend = $balance - $gasCost;

                $transaction = [
                    'nonce' => $nonce,
                    'gasPrice' => $gasPrice,
                    'gasLimit' => 21000,
                    'to' => $masterWallet['address'],
                    'value' => '0x' . dechex($amountToSend),
                    'data' => ''
                ];

                $signedTx = $this->signer->signTransaction($transaction, $privateKey, $chain->chain_id);
                $web3->sendRawTransaction($signedTx);

                Log::info("Swept remaining gas: " . ($amountToSend / 1e18) . " {$chain->native_symbol}");
            }
        } catch (\Exception $e) {
            Log::warning("Could not sweep remaining gas: {$e->getMessage()}");
        }
    }
}
