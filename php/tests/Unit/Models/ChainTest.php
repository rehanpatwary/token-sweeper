<?php

namespace Multicoin\TokenSweeper\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Models\Token;
use Multicoin\TokenSweeper\Models\DepositAddress;
use Multicoin\TokenSweeper\Models\PendingSweep;
use Multicoin\TokenSweeper\Tests\TestCase;

class ChainTest extends TestCase
{
    use RefreshDatabase;

    protected Chain $chain;

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
    }

    /** @test */
    public function it_can_create_a_chain()
    {
        $this->assertInstanceOf(Chain::class, $this->chain);
        $this->assertDatabaseHas('sweeper_chains', [
            'chain_id' => 1,
            'name' => 'Ethereum Mainnet',
        ]);
    }

    /** @test */
    public function it_has_correct_table_name()
    {
        $this->assertEquals('sweeper_chains', $this->chain->getTable());
    }

    /** @test */
    public function it_has_fillable_attributes()
    {
        $fillable = [
            'chain_id',
            'name',
            'rpc_url',
            'master_wallet_address',
            'master_private_key_encrypted',
            'hot_wallet_address',
            'native_symbol',
            'gas_amount_wei',
            'gas_limit_token_transfer',
            'is_active',
        ];

        $this->assertEquals($fillable, $this->chain->getFillable());
    }

    /** @test */
    public function it_casts_chain_id_to_integer()
    {
        $this->assertIsInt($this->chain->chain_id);
        $this->assertEquals(1, $this->chain->chain_id);
    }

    /** @test */
    public function it_casts_gas_limit_token_transfer_to_integer()
    {
        $this->assertIsInt($this->chain->gas_limit_token_transfer);
        $this->assertEquals(100000, $this->chain->gas_limit_token_transfer);
    }

    /** @test */
    public function it_casts_is_active_to_boolean()
    {
        $this->assertIsBool($this->chain->is_active);
        $this->assertTrue($this->chain->is_active);

        $inactiveChain = Chain::create([
            'chain_id' => 2,
            'name' => 'Test Chain',
            'rpc_url' => 'https://test.com',
            'master_wallet_address' => '0x' . str_repeat('3', 40),
            'master_private_key_encrypted' => 'encrypted',
            'hot_wallet_address' => '0x' . str_repeat('4', 40),
            'native_symbol' => 'TEST',
            'gas_amount_wei' => '1000000',
            'gas_limit_token_transfer' => 50000,
            'is_active' => false,
        ]);

        $this->assertFalse($inactiveChain->is_active);
    }

    /** @test */
    public function it_has_tokens_relationship()
    {
        $token = Token::create([
            'chain_id' => $this->chain->chain_id,
            'symbol' => 'USDT',
            'name' => 'Tether USD',
            'contract_address' => '0x' . str_repeat('a', 40),
            'decimals' => 6,
            'is_active' => true,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $this->chain->tokens());
        $this->assertCount(1, $this->chain->tokens);
        $this->assertTrue($this->chain->tokens->contains($token));
        $this->assertEquals('USDT', $this->chain->tokens->first()->symbol);
    }

    /** @test */
    public function it_has_deposit_addresses_relationship()
    {
        $depositAddress = DepositAddress::create([
            'user_id' => 1,
            'chain_id' => $this->chain->chain_id,
            'address' => '0x' . str_repeat('b', 40),
            'private_key_encrypted' => 'encrypted_private_key',
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $this->chain->depositAddresses());
        $this->assertCount(1, $this->chain->depositAddresses);
        $this->assertTrue($this->chain->depositAddresses->contains($depositAddress));
        $this->assertEquals('0x' . str_repeat('b', 40), $this->chain->depositAddresses->first()->address);
    }

    /** @test */
    public function it_has_pending_sweeps_relationship()
    {
        $pendingSweep = PendingSweep::create([
            'deposit_address' => '0x' . str_repeat('c', 40),
            'chain_id' => $this->chain->chain_id,
            'token_address' => '0x' . str_repeat('d', 40),
            'token_symbol' => 'USDC',
            'amount' => '1000000',
            'status' => 'pending',
            'retry_count' => 0,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $this->chain->pendingSweeps());
        $this->assertCount(1, $this->chain->pendingSweeps);
        $this->assertTrue($this->chain->pendingSweeps->contains($pendingSweep));
        $this->assertEquals('USDC', $this->chain->pendingSweeps->first()->token_symbol);
    }

    /** @test */
    public function it_can_have_multiple_tokens()
    {
        Token::create([
            'chain_id' => $this->chain->chain_id,
            'symbol' => 'USDT',
            'name' => 'Tether USD',
            'contract_address' => '0x' . str_repeat('a', 40),
            'decimals' => 6,
            'is_active' => true,
        ]);

        Token::create([
            'chain_id' => $this->chain->chain_id,
            'symbol' => 'USDC',
            'name' => 'USD Coin',
            'contract_address' => '0x' . str_repeat('b', 40),
            'decimals' => 6,
            'is_active' => true,
        ]);

        $this->assertCount(2, $this->chain->tokens);
    }

    /** @test */
    public function scope_active_returns_only_active_chains()
    {
        Chain::create([
            'chain_id' => 2,
            'name' => 'Inactive Chain',
            'rpc_url' => 'https://test.com',
            'master_wallet_address' => '0x' . str_repeat('3', 40),
            'master_private_key_encrypted' => 'encrypted',
            'hot_wallet_address' => '0x' . str_repeat('4', 40),
            'native_symbol' => 'TEST',
            'gas_amount_wei' => '1000000',
            'gas_limit_token_transfer' => 50000,
            'is_active' => false,
        ]);

        $activeChains = Chain::active()->get();

        $this->assertCount(1, $activeChains);
        $this->assertEquals('Ethereum Mainnet', $activeChains->first()->name);
        $this->assertTrue($activeChains->first()->is_active);
    }

    /** @test */
    public function scope_active_returns_empty_collection_when_no_active_chains()
    {
        $this->chain->update(['is_active' => false]);

        $activeChains = Chain::active()->get();

        $this->assertCount(0, $activeChains);
    }

    /** @test */
    public function it_can_update_chain_attributes()
    {
        $this->chain->update([
            'name' => 'Updated Name',
            'is_active' => false,
        ]);

        $this->chain->refresh();

        $this->assertEquals('Updated Name', $this->chain->name);
        $this->assertFalse($this->chain->is_active);
    }

    /** @test */
    public function it_stores_all_required_chain_information()
    {
        $this->assertEquals(1, $this->chain->chain_id);
        $this->assertEquals('Ethereum Mainnet', $this->chain->name);
        $this->assertEquals('https://mainnet.infura.io/v3/test', $this->chain->rpc_url);
        $this->assertEquals('0x' . str_repeat('1', 40), $this->chain->master_wallet_address);
        $this->assertEquals('encrypted_key', $this->chain->master_private_key_encrypted);
        $this->assertEquals('0x' . str_repeat('2', 40), $this->chain->hot_wallet_address);
        $this->assertEquals('ETH', $this->chain->native_symbol);
        $this->assertEquals('21000000000000000', $this->chain->gas_amount_wei);
        $this->assertEquals(100000, $this->chain->gas_limit_token_transfer);
        $this->assertTrue($this->chain->is_active);
    }

    /** @test */
    public function it_can_delete_a_chain()
    {
        $chainId = $this->chain->id;
        $this->chain->delete();

        $this->assertDatabaseMissing('sweeper_chains', ['id' => $chainId]);
    }
}
