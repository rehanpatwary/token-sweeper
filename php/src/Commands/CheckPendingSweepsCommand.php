<?php

namespace Multicoin\TokenSweeper\Commands;

use Illuminate\Console\Command;
use Multicoin\TokenSweeper\Models\PendingSweep;

class CheckPendingSweepsCommand extends Command
{
    protected $signature = 'sweeper:pending {--status=pending}';
    protected $description = 'Check pending sweeps';

    public function handle(): int
    {
        $status = $this->option('status');

        $sweeps = PendingSweep::with('chain')
            ->where('status', $status)
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        $this->info("Pending Sweeps (Status: {$status}):");
        $this->table(
            ['ID', 'Chain', 'Address', 'Token', 'Status', 'Created'],
            $sweeps->map(fn($s) => [
                $s->id,
                $s->chain->name,
                substr($s->deposit_address, 0, 10) . '...',
                $s->token_symbol,
                $s->status,
                $s->created_at->diffForHumans(),
            ])
        );

        return self::SUCCESS;
    }
}
