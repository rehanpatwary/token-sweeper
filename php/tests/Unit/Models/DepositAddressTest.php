<?php

namespace Multicoin\TokenSweeper\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Models\DepositAddress;
use Multicoin\TokenSweeper\Models\PendingSweep;
use Multicoin\TokenSweeper\Tests\TestCase;

class DepositAddressTest extends TestCase
{
    use RefreshDatabase;

    protected Chain $chain;
    protected DepositAddress $depositAddress;

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
            'private_key_encrypted' => 'encrypted_private_key_123',
        ]);
    }

    /** @test */
    public function it_can_create_a_deposit_address()
    {
        $this->assertInstanceOf(DepositAddress::class, $this->depositAddress);
        $this->assertDatabaseHas('sweeper_deposit_addresses', [
            'user_id' => 1,
            'address' => '0x' . str_repeat('a', 40),
        ]);
    }

    /** @test */
    public function it_has_correct_table_name()
    {
        $this->assertEquals('sweeper_deposit_addresses', $this->depositAddress->getTable());
    }

    /** @test */
    public function it_has_fillable_attributes()
    {
        $fillable = [
            'user_id',
            'chain_id',
            'address',
            'private_key_encrypted',
            'last_sweep_at',
        ];

        $this->assertEquals($fillable, $this->depositAddress->getFillable());
    }

    /** @test */
    public function it_casts_user_id_to_integer()
    {
        $this->assertIsInt($this->depositAddress->user_id);
        $this->assertEquals(1, $this->depositAddress->user_id);
    }

    /** @test */
    public function it_casts_chain_id_to_integer()
    {
        $this->assertIsInt($this->depositAddress->chain_id);
        $this->assertEquals(1, $this->depositAddress->chain_id);
    }

    /** @test */
    public function it_casts_last_sweep_at_to_datetime()
    {
        $now = now();
        $this->depositAddress->update(['last_sweep_at' => $now]);
        $this->depositAddress->refresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $this->depositAddress->last_sweep_at);
        $this->assertEquals($now->timestamp, $this->depositAddress->last_sweep_at->timestamp);
    }

    /** @test */
    public function it_hides_private_key_encrypted_in_array()
    {
        $array = $this->depositAddress->toArray();

        $this->assertArrayNotHasKey('private_key_encrypted', $array);
    }

    /** @test */
    public function it_hides_private_key_encrypted_in_json()
    {
        $json = $this->depositAddress->toJson();
        $decoded = json_decode($json, true);

        $this->assertArrayNotHasKey('private_key_encrypted', $decoded);
    }

    /** @test */
    public function private_key_encrypted_is_still_accessible_as_attribute()
    {
        $this->assertEquals('encrypted_private_key_123', $this->depositAddress->private_key_encrypted);
    }

    /** @test */
    public function it_belongs_to_a_chain()
    {
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $this->depositAddress->chain());
        $this->assertInstanceOf(Chain::class, $this->depositAddress->chain);
        $this->assertEquals($this->chain->id, $this->depositAddress->chain->id);
        $this->assertEquals('Ethereum Mainnet', $this->depositAddress->chain->name);
    }

    /** @test */
    public function it_has_pending_sweeps_relationship()
    {
        $pendingSweep = PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => '0x' . str_repeat('b', 40),
            'token_symbol' => 'USDT',
            'amount' => '1000000',
            'status' => 'pending',
            'retry_count' => 0,
        ]);

        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class, $this->depositAddress->pendingSweeps());
        $this->assertCount(1, $this->depositAddress->pendingSweeps);
        $this->assertTrue($this->depositAddress->pendingSweeps->contains($pendingSweep));
        $this->assertEquals('USDT', $this->depositAddress->pendingSweeps->first()->token_symbol);
    }

    /** @test */
    public function it_can_have_multiple_pending_sweeps()
    {
        PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => '0x' . str_repeat('b', 40),
            'token_symbol' => 'USDT',
            'amount' => '1000000',
            'status' => 'pending',
            'retry_count' => 0,
        ]);

        PendingSweep::create([
            'deposit_address' => $this->depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => '0x' . str_repeat('c', 40),
            'token_symbol' => 'USDC',
            'amount' => '2000000',
            'status' => 'pending',
            'retry_count' => 0,
        ]);

        $this->assertCount(2, $this->depositAddress->pendingSweeps);
    }

    /** @test */
    public function scope_for_user_filters_by_user_id()
    {
        DepositAddress::create([
            'user_id' => 2,
            'chain_id' => $this->chain->chain_id,
            'address' => '0x' . str_repeat('d', 40),
            'private_key_encrypted' => 'encrypted_key_user_2',
        ]);

        $user1Addresses = DepositAddress::forUser(1)->get();
        $user2Addresses = DepositAddress::forUser(2)->get();

        $this->assertCount(1, $user1Addresses);
        $this->assertEquals('0x' . str_repeat('a', 40), $user1Addresses->first()->address);

        $this->assertCount(1, $user2Addresses);
        $this->assertEquals('0x' . str_repeat('d', 40), $user2Addresses->first()->address);
    }

    /** @test */
    public function scope_for_user_returns_empty_collection_for_user_without_addresses()
    {
        $addresses = DepositAddress::forUser(999)->get();

        $this->assertCount(0, $addresses);
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

        DepositAddress::create([
            'user_id' => 1,
            'chain_id' => $anotherChain->chain_id,
            'address' => '0x' . str_repeat('e', 40),
            'private_key_encrypted' => 'encrypted_bsc',
        ]);

        $ethereumAddresses = DepositAddress::forChain(1)->get();
        $bscAddresses = DepositAddress::forChain(56)->get();

        $this->assertCount(1, $ethereumAddresses);
        $this->assertEquals('0x' . str_repeat('a', 40), $ethereumAddresses->first()->address);

        $this->assertCount(1, $bscAddresses);
        $this->assertEquals('0x' . str_repeat('e', 40), $bscAddresses->first()->address);
    }

    /** @test */
    public function scope_for_chain_returns_empty_collection_for_chain_without_addresses()
    {
        $addresses = DepositAddress::forChain(999)->get();

        $this->assertCount(0, $addresses);
    }

    /** @test */
    public function it_can_combine_scopes()
    {
        DepositAddress::create([
            'user_id' => 2,
            'chain_id' => $this->chain->chain_id,
            'address' => '0x' . str_repeat('f', 40),
            'private_key_encrypted' => 'encrypted_user_2',
        ]);

        $user1ChainAddresses = DepositAddress::forUser(1)->forChain(1)->get();

        $this->assertCount(1, $user1ChainAddresses);
        $this->assertEquals('0x' . str_repeat('a', 40), $user1ChainAddresses->first()->address);
        $this->assertEquals(1, $user1ChainAddresses->first()->user_id);
    }

    /** @test */
    public function it_stores_all_required_deposit_address_information()
    {
        $this->assertEquals(1, $this->depositAddress->user_id);
        $this->assertEquals($this->chain->chain_id, $this->depositAddress->chain_id);
        $this->assertEquals('0x' . str_repeat('a', 40), $this->depositAddress->address);
        $this->assertEquals('encrypted_private_key_123', $this->depositAddress->private_key_encrypted);
        $this->assertNull($this->depositAddress->last_sweep_at);
    }

    /** @test */
    public function it_can_update_deposit_address_attributes()
    {
        $newTimestamp = now();
        $this->depositAddress->update([
            'last_sweep_at' => $newTimestamp,
        ]);

        $this->depositAddress->refresh();

        $this->assertEquals($newTimestamp->timestamp, $this->depositAddress->last_sweep_at->timestamp);
    }

    /** @test */
    public function it_can_delete_a_deposit_address()
    {
        $addressId = $this->depositAddress->id;
        $this->depositAddress->delete();

        $this->assertDatabaseMissing('sweeper_deposit_addresses', ['id' => $addressId]);
    }

    /** @test */
    public function it_allows_null_last_sweep_at()
    {
        $address = DepositAddress::create([
            'user_id' => 3,
            'chain_id' => $this->chain->chain_id,
            'address' => '0x' . str_repeat('9', 40),
            'private_key_encrypted' => 'encrypted_key',
            'last_sweep_at' => null,
        ]);

        $this->assertNull($address->last_sweep_at);
    }

    /** @test */
    public function multiple_users_can_have_addresses_on_same_chain()
    {
        DepositAddress::create([
            'user_id' => 2,
            'chain_id' => $this->chain->chain_id,
            'address' => '0x' . str_repeat('b', 40),
            'private_key_encrypted' => 'encrypted_2',
        ]);

        DepositAddress::create([
            'user_id' => 3,
            'chain_id' => $this->chain->chain_id,
            'address' => '0x' . str_repeat('c', 40),
            'private_key_encrypted' => 'encrypted_3',
        ]);

        $chainAddresses = DepositAddress::forChain(1)->get();

        $this->assertCount(3, $chainAddresses);
    }
}
