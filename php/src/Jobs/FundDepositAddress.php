<?php

namespace Multicoin\TokenSweeper\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Multicoin\TokenSweeper\Models\{Chain, PendingSweep};
use Multicoin\TokenSweeper\Services\{Web3Service, WalletService, TransactionSignerService};
use Illuminate\Support\Facades\Log;

class FundDepositAddress implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 300;

    public function __construct(
        public PendingSweep $sweep
    ) {}

    public function handle(
        WalletService $walletService,
        TransactionSignerService $signer
    ): void {
        try {
            $chain = Chain::where('chain_id', $this->sweep->chain_id)->firstOrFail();
            $web3 = new Web3Service($chain->rpc_url, $chain->chain_id);

            // Check if already has enough gas
            $balance = hexdec($web3->getBalance($this->sweep->deposit_address));
            if ($balance >= hexdec($chain->gas_amount_wei)) {
                Log::info("Address already has sufficient gas");
                SweepTokens::dispatch($this->sweep);
                return;
            }

            // Fund address
            $masterWallet = $walletService->getMasterWallet($chain->chain_id);
            $nonce = hexdec($web3->getTransactionCount($masterWallet['address']));
            $gasPrice = hexdec($web3->gasPrice());

            $transaction = [
                'nonce' => $nonce,
                'gasPrice' => $gasPrice,
                'gasLimit' => 21000,
                'to' => $this->sweep->deposit_address,
                'value' => $chain->gas_amount_wei,
                'data' => ''
            ];

            $signedTx = $signer->signTransaction($transaction, $masterWallet['privateKey'], $chain->chain_id);
            $txHash = $web3->sendRawTransaction($signedTx);

            Log::info("Funded address with gas", ['tx_hash' => $txHash]);

            $this->sweep->update([
                'status' => 'funding',
                'funding_tx_hash' => $txHash
            ]);

            // Wait for confirmation
            $web3->waitForConfirmation($txHash);

            // Dispatch sweep job
            SweepTokens::dispatch($this->sweep);

        } catch (\Exception $e) {
            Log::error("Funding failed", [
                'sweep_id' => $this->sweep->id,
                'error' => $e->getMessage()
            ]);

            $this->sweep->markAsFailed($e->getMessage());
            $this->sweep->incrementRetry();

            throw $e;
        }
    }
}
