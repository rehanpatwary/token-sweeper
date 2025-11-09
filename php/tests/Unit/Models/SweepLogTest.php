<?php

namespace Multicoin\TokenSweeper\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Models\DepositAddress;
use Multicoin\TokenSweeper\Models\PendingSweep;
use Multicoin\TokenSweeper\Models\SweepLog;
use Multicoin\TokenSweeper\Tests\TestCase;

class SweepLogTest extends TestCase
{
    use RefreshDatabase;

    protected Chain $chain;
    protected DepositAddress $depositAddress;
    protected PendingSweep $pendingSweep;
    protected SweepLog $sweepLog;

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
            'status' => 'completed',
            'retry_count' => 0,
        ]);

        $this->sweepLog = SweepLog::create([
            'sweep_id' => $this->pendingSweep->id,
            'chain_id' => $this->chain->chain_id,
            'deposit_address' => $this->depositAddress->address,
            'token_address' => '0xdac17f958d2ee523a2206206994597c13d831ec7',
            'amount' => '1000000',
            'funding_tx_hash' => '0x' . str_repeat('f', 64),
            'sweep_tx_hash' => '0x' . str_repeat('s', 64),
            'gas_used' => '50000',
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function it_can_create_a_sweep_log()
    {
        $this->assertInstanceOf(SweepLog::class, $this->sweepLog);
        $this->assertDatabaseHas('sweeper_logs', [
            'sweep_id' => $this->pendingSweep->id,
            'deposit_address' => $this->depositAddress->address,
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function it_has_correct_table_name()
    {
        $this->assertEquals('sweeper_logs', $this->sweepLog->getTable());
    }

    /** @test */
    public function it_has_fillable_attributes()
    {
        $fillable = [
            'sweep_id',
            'chain_id',
            'deposit_address',
            'token_address',
            'amount',
            'funding_tx_hash',
            'sweep_tx_hash',
            'gas_used',
            'status',
        ];

        $this->assertEquals($fillable, $this->sweepLog->getFillable());
    }

    /** @test */
    public function it_casts_sweep_id_to_integer()
    {
        $this->assertIsInt($this->sweepLog->sweep_id);
        $this->assertEquals($this->pendingSweep->id, $this->sweepLog->sweep_id);
    }

    /** @test */
    public function it_casts_chain_id_to_integer()
    {
        $this->assertIsInt($this->sweepLog->chain_id);
        $this->assertEquals(1, $this->sweepLog->chain_id);
    }

    /** @test */
    public function it_belongs_to_a_sweep()
    {
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $this->sweepLog->sweep());
        $this->assertInstanceOf(PendingSweep::class, $this->sweepLog->sweep);
        $this->assertEquals($this->pendingSweep->id, $this->sweepLog->sweep->id);
        $this->assertEquals('USDT', $this->sweepLog->sweep->token_symbol);
    }

    /** @test */
    public function it_belongs_to_a_chain()
    {
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $this->sweepLog->chain());
        $this->assertInstanceOf(Chain::class, $this->sweepLog->chain);
        $this->assertEquals($this->chain->id, $this->sweepLog->chain->id);
        $this->assertEquals('Ethereum Mainnet', $this->sweepLog->chain->name);
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

        $bscSweep = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('b', 40),
            'chain_id' => $anotherChain->chain_id,
            'token_address' => '0x' . str_repeat('c', 40),
            'token_symbol' => 'BUSD',
            'amount' => '2000000',
            'status' => 'completed',
            'retry_count' => 0,
        ]);

        SweepLog::create([
            'sweep_id' => $bscSweep->id,
            'chain_id' => $anotherChain->chain_id,
            'deposit_address' => '0x' . str_repeat('b', 40),
            'token_address' => '0x' . str_repeat('c', 40),
            'amount' => '2000000',
            'funding_tx_hash' => '0x' . str_repeat('f', 64),
            'sweep_tx_hash' => '0x' . str_repeat('s', 64),
            'gas_used' => '60000',
            'status' => 'completed',
        ]);

        $ethereumLogs = SweepLog::forChain(1)->get();
        $bscLogs = SweepLog::forChain(56)->get();

        $this->assertCount(1, $ethereumLogs);
        $this->assertEquals('0xdac17f958d2ee523a2206206994597c13d831ec7', $ethereumLogs->first()->token_address);

        $this->assertCount(1, $bscLogs);
        $this->assertEquals('0x' . str_repeat('c', 40), $bscLogs->first()->token_address);
    }

    /** @test */
    public function scope_for_chain_returns_empty_collection_for_chain_without_logs()
    {
        $logs = SweepLog::forChain(999)->get();

        $this->assertCount(0, $logs);
    }

    /** @test */
    public function scope_successful_returns_only_completed_logs()
    {
        SweepLog::create([
            'sweep_id' => $this->pendingSweep->id,
            'chain_id' => $this->chain->chain_id,
            'deposit_address' => '0x' . str_repeat('d', 40),
            'token_address' => '0x' . str_repeat('e', 40),
            'amount' => '3000000',
            'funding_tx_hash' => '0x' . str_repeat('1', 64),
            'sweep_tx_hash' => '0x' . str_repeat('2', 64),
            'gas_used' => '70000',
            'status' => 'failed',
        ]);

        $successfulLogs = SweepLog::successful()->get();

        $this->assertCount(1, $successfulLogs);
        $this->assertEquals('completed', $successfulLogs->first()->status);
        $this->assertEquals('1000000', $successfulLogs->first()->amount);
    }

    /** @test */
    public function scope_successful_returns_empty_collection_when_no_completed_logs()
    {
        $this->sweepLog->update(['status' => 'failed']);

        $successfulLogs = SweepLog::successful()->get();

        $this->assertCount(0, $successfulLogs);
    }

    /** @test */
    public function it_can_combine_scopes()
    {
        $anotherSweep = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('f', 40),
            'chain_id' => $this->chain->chain_id,
            'token_address' => '0x' . str_repeat('g', 40),
            'token_symbol' => 'DAI',
            'amount' => '4000000',
            'status' => 'failed',
            'retry_count' => 0,
        ]);

        SweepLog::create([
            'sweep_id' => $anotherSweep->id,
            'chain_id' => $this->chain->chain_id,
            'deposit_address' => '0x' . str_repeat('f', 40),
            'token_address' => '0x' . str_repeat('g', 40),
            'amount' => '4000000',
            'funding_tx_hash' => '0x' . str_repeat('3', 64),
            'sweep_tx_hash' => '0x' . str_repeat('4', 64),
            'gas_used' => '80000',
            'status' => 'failed',
        ]);

        $successfulChainLogs = SweepLog::successful()->forChain(1)->get();

        $this->assertCount(1, $successfulChainLogs);
        $this->assertEquals('completed', $successfulChainLogs->first()->status);
        $this->assertEquals(1, $successfulChainLogs->first()->chain_id);
    }

    /** @test */
    public function it_stores_all_required_sweep_log_information()
    {
        $this->assertEquals($this->pendingSweep->id, $this->sweepLog->sweep_id);
        $this->assertEquals($this->chain->chain_id, $this->sweepLog->chain_id);
        $this->assertEquals($this->depositAddress->address, $this->sweepLog->deposit_address);
        $this->assertEquals('0xdac17f958d2ee523a2206206994597c13d831ec7', $this->sweepLog->token_address);
        $this->assertEquals('1000000', $this->sweepLog->amount);
        $this->assertEquals('0x' . str_repeat('f', 64), $this->sweepLog->funding_tx_hash);
        $this->assertEquals('0x' . str_repeat('s', 64), $this->sweepLog->sweep_tx_hash);
        $this->assertEquals('50000', $this->sweepLog->gas_used);
        $this->assertEquals('completed', $this->sweepLog->status);
    }

    /** @test */
    public function it_can_update_sweep_log_attributes()
    {
        $this->sweepLog->update([
            'gas_used' => '60000',
            'status' => 'failed',
        ]);

        $this->sweepLog->refresh();

        $this->assertEquals('60000', $this->sweepLog->gas_used);
        $this->assertEquals('failed', $this->sweepLog->status);
    }

    /** @test */
    public function it_can_delete_a_sweep_log()
    {
        $logId = $this->sweepLog->id;
        $this->sweepLog->delete();

        $this->assertDatabaseMissing('sweeper_logs', ['id' => $logId]);
    }

    /** @test */
    public function it_records_gas_used_for_transactions()
    {
        $log1 = SweepLog::create([
            'sweep_id' => $this->pendingSweep->id,
            'chain_id' => $this->chain->chain_id,
            'deposit_address' => '0x' . str_repeat('h', 40),
            'token_address' => '0x' . str_repeat('i', 40),
            'amount' => '5000000',
            'funding_tx_hash' => '0x' . str_repeat('5', 64),
            'sweep_tx_hash' => '0x' . str_repeat('6', 64),
            'gas_used' => '21000',
            'status' => 'completed',
        ]);

        $log2 = SweepLog::create([
            'sweep_id' => $this->pendingSweep->id,
            'chain_id' => $this->chain->chain_id,
            'deposit_address' => '0x' . str_repeat('j', 40),
            'token_address' => '0x' . str_repeat('k', 40),
            'amount' => '6000000',
            'funding_tx_hash' => '0x' . str_repeat('7', 64),
            'sweep_tx_hash' => '0x' . str_repeat('8', 64),
            'gas_used' => '100000',
            'status' => 'completed',
        ]);

        $this->assertEquals('21000', $log1->gas_used);
        $this->assertEquals('100000', $log2->gas_used);
    }

    /** @test */
    public function multiple_logs_can_belong_to_same_sweep()
    {
        SweepLog::create([
            'sweep_id' => $this->pendingSweep->id,
            'chain_id' => $this->chain->chain_id,
            'deposit_address' => '0x' . str_repeat('l', 40),
            'token_address' => '0x' . str_repeat('m', 40),
            'amount' => '7000000',
            'funding_tx_hash' => '0x' . str_repeat('9', 64),
            'sweep_tx_hash' => '0x' . str_repeat('0', 64),
            'gas_used' => '90000',
            'status' => 'completed',
        ]);

        $logs = SweepLog::where('sweep_id', $this->pendingSweep->id)->get();

        $this->assertCount(2, $logs);
    }

    /** @test */
    public function it_supports_different_status_values()
    {
        $completedLog = SweepLog::create([
            'sweep_id' => $this->pendingSweep->id,
            'chain_id' => $this->chain->chain_id,
            'deposit_address' => '0x' . str_repeat('n', 40),
            'token_address' => '0x' . str_repeat('o', 40),
            'amount' => '100',
            'funding_tx_hash' => '0x1',
            'sweep_tx_hash' => '0x2',
            'gas_used' => '1000',
            'status' => 'completed',
        ]);

        $failedLog = SweepLog::create([
            'sweep_id' => $this->pendingSweep->id,
            'chain_id' => $this->chain->chain_id,
            'deposit_address' => '0x' . str_repeat('p', 40),
            'token_address' => '0x' . str_repeat('q', 40),
            'amount' => '200',
            'funding_tx_hash' => '0x3',
            'sweep_tx_hash' => '0x4',
            'gas_used' => '2000',
            'status' => 'failed',
        ]);

        $this->assertEquals('completed', $completedLog->status);
        $this->assertEquals('failed', $failedLog->status);
    }

    /** @test */
    public function it_stores_transaction_hashes()
    {
        $this->assertEquals('0x' . str_repeat('f', 64), $this->sweepLog->funding_tx_hash);
        $this->assertEquals('0x' . str_repeat('s', 64), $this->sweepLog->sweep_tx_hash);
        $this->assertEquals(66, strlen($this->sweepLog->funding_tx_hash));
        $this->assertEquals(66, strlen($this->sweepLog->sweep_tx_hash));
    }

    /** @test */
    public function it_can_query_logs_by_deposit_address()
    {
        $anotherAddress = '0x' . str_repeat('r', 40);

        SweepLog::create([
            'sweep_id' => $this->pendingSweep->id,
            'chain_id' => $this->chain->chain_id,
            'deposit_address' => $anotherAddress,
            'token_address' => '0x' . str_repeat('s', 40),
            'amount' => '300',
            'funding_tx_hash' => '0x5',
            'sweep_tx_hash' => '0x6',
            'gas_used' => '3000',
            'status' => 'completed',
        ]);

        $originalAddressLogs = SweepLog::where('deposit_address', $this->depositAddress->address)->get();
        $anotherAddressLogs = SweepLog::where('deposit_address', $anotherAddress)->get();

        $this->assertCount(1, $originalAddressLogs);
        $this->assertCount(1, $anotherAddressLogs);
    }
}
