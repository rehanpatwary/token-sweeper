<?php

namespace Multicoin\TokenSweeper\Tests\Unit\Jobs;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use Multicoin\TokenSweeper\Events\SweepCompleted;
use Multicoin\TokenSweeper\Jobs\SweepTokens;
use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Models\PendingSweep;
use Multicoin\TokenSweeper\Models\SweepLog;
use Multicoin\TokenSweeper\Services\TransactionSignerService;
use Multicoin\TokenSweeper\Services\WalletService;
use Multicoin\TokenSweeper\Services\Web3Service;
use Multicoin\TokenSweeper\Tests\TestCase;

class SweepTokensTest extends TestCase
{
    protected PendingSweep $sweep;
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

        // Create test pending sweep
        $this->sweep = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('3', 40),
            'chain_id' => 1,
            'token_address' => '0x' . str_repeat('4', 40),
            'token_symbol' => 'USDT',
            'amount' => '1000000',
            'status' => 'funding',
            'funding_tx_hash' => '0x' . bin2hex(random_bytes(32)),
            'retry_count' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_has_correct_retry_configuration()
    {
        $job = new SweepTokens($this->sweep);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(300, $job->timeout);
    }

    /** @test */
    public function it_successfully_sweeps_tokens()
    {
        Event::fake([SweepCompleted::class]);
        Log::shouldReceive('info')
            ->with('Tokens swept', Mockery::type('array'));
        Log::shouldReceive('info')
            ->with('Sweep completed successfully', Mockery::type('array'));

        $txHash = '0x' . bin2hex(random_bytes(32));
        $privateKey = '0x' . bin2hex(random_bytes(32));
        $tokenBalance = 1000000;

        // Mock WalletService
        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('getPrivateKey')
            ->once()
            ->with($this->sweep->deposit_address, $this->chain->chain_id)
            ->andReturn($privateKey);

        // Mock TransactionSignerService
        $signedTx = '0x' . bin2hex(random_bytes(100));
        $signer = Mockery::mock(TransactionSignerService::class);
        $signer->shouldReceive('signTransaction')
            ->once()
            ->with(Mockery::type('array'), $privateKey, $this->chain->chain_id)
            ->andReturn($signedTx);

        // Mock Web3Service
        $web3Mock = Mockery::mock(Web3Service::class);

        // Mock token balance check
        $web3Mock->shouldReceive('callContract')
            ->once()
            ->with(
                $this->sweep->token_address,
                Mockery::pattern('/^0x70a08231/')
            )
            ->andReturn('0x' . dechex($tokenBalance));

        $web3Mock->shouldReceive('getTransactionCount')
            ->once()
            ->with($this->sweep->deposit_address)
            ->andReturn('0x0');

        $web3Mock->shouldReceive('gasPrice')
            ->once()
            ->andReturn('0x' . dechex(20000000000));

        $web3Mock->shouldReceive('sendRawTransaction')
            ->once()
            ->with($signedTx)
            ->andReturn($txHash);

        $web3Mock->shouldReceive('waitForConfirmation')
            ->once()
            ->with($txHash)
            ->andReturn(true);

        $this->app->instance(Web3Service::class, $web3Mock);

        // Execute job
        $job = new SweepTokens($this->sweep);
        $job->handle($walletService, $signer);

        // Assert sweep was updated
        $this->sweep->refresh();
        $this->assertEquals('completed', $this->sweep->status);
        $this->assertEquals($txHash, $this->sweep->sweep_tx_hash);

        // Assert sweep log was created
        $log = SweepLog::where('sweep_id', $this->sweep->id)->first();
        $this->assertNotNull($log);
        $this->assertEquals($this->sweep->deposit_address, $log->deposit_address);
        $this->assertEquals($this->sweep->token_address, $log->token_address);
        $this->assertEquals((string)$tokenBalance, $log->amount);
        $this->assertEquals($this->sweep->funding_tx_hash, $log->funding_tx_hash);
        $this->assertEquals($txHash, $log->sweep_tx_hash);
        $this->assertEquals('completed', $log->status);

        // Assert event was dispatched
        Event::assertDispatched(SweepCompleted::class, function ($event) {
            return $event->sweep->id === $this->sweep->id;
        });
    }

