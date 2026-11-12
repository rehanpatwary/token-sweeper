<?php

namespace Multicoin\TokenSweeper\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Illuminate\Support\Facades\Log;
use Multicoin\TokenSweeper\Models\PendingSweep;
use Multicoin\TokenSweeper\Services\SweeperService;

class CheckPendingSweeps implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function handle(SweeperService $sweeper): void
    {
        $maxRetries = config('token-sweeper.monitoring.max_retry_attempts');
        $retryDelay = config('token-sweeper.monitoring.retry_delay');
        $maxRetries = is_numeric($maxRetries) ? (int) $maxRetries : 3;
        $retryDelay = is_numeric($retryDelay) ? (int) $retryDelay : 60;

        // Get failed sweeps that can be retried. retry_delay is minutes.
        $failedSweeps = PendingSweep::failed()
            ->where('retry_count', '<', $maxRetries)
            ->where('updated_at', '<', now()->subMinutes($retryDelay))
            ->get();

        $failure = null;

        foreach ($failedSweeps as $sweep) {
            Log::info("Retrying failed sweep", ['sweep_id' => $sweep->id]);

            try {
                $sweeper->processSweep(
                    $sweep->deposit_address,
                    $sweep->token_address,
                    $sweep->chain_id
                );
            } catch (\Throwable $e) {
                $failure ??= $e;
            }
        }

        if ($failure !== null) {
            throw $failure;
        }
    }
}
