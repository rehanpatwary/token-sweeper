<?php

namespace Multicoin\TokenSweeper\Tests\Unit\Jobs;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Multicoin\TokenSweeper\Jobs\FundDepositAddress;
use Multicoin\TokenSweeper\Jobs\SweepTokens;
use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Models\PendingSweep;
use Multicoin\TokenSweeper\Services\TransactionSignerService;
use Multicoin\TokenSweeper\Services\WalletService;
use Multicoin\TokenSweeper\Services\Web3Service;
use Multicoin\TokenSweeper\Tests\TestCase;

class FundDepositAddressTest extends TestCase
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
            'gas_amount_wei' => '0x' . dechex(100000000000000000), // 0.1 ETH
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
            'status' => 'pending',
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
        $job = new FundDepositAddress($this->sweep);

        $this->assertEquals(3, $job->tries);
        $this->assertEquals(300, $job->timeout);
    }

    /** @test */
    public function it_dispatches_sweep_tokens_if_address_already_has_sufficient_gas()
    {
        Queue::fake();
        Log::shouldReceive('info')
            ->once()
            ->with('Address already has sufficient gas');

        // Mock services
        $walletService = Mockery::mock(WalletService::class);
        $signer = Mockery::mock(TransactionSignerService::class);

        // Mock Web3Service to return sufficient balance
        $web3Mock = Mockery::mock(Web3Service::class);
        $web3Mock->shouldReceive('getBalance')
            ->once()
            ->with($this->sweep->deposit_address)
            ->andReturn('0x' . dechex(200000000000000000)); // 0.2 ETH (more than required)

        // Mock the Web3Service constructor
        $this->mock(Web3Service::class, function ($mock) use ($web3Mock) {
            $mock->shouldReceive('getBalance')
                ->andReturn('0x' . dechex(200000000000000000));
        });

        // Create a partial mock of the job to override Web3Service instantiation
        $job = Mockery::mock(FundDepositAddress::class, [$this->sweep])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        // Use reflection to set the web3 instance
        $reflection = new \ReflectionClass($job);
        $handleMethod = $reflection->getMethod('handle');

        // Execute with mocked dependencies
        $this->app->instance(Web3Service::class, $web3Mock);

        $job->handle($walletService, $signer);

        // Assert SweepTokens was dispatched
        Queue::assertPushed(SweepTokens::class, function ($job) {
            return $job->sweep->id === $this->sweep->id;
        });
    }

    /** @test */
    public function it_successfully_funds_deposit_address_and_dispatches_sweep()
    {
        Queue::fake();
        Log::shouldReceive('info')
            ->with('Funded address with gas', Mockery::type('array'));

        $txHash = '0x' . bin2hex(random_bytes(32));
        $masterWallet = [
            'address' => $this->chain->master_wallet_address,
            'privateKey' => '0x' . bin2hex(random_bytes(32)),
        ];

        // Mock WalletService
        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('getMasterWallet')
            ->once()
            ->with($this->chain->chain_id)
            ->andReturn($masterWallet);

        // Mock TransactionSignerService
        $signedTx = '0x' . bin2hex(random_bytes(100));
        $signer = Mockery::mock(TransactionSignerService::class);
        $signer->shouldReceive('signTransaction')
            ->once()
            ->with(Mockery::type('array'), $masterWallet['privateKey'], $this->chain->chain_id)
            ->andReturn($signedTx);

        // Mock Web3Service
        $web3Mock = Mockery::mock(Web3Service::class);
        $web3Mock->shouldReceive('getBalance')
            ->once()
            ->with($this->sweep->deposit_address)
            ->andReturn('0x0'); // No balance initially

        $web3Mock->shouldReceive('getTransactionCount')
            ->once()
            ->with($masterWallet['address'])
            ->andReturn('0x5'); // Nonce 5

        $web3Mock->shouldReceive('gasPrice')
            ->once()
            ->andReturn('0x' . dechex(20000000000)); // 20 Gwei

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
        $job = new FundDepositAddress($this->sweep);
        $job->handle($walletService, $signer);

        // Assert sweep was updated
        $this->sweep->refresh();
        $this->assertEquals('funding', $this->sweep->status);
        $this->assertEquals($txHash, $this->sweep->funding_tx_hash);

        // Assert SweepTokens was dispatched
        Queue::assertPushed(SweepTokens::class, function ($job) {
            return $job->sweep->id === $this->sweep->id;
        });
    }

    /** @test */
    public function it_builds_correct_funding_transaction()
    {
        Queue::fake();
        Log::shouldReceive('info')->with('Funded address with gas', Mockery::type('array'));

        $masterWallet = [
            'address' => $this->chain->master_wallet_address,
            'privateKey' => '0x' . bin2hex(random_bytes(32)),
        ];

        $expectedNonce = 10;
        $expectedGasPrice = 25000000000; // 25 Gwei

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('getMasterWallet')
            ->andReturn($masterWallet);

        $signer = Mockery::mock(TransactionSignerService::class);
        $signer->shouldReceive('signTransaction')
            ->once()
            ->withArgs(function ($transaction, $privateKey, $chainId) use ($expectedNonce, $expectedGasPrice) {
                return $transaction['nonce'] === $expectedNonce
                    && $transaction['gasPrice'] === $expectedGasPrice
                    && $transaction['gasLimit'] === 21000
                    && $transaction['to'] === $this->sweep->deposit_address
                    && $transaction['value'] === $this->chain->gas_amount_wei
                    && $transaction['data'] === ''
                    && $privateKey === $masterWallet['privateKey']
                    && $chainId === $this->chain->chain_id;
            })
            ->andReturn('0xsignedtx');

        $web3Mock = Mockery::mock(Web3Service::class);
        $web3Mock->shouldReceive('getBalance')->andReturn('0x0');
        $web3Mock->shouldReceive('getTransactionCount')->andReturn('0x' . dechex($expectedNonce));
        $web3Mock->shouldReceive('gasPrice')->andReturn('0x' . dechex($expectedGasPrice));
        $web3Mock->shouldReceive('sendRawTransaction')->andReturn('0xtxhash');
        $web3Mock->shouldReceive('waitForConfirmation')->andReturn(true);

        $this->app->instance(Web3Service::class, $web3Mock);

        $job = new FundDepositAddress($this->sweep);
        $job->handle($walletService, $signer);
    }

    /** @test */
    public function it_marks_sweep_as_failed_on_exception()
    {
        Log::shouldReceive('error')
            ->once()
            ->with('Funding failed', Mockery::type('array'));

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('getMasterWallet')
            ->andThrow(new \Exception('Wallet not found'));

        $signer = Mockery::mock(TransactionSignerService::class);

        $web3Mock = Mockery::mock(Web3Service::class);
        $web3Mock->shouldReceive('getBalance')->andReturn('0x0');

        $this->app->instance(Web3Service::class, $web3Mock);

        $job = new FundDepositAddress($this->sweep);

        try {
            $job->handle($walletService, $signer);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Wallet not found', $e->getMessage());
        }

        // Assert sweep was marked as failed
        $this->sweep->refresh();
        $this->assertEquals('failed', $this->sweep->status);
        $this->assertEquals('Wallet not found', $this->sweep->error_message);
        $this->assertEquals(1, $this->sweep->retry_count);
    }

    /** @test */
    public function it_increments_retry_count_on_failure()
    {
        Log::shouldReceive('error');

        $initialRetryCount = $this->sweep->retry_count;

        $walletService = Mockery::mock(WalletService::class);
        $signer = Mockery::mock(TransactionSignerService::class);

        $web3Mock = Mockery::mock(Web3Service::class);
        $web3Mock->shouldReceive('getBalance')
            ->andThrow(new \Exception('RPC connection failed'));

        $this->app->instance(Web3Service::class, $web3Mock);

        $job = new FundDepositAddress($this->sweep);

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

        $job = new FundDepositAddress($this->sweep);

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $job->handle($walletService, $signer);
    }

    /** @test */
    public function it_handles_transaction_signing_failure()
    {
        Log::shouldReceive('error');

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('getMasterWallet')
            ->andReturn([
                'address' => '0x' . str_repeat('1', 40),
                'privateKey' => '0xinvalid',
            ]);

        $signer = Mockery::mock(TransactionSignerService::class);
        $signer->shouldReceive('signTransaction')
            ->andThrow(new \Exception('Invalid private key'));

        $web3Mock = Mockery::mock(Web3Service::class);
        $web3Mock->shouldReceive('getBalance')->andReturn('0x0');
        $web3Mock->shouldReceive('getTransactionCount')->andReturn('0x5');
        $web3Mock->shouldReceive('gasPrice')->andReturn('0x' . dechex(20000000000));

        $this->app->instance(Web3Service::class, $web3Mock);

        $job = new FundDepositAddress($this->sweep);

        try {
            $job->handle($walletService, $signer);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Invalid private key', $e->getMessage());
        }

        $this->sweep->refresh();
        $this->assertEquals('failed', $this->sweep->status);
        $this->assertStringContainsString('Invalid private key', $this->sweep->error_message);
    }

    /** @test */
    public function it_handles_transaction_broadcast_failure()
    {
        Log::shouldReceive('error');

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('getMasterWallet')
            ->andReturn([
                'address' => '0x' . str_repeat('1', 40),
                'privateKey' => '0x' . bin2hex(random_bytes(32)),
            ]);

        $signer = Mockery::mock(TransactionSignerService::class);
        $signer->shouldReceive('signTransaction')->andReturn('0xsignedtx');

        $web3Mock = Mockery::mock(Web3Service::class);
        $web3Mock->shouldReceive('getBalance')->andReturn('0x0');
        $web3Mock->shouldReceive('getTransactionCount')->andReturn('0x5');
        $web3Mock->shouldReceive('gasPrice')->andReturn('0x' . dechex(20000000000));
        $web3Mock->shouldReceive('sendRawTransaction')
            ->andThrow(new \Exception('Insufficient funds'));

        $this->app->instance(Web3Service::class, $web3Mock);

        $job = new FundDepositAddress($this->sweep);

        try {
            $job->handle($walletService, $signer);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertEquals('Insufficient funds', $e->getMessage());
        }

        $this->sweep->refresh();
        $this->assertEquals('failed', $this->sweep->status);
    }

    /** @test */
    public function it_rethrows_exception_for_job_retry()
    {
        Log::shouldReceive('error');

        $walletService = Mockery::mock(WalletService::class);
        $signer = Mockery::mock(TransactionSignerService::class);

        $web3Mock = Mockery::mock(Web3Service::class);
        $web3Mock->shouldReceive('getBalance')
            ->andThrow(new \Exception('Network error'));

        $this->app->instance(Web3Service::class, $web3Mock);

        $job = new FundDepositAddress($this->sweep);

        $exceptionThrown = false;
        try {
            $job->handle($walletService, $signer);
        } catch (\Exception $e) {
            $exceptionThrown = true;
            $this->assertEquals('Network error', $e->getMessage());
        }

        $this->assertTrue($exceptionThrown, 'Exception should be rethrown for job retry mechanism');
    }

    /** @test */
    public function it_waits_for_transaction_confirmation()
    {
        Queue::fake();
        Log::shouldReceive('info');

        $txHash = '0x' . bin2hex(random_bytes(32));

        $walletService = Mockery::mock(WalletService::class);
        $walletService->shouldReceive('getMasterWallet')
            ->andReturn([
                'address' => '0x' . str_repeat('1', 40),
                'privateKey' => '0x' . bin2hex(random_bytes(32)),
            ]);

        $signer = Mockery::mock(TransactionSignerService::class);
        $signer->shouldReceive('signTransaction')->andReturn('0xsignedtx');

        $web3Mock = Mockery::mock(Web3Service::class);
        $web3Mock->shouldReceive('getBalance')->andReturn('0x0');
        $web3Mock->shouldReceive('getTransactionCount')->andReturn('0x5');
        $web3Mock->shouldReceive('gasPrice')->andReturn('0x' . dechex(20000000000));
        $web3Mock->shouldReceive('sendRawTransaction')->andReturn($txHash);
        $web3Mock->shouldReceive('waitForConfirmation')
            ->once()
            ->with($txHash)
            ->andReturn(true);

        $this->app->instance(Web3Service::class, $web3Mock);

        $job = new FundDepositAddress($this->sweep);
        $job->handle($walletService, $signer);

        // Verify waitForConfirmation was called (assertion is in the mock)
    }
}
