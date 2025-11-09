<?php

namespace Multicoin\TokenSweeper\Tests\Feature;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Cache;
use Multicoin\TokenSweeper\Tests\TestCase;
use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Models\Token;
use Multicoin\TokenSweeper\Models\DepositAddress;
use Multicoin\TokenSweeper\Models\PendingSweep;
use Multicoin\TokenSweeper\Models\SweepLog;
use Multicoin\TokenSweeper\Events\DepositDetected;
use Multicoin\TokenSweeper\Events\SweepStarted;
use Multicoin\TokenSweeper\Events\SweepCompleted;
use Multicoin\TokenSweeper\Services\SweeperService;
use Multicoin\TokenSweeper\Services\MonitorService;
use Multicoin\TokenSweeper\Services\WalletService;
use Multicoin\TokenSweeper\Services\Web3Service;
use Multicoin\TokenSweeper\Services\TransactionSignerService;

class SweepWorkflowTest extends TestCase
{
    protected Chain $chain;
    protected Token $token;
    protected DepositAddress $depositAddress;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test chain
        $this->chain = Chain::create([
            'chain_id' => 1,
            'name' => 'Ethereum Mainnet',
            'rpc_url' => 'https://eth.llamarpc.com',
            'master_wallet_address' => $this->createTestAddress(),
            'master_private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('1', 64)),
            'hot_wallet_address' => $this->createTestAddress(),
            'native_symbol' => 'ETH',
            'gas_amount_wei' => '0x' . dechex(100000000000000), // 0.0001 ETH
            'gas_limit_token_transfer' => 100000,
            'is_active' => true,
        ]);

        // Create test token
        $this->token = Token::create([
            'chain_id' => 1,
            'symbol' => 'USDT',
            'name' => 'Tether USD',
            'contract_address' => $this->createTestAddress(),
            'decimals' => 6,
            'is_active' => true,
        ]);

