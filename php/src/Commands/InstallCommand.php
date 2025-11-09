<?php

namespace Multicoin\TokenSweeper\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallCommand extends Command
{
    protected $signature = 'sweeper:install';
    protected $description = 'Install Token Sweeper package';

    public function handle(): int
    {
        $this->info('Installing Token Sweeper...');

        // Publish config
        $this->call('vendor:publish', [
            '--tag' => 'token-sweeper-config',
            '--force' => true
        ]);

        // Run migrations
        $this->call('migrate');

        // Publish seeders (optional)
        $this->call('vendor:publish', [
            '--tag' => 'token-sweeper-seeders',
            '--force' => true
        ]);

        $this->info('✅ Token Sweeper installed successfully!');
        $this->info('');
        $this->info('Next steps:');
        $this->info('1. Configure your chains and tokens in config/token-sweeper.php');
        $this->info('2. Run: php artisan sweeper:seed');
        $this->info('3. Start monitoring: php artisan sweeper:monitor');

        return self::SUCCESS;
    }
}
