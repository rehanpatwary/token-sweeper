<?php

namespace Multicoin\TokenSweeper\Commands;

use Illuminate\Console\Command;
use Multicoin\TokenSweeper\Services\MonitorService;

class MonitorDepositsCommand extends Command
{
    protected $signature = 'sweeper:monitor';
    protected $description = 'Monitor blockchain for token deposits';

    public function handle(MonitorService $monitor): int
    {
        $this->info('🚀 Starting multi-chain token monitor...');

        try {
            $monitor->startMonitoring();
        } catch (\Exception $e) {
            $this->error("Monitor failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