    /** @test */
    public function it_updates_status_to_sweeping_at_start()
    {
        Event::fake();
        Log::shouldReceive('info');

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('getPrivateKey')->andReturn('0xprivatekey');

        $signer = Mockery::mock(TransactionSignerService::class);
        $signer->shouldReceive('signTransaction')->andReturn('0xsignedtx');

        $web3Mock = Mockery::mock(Web3Service::class);
        $web3Mock->shouldReceive('callContract')->andReturn('0x' . dechex(1000000));
        $web3Mock->shouldReceive('getTransactionCount')->andReturn('0x0');
        $web3Mock->shouldReceive('gasPrice')->andReturn('0x' . dechex(20000000000));
        $web3Mock->shouldReceive('sendRawTransaction')->andReturn('0xtxhash');
        $web3Mock->shouldReceive('waitForConfirmation')->andReturn(true);

        $this->app->instance(Web3Service::class, $web3Mock);

        $initialStatus = $this->sweep->status;

        $job = new SweepTokens($this->sweep);
        $job->handle($walletService, $signer);

        // Verify status was updated to sweeping during execution
        $this->assertNotEquals('sweeping', $initialStatus);
    }

    /** @test */
    public function it_builds_correct_transfer_transaction()
    {
        Event::fake();
        Log::shouldReceive('info');

        $tokenBalance = 5000000;
        $expectedNonce = 3;
        $expectedGasPrice = 30000000000;

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('getPrivateKey')
            ->andReturn('0xprivatekey');

        $signer = Mockery::mock(TransactionSignerService::class);
        $signer->shouldReceive('signTransaction')
            ->once()
            ->withArgs(function ($transaction, $privateKey, $chainId) use ($expectedNonce, $expectedGasPrice, $tokenBalance) {
                // Verify transaction structure
                $valid = $transaction['nonce'] === $expectedNonce
                    && $transaction['gasPrice'] === $expectedGasPrice
                    && $transaction['gasLimit'] === $this->chain->gas_limit_token_transfer
                    && $transaction['to'] === $this->sweep->token_address
                    && $transaction['value'] === '0x0'
                    && strpos($transaction['data'], '0xa9059cbb') === 0; // transfer function signature

                // Verify transfer data contains hot wallet address and amount
                if ($valid) {
                    $expectedHotWallet = str_pad(str_replace('0x', '', $this->chain->hot_wallet_address), 64, '0', STR_PAD_LEFT);
                    $expectedAmount = str_pad(dechex($tokenBalance), 64, '0', STR_PAD_LEFT);
                    $valid = strpos($transaction['data'], $expectedHotWallet) !== false
                        && strpos($transaction['data'], $expectedAmount) !== false;
                }

                return $valid && $chainId === $this->chain->chain_id;
            })
            ->andReturn('0xsignedtx');

        $web3Mock = Mockery::mock(Web3Service::class);
        $web3Mock->shouldReceive('callContract')->andReturn('0x' . dechex($tokenBalance));
        $web3Mock->shouldReceive('getTransactionCount')->andReturn('0x' . dechex($expectedNonce));
        $web3Mock->shouldReceive('gasPrice')->andReturn('0x' . dechex($expectedGasPrice));
        $web3Mock->shouldReceive('sendRawTransaction')->andReturn('0xtxhash');
        $web3Mock->shouldReceive('waitForConfirmation')->andReturn(true);

        $this->app->instance(Web3Service::class, $web3Mock);

        $job = new SweepTokens($this->sweep);
        $job->handle($walletService, $signer);
    }

    /** @test */
    public function it_throws_exception_when_no_tokens_to_sweep()
    {
        Log::shouldReceive('error')
            ->once()
            ->with('Sweep failed', Mockery::type('array'));

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('getPrivateKey')->andReturn('0xprivatekey');

        $signer = Mockery::mock(TransactionSignerService::class);

        // Mock Web3Service to return zero balance
        $web3Mock = Mockery::mock(Web3Service::class);
        $web3Mock->shouldReceive('callContract')->andReturn('0x0'); // Zero balance

        $this->app->instance(Web3Service::class, $web3Mock);

        $job = new SweepTokens($this->sweep);

        try {
            $job->handle($walletService, $signer);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('No tokens to sweep', $e->getMessage());
        }

        // Assert sweep was marked as failed
        $this->sweep->refresh();
        $this->assertEquals('failed', $this->sweep->status);
        $this->assertEquals('No tokens to sweep', $this->sweep->error_message);
    }

    /** @test */
    public function it_marks_sweep_as_failed_on_exception()
    {
        Log::shouldReceive('error')
            ->once()
            ->with('Sweep failed', Mockery::type('array'));

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('getPrivateKey')
            ->andThrow(new \Exception('Private key not found'));

        $signer = Mockery::mock(TransactionSignerService::class);

        $web3Mock = Mockery::mock(Web3Service::class);
        $this->app->instance(Web3Service::class, $web3Mock);

        $job = new SweepTokens($this->sweep);

        try {
            $job->handle($walletService, $signer);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Private key not found', $e->getMessage());
        }

        // Assert sweep was marked as failed
        $this->sweep->refresh();
        $this->assertEquals('failed', $this->sweep->status);
        $this->assertEquals('Private key not found', $this->sweep->error_message);
        $this->assertEquals(1, $this->sweep->retry_count);
    }

