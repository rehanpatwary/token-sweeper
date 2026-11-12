<?php

namespace Multicoin\TokenSweeper\Tests\Feature;

use Multicoin\TokenSweeper\Facades\TokenSweeper;
use Multicoin\TokenSweeper\Tests\TestCase;
use Multicoin\TokenSweeper\TokenSweeperManager;
use Multicoin\TokenSweeper\TokenSweeperServiceProvider;

class PackageBootstrapTest extends TestCase
{
    /** @test */
    public function it_registers_only_existing_migration_directories(): void
    {
        foreach ($this->app->make('migrator')->paths() as $path) {
            $this->assertDirectoryExists($path);
        }
    }

    /** @test */
    public function it_binds_the_token_sweeper_facade_to_a_manager(): void
    {
        $this->assertInstanceOf(TokenSweeperManager::class, TokenSweeper::getFacadeRoot());
    }

    /** @test */
    public function it_ships_the_config_file_the_provider_loads(): void
    {
        $provider = (new \ReflectionClass(TokenSweeperServiceProvider::class))->getFileName();
        $config = dirname($provider) . '/config/token-sweeper.php';

        $this->assertFileExists($config);

        $chains = config('token-sweeper.default_chains');
        $this->assertSame('2000000000000000', $chains[0]['gas_amount_wei']);
        $this->assertSame('100000000000000000', $chains[2]['gas_amount_wei']);
    }
}
