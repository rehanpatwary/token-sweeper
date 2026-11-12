<?php

namespace Multicoin\TokenSweeper\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;
use Multicoin\TokenSweeper\Exceptions\SweepRevertedException;
use Multicoin\TokenSweeper\Models\{Chain, PendingSweep};
use Multicoin\TokenSweeper\Services\{TransactionSignerService, WalletService};
use Multicoin\TokenSweeper\Support\ChainRpc;
use Multicoin\TokenSweeper\Support\MasterNonceLock;
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

            $txHash = MasterNonceLock::acquire((int) $chain->chain_id)->block(15, function () use ($chain, $web3, $walletService, $signer) {
                if (Quantity::compare($web3->getBalance($this->sweep->deposit_address), $chain->gas_amount_wei) >= 0) {
                    return null;
                }

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
                $hash = $web3->sendRawTransaction($signedTx);

                Log::info("Funded address with gas", ['tx_hash' => $hash]);

                $this->sweep->update([
                    'status' => 'funding',
                    'funding_tx_hash' => $hash,
                ]);

                return $hash;
            });

            if ($txHash === null) {
                Log::info("Address already has sufficient gas");
                SweepTokens::dispatch($this->sweep);
                return;
            }

            if ($web3->waitForConfirmation($txHash) !== true) {
                throw new SweepRevertedException("Funding transaction reverted: {$txHash}");
            }

            SweepTokens::dispatch($this->sweep);

        } catch (SweepRevertedException $e) {
            Log::error("Funding failed", [
                'sweep_id' => $this->sweep->id,
                'error' => $e->getMessage(),
            ]);
            $this->sweep->markAsFailed($e->getMessage());
            $this->sweep->incrementRetry();
        } catch (\Throwable $e) {
            Log::error("Funding failed", [
                'sweep_id' => $this->sweep->id,
                'error' => $e->getMessage(),
            ]);

            $this->sweep->markAsFailed($e->getMessage());

            throw $e;
        }
    }

    public function failed(\Throwable $e): void
    {
        $this->sweep->incrementRetry();
    }
}
