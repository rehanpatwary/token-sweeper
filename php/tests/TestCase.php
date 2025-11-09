<?php

namespace Multicoin\TokenSweeper\Tests;

use Multicoin\TokenSweeper\TokenSweeperServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }

    protected function getPackageProviders($app): array
    {
        return [
            TokenSweeperServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Setup encryption key for testing
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));

        // Setup token sweeper config
        $app['config']->set('token-sweeper.monitoring.check_interval', 5);
        $app['config']->set('token-sweeper.monitoring.block_confirmations', 1);
    }

    /**
     * Create a mock Web3 response
     */
    protected function mockWeb3Response($result)
    {
        return json_encode([
            'jsonrpc' => '2.0',
            'id' => time(),
            'result' => $result,
        ]);
    }

    /**
     * Create a test Ethereum address
     */
    protected function createTestAddress(): string
    {
        return '0x' . str_pad(dechex(rand(0, 999999)), 40, '0', STR_PAD_LEFT);
    }

    /**
     * Create a test transaction hash
     */
    protected function createTestTxHash(): string
    {
        return '0x' . bin2hex(random_bytes(32));
    }
}
