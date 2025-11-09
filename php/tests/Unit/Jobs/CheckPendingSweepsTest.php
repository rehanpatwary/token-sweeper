<?php

namespace Multicoin\TokenSweeper\Tests\Unit\Jobs;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Mockery;
use Multicoin\TokenSweeper\Jobs\CheckPendingSweeps;
use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Models\PendingSweep;
use Multicoin\TokenSweeper\Services\SweeperService;
use Multicoin\TokenSweeper\Tests\TestCase;

class CheckPendingSweepsTest extends TestCase
{
    protected Chain $chain;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test chain
        $this->chain = Chain::create([
            'chain_id' => 1,
            'name' => 'Ethereum',
            'rpc_url' => 'https://eth.example.com',
            'master_wallet_address' => '0x' . str_repeat('1', 40),
            'master_private_key_encrypted' => Crypt::encryptString('0x' . bin2hex(random_bytes(32))),
            'hot_wallet_address' => '0x' . str_repeat('2', 40),
            'native_symbol' => 'ETH',
            'gas_amount_wei' => '0x' . dechex(100000000000000000),
            'gas_limit_token_transfer' => 100000,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_processes_failed_sweeps_within_retry_limit()
    {
        Config::set('token-sweeper.monitoring.max_retry_attempts', 3);
        Config::set('token-sweeper.monitoring.retry_delay', 60);

        // Create failed sweeps with different retry counts
        $sweep1 = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('3', 40),
            'chain_id' => 1,
            'token_address' => '0x' . str_repeat('4', 40),
            'token_symbol' => 'USDT',
            'amount' => '1000000',
            'status' => 'failed',
            'retry_count' => 1,
            'updated_at' => now()->subMinutes(61), // Old enough to retry
        ]);

        $sweep2 = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('5', 40),
            'chain_id' => 1,
            'token_address' => '0x' . str_repeat('4', 40),
            'token_symbol' => 'USDT',
            'amount' => '2000000',
            'status' => 'failed',
            'retry_count' => 2,
            'updated_at' => now()->subMinutes(61),
        ]);

        Log::shouldReceive('info')
            ->twice()
            ->with('Retrying failed sweep', Mockery::type('array'));

        $sweeperService = Mockery::mock(SweeperService::class);
        $sweeperService->shouldReceive('processSweep')
            ->once()
            ->with($sweep1->deposit_address, $sweep1->token_address, $sweep1->chain_id);

        $sweeperService->shouldReceive('processSweep')
            ->once()
            ->with($sweep2->deposit_address, $sweep2->token_address, $sweep2->chain_id);

        $job = new CheckPendingSweeps();
        $job->handle($sweeperService);
    }

    /** @test */
    public function it_ignores_sweeps_that_exceeded_max_retry_attempts()
    {
        Config::set('token-sweeper.monitoring.max_retry_attempts', 3);
        Config::set('token-sweeper.monitoring.retry_delay', 60);

        // Create sweep that exceeded retry limit
        $sweep = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('3', 40),
            'chain_id' => 1,
            'token_address' => '0x' . str_repeat('4', 40),
            'token_symbol' => 'USDT',
            'amount' => '1000000',
            'status' => 'failed',
            'retry_count' => 3, // Equal to max retry attempts
            'updated_at' => now()->subMinutes(61),
        ]);

        $sweeperService = Mockery::mock(SweeperService::class);
        $sweeperService->shouldReceive('processSweep')->never();

        $job = new CheckPendingSweeps();
        $job->handle($sweeperService);
    }

    /** @test */
    public function it_ignores_sweeps_that_are_too_recent()
    {
        Config::set('token-sweeper.monitoring.max_retry_attempts', 3);
        Config::set('token-sweeper.monitoring.retry_delay', 60);

        // Create recently failed sweep
        $sweep = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('3', 40),
            'chain_id' => 1,
            'token_address' => '0x' . str_repeat('4', 40),
            'token_symbol' => 'USDT',
            'amount' => '1000000',
            'status' => 'failed',
            'retry_count' => 1,
            'updated_at' => now()->subMinutes(30), // Too recent (< 60 minutes)
        ]);

        $sweeperService = Mockery::mock(SweeperService::class);
        $sweeperService->shouldReceive('processSweep')->never();

        $job = new CheckPendingSweeps();
        $job->handle($sweeperService);
    }

    /** @test */
    public function it_ignores_non_failed_sweeps()
    {
        Config::set('token-sweeper.monitoring.max_retry_attempts', 3);
        Config::set('token-sweeper.monitoring.retry_delay', 60);

        // Create pending sweep
        $pendingSweep = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('3', 40),
            'chain_id' => 1,
            'token_address' => '0x' . str_repeat('4', 40),
            'token_symbol' => 'USDT',
            'amount' => '1000000',
            'status' => 'pending',
            'retry_count' => 0,
            'updated_at' => now()->subMinutes(61),
        ]);

        // Create completed sweep
        $completedSweep = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('5', 40),
            'chain_id' => 1,
            'token_address' => '0x' . str_repeat('4', 40),
            'token_symbol' => 'USDT',
            'amount' => '2000000',
            'status' => 'completed',
            'retry_count' => 0,
            'updated_at' => now()->subMinutes(61),
        ]);

        $sweeperService = Mockery::mock(SweeperService::class);
        $sweeperService->shouldReceive('processSweep')->never();

        $job = new CheckPendingSweeps();
        $job->handle($sweeperService);
    }

    /** @test */
    public function it_processes_multiple_failed_sweeps()
    {
        Config::set('token-sweeper.monitoring.max_retry_attempts', 3);
        Config::set('token-sweeper.monitoring.retry_delay', 60);

        // Create multiple failed sweeps
        $sweeps = [];
        for ($i = 0; $i < 5; $i++) {
            $sweeps[] = PendingSweep::create([
                'deposit_address' => '0x' . str_pad(dechex($i), 40, '0', STR_PAD_LEFT),
                'chain_id' => 1,
                'token_address' => '0x' . str_repeat('4', 40),
                'token_symbol' => 'USDT',
                'amount' => '1000000',
                'status' => 'failed',
                'retry_count' => $i % 2, // Alternate between 0 and 1
                'updated_at' => now()->subMinutes(61),
            ]);
        }

        Log::shouldReceive('info')
            ->times(5)
            ->with('Retrying failed sweep', Mockery::type('array'));

        $sweeperService = Mockery::mock(SweeperService::class);
        $sweeperService->shouldReceive('processSweep')
            ->times(5);

        $job = new CheckPendingSweeps();
        $job->handle($sweeperService);
    }

    /** @test */
    public function it_respects_custom_retry_delay_configuration()
    {
        Config::set('token-sweeper.monitoring.max_retry_attempts', 3);
        Config::set('token-sweeper.monitoring.retry_delay', 120); // 2 hours

        // Create sweep that's 90 minutes old (should not be retried)
        $sweep1 = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('3', 40),
            'chain_id' => 1,
            'token_address' => '0x' . str_repeat('4', 40),
            'token_symbol' => 'USDT',
            'amount' => '1000000',
            'status' => 'failed',
            'retry_count' => 1,
            'updated_at' => now()->subMinutes(90),
        ]);

        // Create sweep that's 150 minutes old (should be retried)
        $sweep2 = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('5', 40),
            'chain_id' => 1,
            'token_address' => '0x' . str_repeat('4', 40),
            'token_symbol' => 'USDT',
            'amount' => '2000000',
            'status' => 'failed',
            'retry_count' => 1,
            'updated_at' => now()->subMinutes(150),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('Retrying failed sweep', Mockery::hasKey('sweep_id'));

        $sweeperService = Mockery::mock(SweeperService::class);
        $sweeperService->shouldReceive('processSweep')
            ->once()
            ->with($sweep2->deposit_address, $sweep2->token_address, $sweep2->chain_id);

        $job = new CheckPendingSweeps();
        $job->handle($sweeperService);
    }

    /** @test */
    public function it_uses_default_config_values_when_not_set()
    {
        // Clear any existing config
        Config::set('token-sweeper.monitoring.max_retry_attempts', null);
        Config::set('token-sweeper.monitoring.retry_delay', null);

        // Create failed sweep
        $sweep = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('3', 40),
            'chain_id' => 1,
            'token_address' => '0x' . str_repeat('4', 40),
            'token_symbol' => 'USDT',
            'amount' => '1000000',
            'status' => 'failed',
            'retry_count' => 2,
            'updated_at' => now()->subMinutes(61),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('Retrying failed sweep', Mockery::type('array'));

        $sweeperService = Mockery::mock(SweeperService::class);
        $sweeperService->shouldReceive('processSweep')
            ->once()
            ->with($sweep->deposit_address, $sweep->token_address, $sweep->chain_id);

        $job = new CheckPendingSweeps();
        $job->handle($sweeperService);
    }

    /** @test */
    public function it_passes_correct_parameters_to_sweeper_service()
    {
        Config::set('token-sweeper.monitoring.max_retry_attempts', 3);
        Config::set('token-sweeper.monitoring.retry_delay', 60);

        $depositAddress = '0x' . str_repeat('3', 40);
        $tokenAddress = '0x' . str_repeat('4', 40);
        $chainId = 1;

        $sweep = PendingSweep::create([
            'deposit_address' => $depositAddress,
            'chain_id' => $chainId,
            'token_address' => $tokenAddress,
            'token_symbol' => 'USDT',
            'amount' => '1000000',
            'status' => 'failed',
            'retry_count' => 1,
            'updated_at' => now()->subMinutes(61),
        ]);

        Log::shouldReceive('info');

        $sweeperService = Mockery::mock(SweeperService::class);
        $sweeperService->shouldReceive('processSweep')
            ->once()
            ->withArgs(function ($address, $token, $chain) use ($depositAddress, $tokenAddress, $chainId) {
                return $address === $depositAddress
                    && $token === $tokenAddress
                    && $chain === $chainId;
            });

        $job = new CheckPendingSweeps();
        $job->handle($sweeperService);
    }

    /** @test */
    public function it_logs_retry_attempts_with_sweep_id()
    {
        Config::set('token-sweeper.monitoring.max_retry_attempts', 3);
        Config::set('token-sweeper.monitoring.retry_delay', 60);

        $sweep = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('3', 40),
            'chain_id' => 1,
            'token_address' => '0x' . str_repeat('4', 40),
            'token_symbol' => 'USDT',
            'amount' => '1000000',
            'status' => 'failed',
            'retry_count' => 1,
            'updated_at' => now()->subMinutes(61),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) use ($sweep) {
                return $message === 'Retrying failed sweep'
                    && isset($context['sweep_id'])
                    && $context['sweep_id'] === $sweep->id;
            });

        $sweeperService = Mockery::mock(SweeperService::class);
        $sweeperService->shouldReceive('processSweep');

        $job = new CheckPendingSweeps();
        $job->handle($sweeperService);
    }

    /** @test */
    public function it_handles_empty_failed_sweeps_list()
    {
        Config::set('token-sweeper.monitoring.max_retry_attempts', 3);
        Config::set('token-sweeper.monitoring.retry_delay', 60);

        // No failed sweeps in database
        $sweeperService = Mockery::mock(SweeperService::class);
        $sweeperService->shouldReceive('processSweep')->never();

        $job = new CheckPendingSweeps();
        $job->handle($sweeperService);

        // Should complete without errors
        $this->assertTrue(true);
    }

    /** @test */
    public function it_processes_sweeps_from_different_chains()
    {
        Config::set('token-sweeper.monitoring.max_retry_attempts', 3);
        Config::set('token-sweeper.monitoring.retry_delay', 60);

        // Create another chain
        $chain2 = Chain::create([
            'chain_id' => 56,
            'name' => 'BSC',
            'rpc_url' => 'https://bsc.example.com',
            'master_wallet_address' => '0x' . str_repeat('a', 40),
            'master_private_key_encrypted' => Crypt::encryptString('0x' . bin2hex(random_bytes(32))),
            'hot_wallet_address' => '0x' . str_repeat('b', 40),
            'native_symbol' => 'BNB',
            'gas_amount_wei' => '0x' . dechex(50000000000000000),
            'gas_limit_token_transfer' => 100000,
            'is_active' => true,
        ]);

        // Create failed sweeps on different chains
        $sweep1 = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('3', 40),
            'chain_id' => 1,
            'token_address' => '0x' . str_repeat('4', 40),
            'token_symbol' => 'USDT',
            'amount' => '1000000',
            'status' => 'failed',
            'retry_count' => 1,
            'updated_at' => now()->subMinutes(61),
        ]);

        $sweep2 = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('5', 40),
            'chain_id' => 56,
            'token_address' => '0x' . str_repeat('6', 40),
            'token_symbol' => 'BUSD',
            'amount' => '2000000',
            'status' => 'failed',
            'retry_count' => 1,
            'updated_at' => now()->subMinutes(61),
        ]);

        Log::shouldReceive('info')->twice();

        $sweeperService = Mockery::mock(SweeperService::class);
        $sweeperService->shouldReceive('processSweep')
            ->once()
            ->with($sweep1->deposit_address, $sweep1->token_address, $sweep1->chain_id);

        $sweeperService->shouldReceive('processSweep')
            ->once()
            ->with($sweep2->deposit_address, $sweep2->token_address, $sweep2->chain_id);

        $job = new CheckPendingSweeps();
        $job->handle($sweeperService);
    }

    /** @test */
    public function it_handles_sweeper_service_exceptions_gracefully()
    {
        Config::set('token-sweeper.monitoring.max_retry_attempts', 3);
        Config::set('token-sweeper.monitoring.retry_delay', 60);

        $sweep1 = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('3', 40),
            'chain_id' => 1,
            'token_address' => '0x' . str_repeat('4', 40),
            'token_symbol' => 'USDT',
            'amount' => '1000000',
            'status' => 'failed',
            'retry_count' => 1,
            'updated_at' => now()->subMinutes(61),
        ]);

        $sweep2 = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('5', 40),
            'chain_id' => 1,
            'token_address' => '0x' . str_repeat('4', 40),
            'token_symbol' => 'USDT',
            'amount' => '2000000',
            'status' => 'failed',
            'retry_count' => 1,
            'updated_at' => now()->subMinutes(61),
        ]);

        Log::shouldReceive('info')->twice();

        $sweeperService = Mockery::mock(SweeperService::class);

        // First sweep throws exception
        $sweeperService->shouldReceive('processSweep')
            ->once()
            ->with($sweep1->deposit_address, $sweep1->token_address, $sweep1->chain_id)
            ->andThrow(new \Exception('Service error'));

        // Second sweep should still be processed
        $sweeperService->shouldReceive('processSweep')
            ->once()
            ->with($sweep2->deposit_address, $sweep2->token_address, $sweep2->chain_id);

        $job = new CheckPendingSweeps();

        // The job should handle the exception and continue
        try {
            $job->handle($sweeperService);
        } catch (\Exception $e) {
            // Exception from first sweep should propagate
            $this->assertEquals('Service error', $e->getMessage());
        }
    }

    /** @test */
    public function it_queries_sweeps_with_correct_conditions()
    {
        Config::set('token-sweeper.monitoring.max_retry_attempts', 3);
        Config::set('token-sweeper.monitoring.retry_delay', 60);

        // Create various sweeps to test query conditions
        // Should be included: failed, retry_count < 3, updated > 60 min ago
        $includedSweep = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('1', 40),
            'chain_id' => 1,
            'token_address' => '0x' . str_repeat('4', 40),
            'token_symbol' => 'USDT',
            'amount' => '1000000',
            'status' => 'failed',
            'retry_count' => 2,
            'updated_at' => now()->subMinutes(61),
        ]);

        // Should be excluded: retry_count >= max
        $excludedByRetryCount = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('2', 40),
            'chain_id' => 1,
            'token_address' => '0x' . str_repeat('4', 40),
            'token_symbol' => 'USDT',
            'amount' => '1000000',
            'status' => 'failed',
            'retry_count' => 3,
            'updated_at' => now()->subMinutes(61),
        ]);

        // Should be excluded: too recent
        $excludedByTime = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('3', 40),
            'chain_id' => 1,
            'token_address' => '0x' . str_repeat('4', 40),
            'token_symbol' => 'USDT',
            'amount' => '1000000',
            'status' => 'failed',
            'retry_count' => 1,
            'updated_at' => now()->subMinutes(30),
        ]);

        // Should be excluded: not failed status
        $excludedByStatus = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('4', 40),
            'chain_id' => 1,
            'token_address' => '0x' . str_repeat('4', 40),
            'token_symbol' => 'USDT',
            'amount' => '1000000',
            'status' => 'pending',
            'retry_count' => 1,
            'updated_at' => now()->subMinutes(61),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->with('Retrying failed sweep', Mockery::hasKey('sweep_id'));

        $sweeperService = Mockery::mock(SweeperService::class);
        $sweeperService->shouldReceive('processSweep')
            ->once()
            ->with($includedSweep->deposit_address, $includedSweep->token_address, $includedSweep->chain_id);

        $job = new CheckPendingSweeps();
        $job->handle($sweeperService);
    }
}
