<?php

namespace Multicoin\TokenSweeper\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Multicoin\TokenSweeper\Models\{Chain, PendingSweep};
use Multicoin\TokenSweeper\Services\{TransactionSignerService, WalletService};
use Multicoin\TokenSweeper\Support\ChainRpc;
use Multicoin\TokenSweeper\Support\Quantity;

class FundDepositAddress implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 3;
    public $timeout = 300;

    public function __construct(
        public PendingSweep $sweep
    ) {
    }

    public function handle(
        WalletService $walletService,
        TransactionSignerService $signer
    ): void {
        try {
            $chain = Chain::where('chain_id', $this->sweep->chain_id)->firstOrFail();
            $web3 = ChainRpc::connect($chain);

            if (Quantity::compare($web3->getBalance($this->sweep->deposit_address), $chain->gas_amount_wei) >= 0) {
                Log::info("Address already has sufficient gas");
                SweepTokens::dispatch($this->sweep);
                return;
            }

            $lock = Cache::lock('sweeper-master-nonce-' . $chain->chain_id, 30);
            $lock->block(15, function () use ($chain, $web3, $walletService, $signer) {
                $masterWallet = $walletService->getMasterWallet($chain->chain_id);
                $transaction = [
                    'nonce' => Quantity::toInt($web3->getTransactionCount($masterWallet['address'])),
                    'gasPrice' => Quantity::toInt($web3->gasPrice()),
                    'gasLimit' => 21000,
                    'to' => $this->sweep->deposit_address,
                    'value' => $chain->gas_amount_wei,
                    'data' => '',
                ];

                $signedTx = $signer->signTransaction($transaction, $masterWallet['privateKey'], $chain->chain_id);
                $txHash = $web3->sendRawTransaction($signedTx);

                Log::info("Funded address with gas", ['tx_hash' => $txHash]);

                $this->sweep->update([
                    'status' => 'funding',
                    'funding_tx_hash' => $txHash,
                ]);

                if ($web3->waitForConfirmation($txHash) !== true) {
                    throw new \RuntimeException("Funding transaction reverted: {$txHash}");
                }
            });

            SweepTokens::dispatch($this->sweep);

        } catch (\Throwable $e) {
            Log::error("Funding failed", [
                'sweep_id' => $this->sweep->id,
                'error' => $e->getMessage(),
            ]);

            $this->sweep->markAsFailed($e->getMessage());
            $this->sweep->incrementRetry();

            throw $e;
        }
    }
}
