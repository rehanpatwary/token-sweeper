<?php

namespace Multicoin\TokenSweeper\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Models\DepositAddress;
use Multicoin\TokenSweeper\Models\PendingSweep;
use Multicoin\TokenSweeper\Models\SweepLog;
use Multicoin\TokenSweeper\Tests\TestCase;

class PendingSweepTest extends TestCase
{
    use RefreshDatabase;

    protected Chain $chain;
    protected DepositAddress $depositAddress;
    protected PendingSweep $pendingSweep;

    protected function setUp(): void
    {
        parent::setUp();

        $this->chain = Chain::create([
            'chain_id' => 1,
            'name' => 'Ethereum Mainnet',
            'rpc_url' => 'https://mainnet.infura.io/v3/test',
            'master_wallet_address' => '0x' . str_repeat('1', 40),
            'master_private_key_encrypted' => 'encrypted_key',
            'hot_wallet_address' => '0x' . str_repeat('2', 40),
            'native_symbol' => 'ETH',
            'gas_amount_wei' => '21000000000000000',
            'gas_limit_token_transfer' => 100000,
            'is_active' => true,
        ]);

        $this->depositAddress = DepositAddress::create([
            'user_id' => 1,
            'chain_id' => $this->chain->chain_id,
            'address' => '0x' . str_repeat('a', 40),
            'private_key_encrypted' => 'encrypted_private_key',
        ]);

        $this->pendingSweep = PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => '0xdac17f958d2ee523a2206206994597c13d831ec7',
            'token_symbol' => 'USDT',
            'amount' => '1000000',
            'status' => 'pending',
            'retry_count' => 0,
        ]);
    }

    /** @test */
    public function it_can_create_a_pending_sweep()
    {
        $this->assertInstanceOf(PendingSweep::class, $this->pendingSweep);
        $this->assertDatabaseHas('sweeper_pending_sweeps', [
            'deposit_address' => $this->depositAddress->address,
            'token_symbol' => 'USDT',
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function it_has_correct_table_name()
    {
        $this->assertEquals('sweeper_pending_sweeps', $this->pendingSweep->getTable());
    }

    /** @test */
    public function it_has_fillable_attributes()
    {
        $fillable = [
            'deposit_address',
            'chain_id',
            'token_address',
            'token_symbol',
            'amount',
            'status',
            'funding_tx_hash',
            'sweep_tx_hash',
            'error_message',
            'retry_count',
        ];

        $this->assertEquals($fillable, $this->pendingSweep->getFillable());
    }

    /** @test */
    public function it_casts_chain_id_to_integer()
    {
        $this->assertIsInt($this->pendingSweep->chain_id);
        $this->assertEquals(1, $this->pendingSweep->chain_id);
    }

    /** @test */
    public function it_casts_retry_count_to_integer()
    {
        $this->assertIsInt($this->pendingSweep->retry_count);
        $this->assertEquals(0, $this->pendingSweep->retry_count);
    }

    /** @test */
    public function it_belongs_to_a_chain()
    {
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $this->pendingSweep->chain());
        $this->assertInstanceOf(Chain::class, $this->pendingSweep->chain);
        $this->assertEquals($this->chain->id, $this->pendingSweep->chain->id);
        $this->assertEquals('Ethereum Mainnet', $this->pendingSweep->chain->name);
    }

    /** @test */
    public function it_belongs_to_a_deposit_address()
    {
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $this->pendingSweep->depositAddressModel());
        $this->assertInstanceOf(DepositAddress::class, $this->pendingSweep->depositAddressModel);
        $this->assertEquals($this->depositAddress->id, $this->pendingSweep->depositAddressModel->id);
        $this->assertEquals($this->depositAddress->address, $this->pendingSweep->depositAddressModel->address);
    }

    /** @test */
    public function it_has_log_relationship()
    {
        $log = SweepLog::create([
            'sweep_id' => $this->pendingSweep->id,
            'chain_id' => $this->chain->chain_id,
            'deposit_address' => $this->depositAddress->address,
            'token_address' => '0xdac17f958d2ee523a2206206994597c13d831ec7',
            'amount' => '1000000',
            'funding_tx_hash' => '0x' . str_repeat('f', 64),
            'sweep_tx_hash' => '0x' . str_repeat('s', 64),
            'gas_used' => '21000',
            'status' => 'completed',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasOne::class, $this->pendingSweep->log());
        $this->assertInstanceOf(SweepLog::class, $this->pendingSweep->log);
        $this->assertEquals($log->id, $this->pendingSweep->log->id);
    }

    /** @test */
    public function scope_pending_returns_only_pending_sweeps()
    {
        PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => '0x' . str_repeat('b', 40),
            'token_symbol' => 'USDC',
            'amount' => '2000000',
            'status' => 'completed',
            'retry_count' => 0,
        ]);

        $pendingSweeps = PendingSweep::pending()->get();

        $this->assertCount(1, $pendingSweeps);
        $this->assertEquals('pending', $pendingSweeps->first()->status);
        $this->assertEquals('USDT', $pendingSweeps->first()->token_symbol);
    }

    /** @test */
    public function scope_failed_returns_only_failed_sweeps()
    {
        PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => '0x' . str_repeat('c', 40),
            'token_symbol' => 'DAI',
            'amount' => '3000000',
            'status' => 'failed',
            'error_message' => 'Insufficient gas',
            'retry_count' => 3,
        ]);

        $failedSweeps = PendingSweep::failed()->get();

        $this->assertCount(1, $failedSweeps);
        $this->assertEquals('failed', $failedSweeps->first()->status);
        $this->assertEquals('DAI', $failedSweeps->first()->token_symbol);
    }

    /** @test */
    public function scope_completed_returns_only_completed_sweeps()
    {
        PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => '0x' . str_repeat('d', 40),
            'token_symbol' => 'USDC',
            'amount' => '4000000',
            'status' => 'completed',
            'sweep_tx_hash' => '0x' . str_repeat('e', 64),
            'retry_count' => 0,
        ]);

        $completedSweeps = PendingSweep::completed()->get();

        $this->assertCount(1, $completedSweeps);
        $this->assertEquals('completed', $completedSweeps->first()->status);
        $this->assertEquals('USDC', $completedSweeps->first()->token_symbol);
    }

    /** @test */
    public function scope_for_chain_filters_by_chain_id()
    {
        $anotherChain = Chain::create([
            'chain_id' => 56,
            'name' => 'BSC',
            'rpc_url' => 'https://bsc-dataseed.binance.org/',
            'master_wallet_address' => '0x' . str_repeat('5', 40),
            'master_private_key_encrypted' => 'encrypted',
            'hot_wallet_address' => '0x' . str_repeat('6', 40),
            'native_symbol' => 'BNB',
            'gas_amount_wei' => '1000000000000000',
            'gas_limit_token_transfer' => 100000,
            'is_active' => true,
        ]);

        PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('f', 40),
            'chain_id' => $anotherChain->chain_id,
            'token_address' => '0x' . str_repeat('g', 40),
            'token_symbol' => 'BUSD',
            'amount' => '5000000',
            'status' => 'pending',
            'retry_count' => 0,
        ]);

        $ethereumSweeps = PendingSweep::forChain(1)->get();
        $bscSweeps = PendingSweep::forChain(56)->get();

        $this->assertCount(1, $ethereumSweeps);
        $this->assertEquals('USDT', $ethereumSweeps->first()->token_symbol);

        $this->assertCount(1, $bscSweeps);
        $this->assertEquals('BUSD', $bscSweeps->first()->token_symbol);
    }

    /** @test */
    public function it_can_combine_scopes()
    {
        PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => '0x' . str_repeat('h', 40),
            'token_symbol' => 'USDC',
            'amount' => '6000000',
            'status' => 'completed',
            'retry_count' => 0,
        ]);

        $pendingChainSweeps = PendingSweep::pending()->forChain(1)->get();

        $this->assertCount(1, $pendingChainSweeps);
        $this->assertEquals('USDT', $pendingChainSweeps->first()->token_symbol);
        $this->assertEquals('pending', $pendingChainSweeps->first()->status);
    }

    /** @test */
    public function mark_as_completed_updates_status_and_tx_hash()
    {
        $txHash = '0x' . str_repeat('a', 64);
        $this->pendingSweep->markAsCompleted($txHash);

        $this->assertEquals('completed', $this->pendingSweep->status);
        $this->assertEquals($txHash, $this->pendingSweep->sweep_tx_hash);

        $this->assertDatabaseHas('sweeper_pending_sweeps', [
            'id' => $this->pendingSweep->id,
            'status' => 'completed',
            'sweep_tx_hash' => $txHash,
        ]);
    }

    /** @test */
    public function mark_as_failed_updates_status_and_error_message()
    {
        $errorMessage = 'Insufficient funds for gas';
        $this->pendingSweep->markAsFailed($errorMessage);

        $this->assertEquals('failed', $this->pendingSweep->status);
        $this->assertEquals($errorMessage, $this->pendingSweep->error_message);

        $this->assertDatabaseHas('sweeper_pending_sweeps', [
            'id' => $this->pendingSweep->id,
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /** @test */
    public function increment_retry_increases_retry_count()
    {
        $this->assertEquals(0, $this->pendingSweep->retry_count);

        $this->pendingSweep->incrementRetry();
        $this->pendingSweep->refresh();
        $this->assertEquals(1, $this->pendingSweep->retry_count);

        $this->pendingSweep->incrementRetry();
        $this->pendingSweep->refresh();
        $this->assertEquals(2, $this->pendingSweep->retry_count);
    }

    /** @test */
    public function it_stores_all_required_pending_sweep_information()
    {
        $this->assertEquals($this->depositAddress->address, $this->pendingSweep->deposit_address);
        $this->assertEquals($this->chain->chain_id, $this->pendingSweep->chain_id);
        $this->assertEquals('0xdac17f958d2ee523a2206206994597c13d831ec7', $this->pendingSweep->token_address);
        $this->assertEquals('USDT', $this->pendingSweep->token_symbol);
        $this->assertEquals('1000000', $this->pendingSweep->amount);
        $this->assertEquals('pending', $this->pendingSweep->status);
        $this->assertEquals(0, $this->pendingSweep->retry_count);
    }

    /** @test */
    public function it_can_store_optional_transaction_hashes()
    {
        $sweep = PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => '0x' . str_repeat('i', 40),
            'token_symbol' => 'DAI',
            'amount' => '7000000',
            'status' => 'completed',
            'funding_tx_hash' => '0x' . str_repeat('f', 64),
            'sweep_tx_hash' => '0x' . str_repeat('s', 64),
            'retry_count' => 0,
        ]);

        $this->assertEquals('0x' . str_repeat('f', 64), $sweep->funding_tx_hash);
        $this->assertEquals('0x' . str_repeat('s', 64), $sweep->sweep_tx_hash);
    }

    /** @test */
    public function it_can_delete_a_pending_sweep()
    {
        $sweepId = $this->pendingSweep->id;
        $this->pendingSweep->delete();

        $this->assertDatabaseMissing('sweeper_pending_sweeps', ['id' => $sweepId]);
    }

    /** @test */
    public function it_allows_null_optional_fields()
    {
        $sweep = PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => '0x' . str_repeat('j', 40),
            'token_symbol' => 'WETH',
            'amount' => '8000000',
            'status' => 'pending',
            'retry_count' => 0,
        ]);

        $this->assertNull($sweep->funding_tx_hash);
        $this->assertNull($sweep->sweep_tx_hash);
        $this->assertNull($sweep->error_message);
    }

    /** @test */
    public function multiple_status_values_are_supported()
    {
        $pending = PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => '0x' . str_repeat('k', 40),
            'token_symbol' => 'TOKEN1',
            'amount' => '100',
            'status' => 'pending',
            'retry_count' => 0,
        ]);

        $failed = PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => '0x' . str_repeat('l', 40),
            'token_symbol' => 'TOKEN2',
            'amount' => '200',
            'status' => 'failed',
            'retry_count' => 2,
        ]);

        $completed = PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => '0x' . str_repeat('m', 40),
            'token_symbol' => 'TOKEN3',
            'amount' => '300',
            'status' => 'completed',
            'retry_count' => 0,
        ]);

        $this->assertEquals('pending', $pending->status);
        $this->assertEquals('failed', $failed->status);
        $this->assertEquals('completed', $completed->status);
    }
}
