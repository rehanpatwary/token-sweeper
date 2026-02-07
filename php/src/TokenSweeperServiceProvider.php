<?php

namespace Multicoin\TokenSweeper;

use Illuminate\Support\ServiceProvider;
use Multicoin\TokenSweeper\Commands\{
    GenerateDepositAddressCommand,
    MonitorDepositsCommand,
    ProcessSweepCommand,
    CheckBalanceCommand,
    CheckPendingSweepsCommand,
    InstallCommand,
    SeedChainsCommand
};

class TokenSweeperServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/Config/token-sweeper.php',
            'token-sweeper'
        );
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/Config/token-sweeper.php' => config_path('token-sweeper.php'),
        ], 'token-sweeper-config');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Load routes
        $this->loadRoutesFrom(__DIR__ . '/routes/api.php');

        // Register commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                GenerateDepositAddressCommand::class,
                MonitorDepositsCommand::class,
                ProcessSweepCommand::class,
                CheckBalanceCommand::class,
                CheckPendingSweepsCommand::class,
                InstallCommand::class,
                SeedChainsCommand::class,
            ]);
        }
    }
}
