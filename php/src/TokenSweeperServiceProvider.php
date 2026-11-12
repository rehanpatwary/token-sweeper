<?php

namespace Multicoin\TokenSweeper;

use Illuminate\Support\ServiceProvider;
use Multicoin\TokenSweeper\Commands\{
    CheckBalanceCommand,
    CheckPendingSweepsCommand,
    GenerateDepositAddressCommand,
    InstallCommand,
    MonitorDepositsCommand,
    ProcessSweepCommand,
    SeedChainsCommand
};
use Multicoin\TokenSweeper\Services\SweeperService;
use Multicoin\TokenSweeper\Services\WalletService;

class TokenSweeperServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/token-sweeper.php',
            'token-sweeper'
        );

        $this->app->singleton('token-sweeper', function ($app) {
            return new TokenSweeperManager(
                $app->make(WalletService::class),
                $app->make(SweeperService::class),
            );
        });
    }

    public function boot(): void
    {
        // Publish config
        $this->publishes([
            __DIR__ . '/config/token-sweeper.php' => config_path('token-sweeper.php'),
        ], 'token-sweeper-config');

        // Load migrations
        $this->loadMigrationsFrom(dirname(__DIR__) . '/database/migrations');

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
