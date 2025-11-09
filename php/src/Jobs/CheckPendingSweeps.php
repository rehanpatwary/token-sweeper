<?php

namespace Multicoin\TokenSweeper\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue, SerializesModels};
use Multicoin\TokenSweeper\Models\PendingSweep;
use Multicoin\TokenSweeper\Services\SweeperService;
use Illuminate\Support\Facades\Log;

class CheckPendingSweeps implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(SweeperService $sweeper): void
    {
        $maxRetries = config('token-sweeper.monitoring.max_retry_attempts', 3);

        // Get failed sweeps that can be retried
        $failedSweeps = PendingSweep::failed()
            ->where('retry_count', '<', $maxRetries)
            ->where('updated_at', '<', now()->subMinutes(config('token-sweeper.monitoring.retry_delay', 60)))
            ->get();

        foreach ($failedSweeps as $sweep) {
            Log::info("Retrying failed sweep", ['sweep_id' => $sweep->id]);

            $sweeper->processSweep(
                $sweep->deposit_address,
                $sweep->token_address,
                $sweep->chain_id
            );
        }
    }
}