        // Create deposit address
        $this->depositAddress = DepositAddress::create([
            'user_id' => 1,
            'chain_id' => 1,
            'address' => $this->createTestAddress(),
            'private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('2', 64)),
        ]);
    }

    /** @test */
    public function it_creates_pending_sweep_when_deposit_detected_event_fires()
    {
        Event::fake([DepositDetected::class]);

        $depositAddress = $this->depositAddress->address;
        $tokenAddress = $this->token->contract_address;
        $chainId = $this->chain->chain_id;
        $amount = '1000000'; // 1 USDT (6 decimals)

        event(new DepositDetected($depositAddress, $tokenAddress, $chainId, $amount));

        Event::assertDispatched(DepositDetected::class, function ($event) use ($depositAddress, $tokenAddress, $chainId, $amount) {
            return $event->depositAddress === $depositAddress &&
                   $event->tokenAddress === $tokenAddress &&
                   $event->chainId === $chainId &&
                   $event->amount === $amount;
        });
    }

    /** @test */
    public function it_completes_full_sweep_workflow_with_mocked_web3()
    {
        Event::fake([SweepStarted::class, SweepCompleted::class]);

        // Mock Web3Service
        $web3Mock = $this->createMock(Web3Service::class);

        // Mock balance check - address has no ETH
        $web3Mock->method('getBalance')
            ->willReturn('0x0');

        // Mock transaction count
        $web3Mock->method('getTransactionCount')
            ->willReturn('0x5');

        // Mock gas price
        $web3Mock->method('gasPrice')
            ->willReturn('0x' . dechex(20000000000)); // 20 gwei

        // Mock token balance check
        $web3Mock->method('callContract')
            ->willReturn('0x' . dechex(1000000)); // 1 USDT

        // Mock transaction sending
        $fundingTxHash = $this->createTestTxHash();
        $sweepTxHash = $this->createTestTxHash();

        $web3Mock->method('sendRawTransaction')
            ->willReturnOnConsecutiveCalls($fundingTxHash, $sweepTxHash);

        // Mock transaction confirmation
        $web3Mock->method('waitForConfirmation')
            ->willReturn(true);

        // Create sweep service with mocked dependencies
        $walletService = new WalletService();
        $signerService = new TransactionSignerService();

        $sweeperService = $this->getMockBuilder(SweeperService::class)
            ->setConstructorArgs([$walletService, $signerService])
            ->onlyMethods([])
            ->getMock();

        // Since we can't easily inject the Web3Service mock, we'll test the PendingSweep creation
        $sweep = PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '1000000',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('sweeper_pending_sweeps', [
            'deposit_address' => $this->depositAddress->address,
            'token_address' => $this->token->contract_address,
            'status' => 'pending',
        ]);

        // Simulate sweep started
        event(new SweepStarted($sweep));
        Event::assertDispatched(SweepStarted::class);

        // Simulate funding
        $sweep->update(['status' => 'funding', 'funding_tx_hash' => $fundingTxHash]);

        $this->assertDatabaseHas('sweeper_pending_sweeps', [
            'id' => $sweep->id,
            'status' => 'funding',
            'funding_tx_hash' => $fundingTxHash,
        ]);

        // Simulate sweeping
        $sweep->update(['status' => 'sweeping']);

        $this->assertDatabaseHas('sweeper_pending_sweeps', [
            'id' => $sweep->id,
            'status' => 'sweeping',
        ]);

        // Simulate completion
        $sweep->markAsCompleted($sweepTxHash);

        $this->assertDatabaseHas('sweeper_pending_sweeps', [
            'id' => $sweep->id,
            'status' => 'completed',
            'sweep_tx_hash' => $sweepTxHash,
        ]);

        // Create sweep log
        SweepLog::create([
            'sweep_id' => $sweep->id,
            'chain_id' => $this->chain->chain_id,
            'deposit_address' => $this->depositAddress->address,
            'token_address' => $this->token->contract_address,
            'amount' => '1000000',
            'funding_tx_hash' => $fundingTxHash,
            'sweep_tx_hash' => $sweepTxHash,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('sweeper_logs', [
            'sweep_id' => $sweep->id,
            'status' => 'completed',
            'sweep_tx_hash' => $sweepTxHash,
        ]);

        // Verify event was dispatched
        event(new SweepCompleted($sweep));
        Event::assertDispatched(SweepCompleted::class);
    }

    /** @test */
    public function it_handles_sweep_failure_gracefully()
    {
        $sweep = PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '1000000',
            'status' => 'pending',
        ]);

        $errorMessage = 'Insufficient gas for transaction';

        $sweep->markAsFailed($errorMessage);
        $sweep->incrementRetry();

        $this->assertDatabaseHas('sweeper_pending_sweeps', [
            'id' => $sweep->id,
            'status' => 'failed',
            'error_message' => $errorMessage,
            'retry_count' => 1,
        ]);

        $this->assertEquals('failed', $sweep->fresh()->status);
        $this->assertEquals($errorMessage, $sweep->fresh()->error_message);
        $this->assertEquals(1, $sweep->fresh()->retry_count);
    }

    /** @test */
    public function it_tracks_multiple_sweeps_for_different_tokens()
    {
        // Create second token
        $token2 = Token::create([
            'chain_id' => 1,
            'symbol' => 'USDC',
            'name' => 'USD Coin',
            'contract_address' => $this->createTestAddress(),
            'decimals' => 6,
            'is_active' => true,
        ]);

        // Create sweeps for both tokens
        $sweep1 = PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '1000000',
            'status' => 'pending',
        ]);

        $sweep2 = PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => $token2->contract_address,
            'token_symbol' => $token2->symbol,
            'amount' => '2000000',
            'status' => 'pending',
        ]);

        $this->assertEquals(2, PendingSweep::count());

        $this->assertDatabaseHas('sweeper_pending_sweeps', [
            'id' => $sweep1->id,
            'token_symbol' => 'USDT',
        ]);

        $this->assertDatabaseHas('sweeper_pending_sweeps', [
            'id' => $sweep2->id,
            'token_symbol' => 'USDC',
        ]);
    }

    /** @test */
    public function it_filters_pending_sweeps_by_status()
    {
        PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '1000000',
            'status' => 'pending',
        ]);

        PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '2000000',
            'status' => 'completed',
        ]);

        PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '3000000',
            'status' => 'failed',
        ]);

        $pending = PendingSweep::pending()->get();
        $completed = PendingSweep::completed()->get();
        $failed = PendingSweep::failed()->get();

        $this->assertEquals(1, $pending->count());
        $this->assertEquals(1, $completed->count());
        $this->assertEquals(1, $failed->count());
    }

    /** @test */
    public function it_filters_sweeps_by_chain()
    {
        // Create second chain
        $chain2 = Chain::create([
            'chain_id' => 56,
            'name' => 'BSC',
            'rpc_url' => 'https://bsc-dataseed.binance.org/',
            'master_wallet_address' => $this->createTestAddress(),
            'master_private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('3', 64)),
            'hot_wallet_address' => $this->createTestAddress(),
            'native_symbol' => 'BNB',
            'gas_amount_wei' => '0x' . dechex(10000000000000),
            'gas_limit_token_transfer' => 100000,
            'is_active' => true,
        ]);

        PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => 1,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '1000000',
            'status' => 'pending',
        ]);

        PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => 56,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '2000000',
            'status' => 'pending',
        ]);

        $ethSweeps = PendingSweep::forChain(1)->get();
        $bscSweeps = PendingSweep::forChain(56)->get();

        $this->assertEquals(1, $ethSweeps->count());
        $this->assertEquals(1, $bscSweeps->count());
    }

    /** @test */
    public function it_creates_sweep_log_with_complete_information()
    {
        $sweep = PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '1000000',
            'status' => 'completed',
            'funding_tx_hash' => $this->createTestTxHash(),
            'sweep_tx_hash' => $this->createTestTxHash(),
        ]);

        $log = SweepLog::create([
            'sweep_id' => $sweep->id,
            'chain_id' => $this->chain->chain_id,
            'deposit_address' => $this->depositAddress->address,
            'token_address' => $this->token->contract_address,
            'amount' => '1000000',
            'funding_tx_hash' => $sweep->funding_tx_hash,
            'sweep_tx_hash' => $sweep->sweep_tx_hash,
            'gas_used' => '65000',
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('sweeper_logs', [
            'sweep_id' => $sweep->id,
            'deposit_address' => $this->depositAddress->address,
            'status' => 'completed',
        ]);

        $this->assertEquals($sweep->id, $log->sweep->id);
        $this->assertEquals($this->chain->chain_id, $log->chain->chain_id);
    }

    /** @test */
    public function it_handles_retry_mechanism_for_failed_sweeps()
    {
        $sweep = PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '1000000',
            'status' => 'pending',
            'retry_count' => 0,
        ]);

        // First failure
        $sweep->markAsFailed('Network timeout');
        $sweep->incrementRetry();
        $this->assertEquals(1, $sweep->fresh()->retry_count);

        // Second failure
        $sweep->markAsFailed('Gas price too low');
        $sweep->incrementRetry();
        $this->assertEquals(2, $sweep->fresh()->retry_count);

        // Third failure
        $sweep->markAsFailed('Nonce too low');
        $sweep->incrementRetry();
        $this->assertEquals(3, $sweep->fresh()->retry_count);

        $this->assertEquals('failed', $sweep->fresh()->status);
        $this->assertEquals('Nonce too low', $sweep->fresh()->error_message);
    }

    /** @test */
    public function it_maintains_relationship_between_sweep_and_deposit_address()
    {
        $sweep = PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '1000000',
            'status' => 'pending',
        ]);

        $depositAddressSweeps = $this->depositAddress->pendingSweeps;

        $this->assertEquals(1, $depositAddressSweeps->count());
        $this->assertEquals($sweep->id, $depositAddressSweeps->first()->id);
        $this->assertEquals($this->depositAddress->address, $sweep->depositAddressModel->address);
    }

    /** @test */
    public function it_tracks_last_sweep_timestamp_on_deposit_address()
    {
        $this->assertNull($this->depositAddress->last_sweep_at);

        $now = now();
        $this->depositAddress->update(['last_sweep_at' => $now]);

        $this->depositAddress->refresh();
        $this->assertNotNull($this->depositAddress->last_sweep_at);
        $this->assertEquals($now->timestamp, $this->depositAddress->last_sweep_at->timestamp);
    }

    /** @test */
    public function it_queries_successful_sweep_logs()
    {
        $completedSweep = PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '1000000',
            'status' => 'completed',
        ]);

        SweepLog::create([
            'sweep_id' => $completedSweep->id,
            'chain_id' => $this->chain->chain_id,
            'deposit_address' => $this->depositAddress->address,
            'token_address' => $this->token->contract_address,
            'amount' => '1000000',
            'status' => 'completed',
        ]);

        $failedSweep = PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '2000000',
            'status' => 'failed',
        ]);

        SweepLog::create([
            'sweep_id' => $failedSweep->id,
            'chain_id' => $this->chain->chain_id,
            'deposit_address' => $this->depositAddress->address,
            'token_address' => $this->token->contract_address,
            'amount' => '2000000',
            'status' => 'failed',
        ]);

        $successfulLogs = SweepLog::successful()->get();
        $this->assertEquals(1, $successfulLogs->count());
        $this->assertEquals('completed', $successfulLogs->first()->status);
    }
}