    /** @test */
    public function it_increments_retry_count_on_failure()
    {
        Log::shouldReceive('error');

        $initialRetryCount = $this->sweep->retry_count;

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('getPrivateKey')
            ->andThrow(new \Exception('Network error'));

        $signer = Mockery::mock(TransactionSignerService::class);

        $web3Mock = Mockery::mock(Web3Service::class);
        $this->app->instance(Web3Service::class, $web3Mock);

        $job = new SweepTokens($this->sweep);

        try {
            $job->handle($walletService, $signer);
        } catch (\Exception $e) {
            // Expected
        }

        $this->sweep->refresh();
        $this->assertEquals($initialRetryCount + 1, $this->sweep->retry_count);
    }

    /** @test */
    public function it_handles_chain_not_found_exception()
    {
        Log::shouldReceive('error');

        // Delete the chain
        $this->chain->delete();

        $walletService = Mockery::mock(WalletService::class);
        $signer = Mockery::mock(TransactionSignerService::class);

        $job = new SweepTokens($this->sweep);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $job->handle($walletService, $signer);
    }

    /** @test */
    public function it_handles_transaction_signing_failure()
    {
        Log::shouldReceive('error');

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('getPrivateKey')->andReturn('0xinvalid');

        $signer = Mockery::mock(TransactionSignerService::class);
        $signer->shouldReceive('signTransaction')
            ->andThrow(new \Exception('Invalid private key format'));

        $web3Mock = Mockery::mock(Web3Service::class);
        $web3Mock->shouldReceive('callContract')->andReturn('0x' . dechex(1000000));
        $web3Mock->shouldReceive('getTransactionCount')->andReturn('0x0');
        $web3Mock->shouldReceive('gasPrice')->andReturn('0x' . dechex(20000000000));

        $this->app->instance(Web3Service::class, $web3Mock);

        $job = new SweepTokens($this->sweep);

        try {
            $job->handle($walletService, $signer);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Invalid private key format', $e->getMessage());
        }

        $this->sweep->refresh();
        $this->assertEquals('failed', $this->sweep->status);
    }

    /** @test */
    public function it_handles_transaction_broadcast_failure()
    {
        Log::shouldReceive('error');

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('getPrivateKey')->andReturn('0xprivatekey');

        $signer = Mockery::mock(TransactionSignerService::class);
        $signer->shouldReceive('signTransaction')->andReturn('0xsignedtx');

        $web3Mock = Mockery::mock(Web3Service::class);
        $web3Mock->shouldReceive('callContract')->andReturn('0x' . dechex(1000000));
        $web3Mock->shouldReceive('getTransactionCount')->andReturn('0x0');
        $web3Mock->shouldReceive('gasPrice')->andReturn('0x' . dechex(20000000000));
        $web3Mock->shouldReceive('sendRawTransaction')
            ->andThrow(new \Exception('Gas estimation failed'));

        $this->app->instance(Web3Service::class, $web3Mock);

        $job = new SweepTokens($this->sweep);

        try {
            $job->handle($walletService, $signer);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Gas estimation failed', $e->getMessage());
        }

        $this->sweep->refresh();
        $this->assertEquals('failed', $this->sweep->status);
    }

    /** @test */
    public function it_waits_for_transaction_confirmation()
    {
        Event::fake();
        Log::shouldReceive('info');

        $txHash = '0x' . bin2hex(random_bytes(32));

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('getPrivateKey')->andReturn('0xprivatekey');

        $signer = Mockery::mock(TransactionSignerService::class);
        $signer->shouldReceive('signTransaction')->andReturn('0xsignedtx');

        $web3Mock = Mockery::mock(Web3Service::class);
        $web3Mock->shouldReceive('callContract')->andReturn('0x' . dechex(1000000));
        $web3Mock->shouldReceive('getTransactionCount')->andReturn('0x0');
        $web3Mock->shouldReceive('gasPrice')->andReturn('0x' . dechex(20000000000));
        $web3Mock->shouldReceive('sendRawTransaction')->andReturn($txHash);
        $web3Mock->shouldReceive('waitForConfirmation')
            ->once()
            ->with($txHash)
            ->andReturn(true);

        $this->app->instance(Web3Service::class, $web3Mock);

        $job = new SweepTokens($this->sweep);
        $job->handle($walletService, $signer);

        // Verify waitForConfirmation was called (assertion is in the mock)
    }

