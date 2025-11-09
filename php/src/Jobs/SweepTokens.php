<?php

namespace Multicoin\TokenSweeper\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Multicoin\TokenSweeper\Models\{Chain, PendingSweep, SweepLog};
use Multicoin\TokenSweeper\Services\{Web3Service, WalletService, TransactionSignerService};
use Multicoin\TokenSweeper\Events\SweepCompleted;
use Illuminate\Support\Facades\Log;

class SweepTokens implements ShouldQueue
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

            $this->sweep->update(['status' => 'sweeping']);

            // Get private key
            $privateKey = $walletService->getPrivateKey($this->sweep->deposit_address, $chain->chain_id);

            // Get token balance
            $balanceHex = $web3->callContract(
                $this->sweep->token_address,
                '0x70a08231' . str_pad(str_replace('0x', '', $this->sweep->deposit_address), 64, '0', STR_PAD_LEFT)
            );

            $balance = hexdec($balanceHex);

            if ($balance === 0) {
                throw new \Exception('No tokens to sweep');
            }

            // Build transfer data
            $transferData = '0xa9059cbb' .
                str_pad(str_replace('0x', '', $chain->hot_wallet_address), 64, '0', STR_PAD_LEFT) .
                str_pad(dechex($balance), 64, '0', STR_PAD_LEFT);

            $nonce = hexdec($web3->getTransactionCount($this->sweep->deposit_address));
            $gasPrice = hexdec($web3->gasPrice());

            $transaction = [
                'nonce' => $nonce,
                'gasPrice' => $gasPrice,
                'gasLimit' => $chain->gas_limit_token_transfer,
                'to' => $this->sweep->token_address,
                'value' => '0x0',
                'data' => $transferData
            ];

            $signedTx = $signer->signTransaction($transaction, $privateKey, $chain->chain_id);
            $txHash = $web3->sendRawTransaction($signedTx);

            Log::info("Tokens swept", ['tx_hash' => $txHash]);

            $this->sweep->update(['sweep_tx_hash' => $txHash]);

            // Wait for confirmation
            $web3->waitForConfirmation($txHash);

            // Mark as completed
            $this->sweep->markAsCompleted($txHash);

            // Create log
            SweepLog::create([
                'sweep_id' => $this->sweep->id,
                'chain_id' => $this->sweep->chain_id,
                'deposit_address' => $this->sweep->deposit_address,
                'token_address' => $this->sweep->token_address,
                'amount' => (string)$balance,
                'funding_tx_hash' => $this->sweep->funding_tx_hash,
                'sweep_tx_hash' => $txHash,
                'status' => 'completed',
            ]);

            event(new SweepCompleted($this->sweep));

            Log::info("Sweep completed successfully", ['sweep_id' => $this->sweep->id]);

        } catch (\Exception $e) {
            Log::error("Sweep failed", [
                'sweep_id' => $this->sweep->id,
                'error' => $e->getMessage()
            ]);

            $this->sweep->markAsFailed($e->getMessage());
            $this->sweep->incrementRetry();

            throw $e;
        }
    }
}
