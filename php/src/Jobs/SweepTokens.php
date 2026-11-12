<?php

namespace Multicoin\TokenSweeper\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;
use Multicoin\TokenSweeper\Events\SweepCompleted;
use Multicoin\TokenSweeper\Exceptions\SweepRevertedException;
use Multicoin\TokenSweeper\Models\{Chain, PendingSweep, SweepLog};
use Multicoin\TokenSweeper\Services\{TransactionSignerService, WalletService};
use Multicoin\TokenSweeper\Support\ChainRpc;
use Multicoin\TokenSweeper\Support\Erc20;
use Multicoin\TokenSweeper\Support\Quantity;

class SweepTokens implements ShouldQueue
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

            $this->sweep->update(['status' => 'sweeping']);

            $privateKey = $walletService->getPrivateKey($this->sweep->deposit_address, $chain->chain_id);

            $balance = Quantity::hexToDecimal($web3->callContract(
                $this->sweep->token_address,
                Erc20::balanceOfData($this->sweep->deposit_address)
            ));

            if (Quantity::compare($balance, '0') === 0) {
                throw new \RuntimeException('No tokens to sweep');
            }

            $transaction = [
                'nonce' => Quantity::toInt($web3->getTransactionCount($this->sweep->deposit_address)),
                'gasPrice' => Quantity::toInt($web3->gasPrice()),
                'gasLimit' => $chain->gas_limit_token_transfer,
                'to' => $this->sweep->token_address,
                'value' => '0x0',
                'data' => Erc20::transferData($chain->hot_wallet_address, $balance),
            ];

            $signedTx = $signer->signTransaction($transaction, $privateKey, $chain->chain_id);
            $txHash = $web3->sendRawTransaction($signedTx);

            Log::info("Tokens swept", ['tx_hash' => $txHash]);

            $this->sweep->update(['sweep_tx_hash' => $txHash]);

            if ($web3->waitForConfirmation($txHash) !== true) {
                throw new SweepRevertedException("Sweep transaction reverted: {$txHash}");
            }

            $this->sweep->markAsCompleted($txHash);

            SweepLog::create([
                'sweep_id' => $this->sweep->id,
                'chain_id' => $this->sweep->chain_id,
                'deposit_address' => $this->sweep->deposit_address,
                'token_address' => $this->sweep->token_address,
                'amount' => $balance,
                'funding_tx_hash' => $this->sweep->funding_tx_hash,
                'sweep_tx_hash' => $txHash,
                'status' => 'completed',
            ]);

            event(new SweepCompleted($this->sweep));

            Log::info("Sweep completed successfully", ['sweep_id' => $this->sweep->id]);

        } catch (SweepRevertedException $e) {
            Log::error("Sweep failed", [
                'sweep_id' => $this->sweep->id,
                'error' => $e->getMessage(),
            ]);
            $this->sweep->markAsFailed($e->getMessage());
            $this->sweep->incrementRetry();
        } catch (\Throwable $e) {
            Log::error("Sweep failed", [
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