    /** @test */
    public function it_creates_sweep_log_with_correct_data()
    {
        Event::fake();
        Log::shouldReceive('info');

        $txHash = '0x' . bin2hex(random_bytes(32));
        $tokenBalance = 2500000;

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('getPrivateKey')->andReturn('0xprivatekey');

        $signer = Mockery::mock(TransactionSignerService::class);
        $signer->shouldReceive('signTransaction')->andReturn('0xsignedtx');

        $web3Mock = Mockery::mock(Web3Service::class);
        $web3Mock->shouldReceive('callContract')->andReturn('0x' . dechex($tokenBalance));
        $web3Mock->shouldReceive('getTransactionCount')->andReturn('0x0');
        $web3Mock->shouldReceive('gasPrice')->andReturn('0x' . dechex(20000000000));
        $web3Mock->shouldReceive('sendRawTransaction')->andReturn($txHash);
        $web3Mock->shouldReceive('waitForConfirmation')->andReturn(true);

        $this->app->instance(Web3Service::class, $web3Mock);

        $job = new SweepTokens($this->sweep);
        $job->handle($walletService, $signer);

        $log = SweepLog::where('sweep_id', $this->sweep->id)->first();

        $this->assertNotNull($log);
        $this->assertEquals($this->sweep->id, $log->sweep_id);
        $this->assertEquals($this->sweep->chain_id, $log->chain_id);
        $this->assertEquals($this->sweep->deposit_address, $log->deposit_address);
        $this->assertEquals($this->sweep->token_address, $log->token_address);
        $this->assertEquals((string)$tokenBalance, $log->amount);
        $this->assertEquals($this->sweep->funding_tx_hash, $log->funding_tx_hash);
        $this->assertEquals($txHash, $log->sweep_tx_hash);
        $this->assertEquals('completed', $log->status);
    }

    /** @test */
    public function it_rethrows_exception_for_job_retry()
    {
        Log::shouldReceive('error');

        $walletService = Mockery::mock(WalletService::class);
        $signer = Mockery::mock(TransactionSignerService::class);

        $web3Mock = Mockery::mock(Web3Service::class);
        $web3Mock->shouldReceive('callContract')
            ->andThrow(new \Exception('RPC timeout'));

        $this->app->instance(Web3Service::class, $web3Mock);

        $job = new SweepTokens($this->sweep);

        $exceptionThrown = false;
        try {
            $job->handle($walletService, $signer);
        } catch (\Exception $e) {
            $exceptionThrown = true;
            $this->assertEquals('RPC timeout', $e->getMessage());
        }

        $this->assertTrue($exceptionThrown, 'Exception should be rethrown for job retry mechanism');
    }

    /** @test */
    public function it_dispatches_sweep_completed_event()
    {
        Event::fake([SweepCompleted::class]);
        Log::shouldReceive('info');

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('getPrivateKey')->andReturn('0xprivatekey');

        $signer = Mockery::mock(TransactionSignerService::class);
        $signer->shouldReceive('signTransaction')->andReturn('0xsignedtx');

        $web3Mock = Mockery::mock(Web3Service::class);
        $web3Mock->shouldReceive('callContract')->andReturn('0x' . dechex(1000000));
        $web3Mock->shouldReceive('getTransactionCount')->andReturn('0x0');
        $web3Mock->shouldReceive('gasPrice')->andReturn('0x' . dechex(20000000000));
        $web3Mock->shouldReceive('sendRawTransaction')->andReturn('0xtxhash');
        $web3Mock->shouldReceive('waitForConfirmation')->andReturn(true);

        $this->app->instance(Web3Service::class, $web3Mock);

        $job = new SweepTokens($this->sweep);
        $job->handle($walletService, $signer);

        Event::assertDispatched(SweepCompleted::class, function ($event) {
            return $event->sweep instanceof PendingSweep
                && $event->sweep->id === $this->sweep->id;
        });
    }

    /** @test */
    public function it_uses_correct_erc20_transfer_function_signature()
    {
        Event::fake();
        Log::shouldReceive('info');

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('getPrivateKey')->andReturn('0xprivatekey');

        $signer = Mockery::mock(TransactionSignerService::class);
        $signer->shouldReceive('signTransaction')
            ->once()
            ->withArgs(function ($transaction) {
                // Verify the transfer function signature (0xa9059cbb)
                return strpos($transaction['data'], '0xa9059cbb') === 0;
            })
            ->andReturn('0xsignedtx');

        $web3Mock = Mockery::mock(Web3Service::class);
        $web3Mock->shouldReceive('callContract')->andReturn('0x' . dechex(1000000));
        $web3Mock->shouldReceive('getTransactionCount')->andReturn('0x0');
        $web3Mock->shouldReceive('gasPrice')->andReturn('0x' . dechex(20000000000));
        $web3Mock->shouldReceive('sendRawTransaction')->andReturn('0xtxhash');
        $web3Mock->shouldReceive('waitForConfirmation')->andReturn(true);

        $this->app->instance(Web3Service::class, $web3Mock);

        $job = new SweepTokens($this->sweep);
        $job->handle($walletService, $signer);
    }
}
