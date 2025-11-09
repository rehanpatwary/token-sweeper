<?php

namespace Multicoin\TokenSweeper\Tests\Feature;

use Illuminate\Support\Facades\Crypt;
use Multicoin\TokenSweeper\Tests\TestCase;
use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Models\DepositAddress;
use Multicoin\TokenSweeper\Services\WalletService;

class DepositAddressTest extends TestCase
{
    protected Chain $chain;
    protected WalletService $walletService;

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
            'gas_amount_wei' => '0x' . dechex(100000000000000),
            'gas_limit_token_transfer' => 100000,
            'is_active' => true,
        ]);

        $this->walletService = new WalletService();
    }

    /** @test */
    public function it_generates_valid_ethereum_address()
    {
        $wallet = $this->walletService->generateWallet();

        $this->assertIsArray($wallet);
        $this->assertArrayHasKey('address', $wallet);
        $this->assertArrayHasKey('privateKey', $wallet);

        // Verify address format
        $this->assertStringStartsWith('0x', $wallet['address']);
        $this->assertEquals(42, strlen($wallet['address'])); // 0x + 40 hex chars

        // Verify private key format
        $this->assertStringStartsWith('0x', $wallet['privateKey']);
        $this->assertEquals(66, strlen($wallet['privateKey'])); // 0x + 64 hex chars
    }

    /** @test */
    public function it_generates_unique_addresses()
    {
        $wallet1 = $this->walletService->generateWallet();
        $wallet2 = $this->walletService->generateWallet();

        $this->assertNotEquals($wallet1['address'], $wallet2['address']);
        $this->assertNotEquals($wallet1['privateKey'], $wallet2['privateKey']);
    }

    /** @test */
    public function it_creates_deposit_address_for_user()
    {
        $userId = 1;
        $chainId = $this->chain->chain_id;

        $address = $this->walletService->createDepositAddress($userId, $chainId);

        $this->assertStringStartsWith('0x', $address);
        $this->assertEquals(42, strlen($address));

        $this->assertDatabaseHas('sweeper_deposit_addresses', [
            'user_id' => $userId,
            'chain_id' => $chainId,
            'address' => $address,
        ]);

        $depositAddress = DepositAddress::where('address', $address)->first();
        $this->assertNotNull($depositAddress);
        $this->assertEquals($userId, $depositAddress->user_id);
        $this->assertEquals($chainId, $depositAddress->chain_id);
        $this->assertNotNull($depositAddress->private_key_encrypted);
    }

    /** @test */
    public function it_returns_existing_address_if_already_created()
    {
        $userId = 1;
        $chainId = $this->chain->chain_id;

        // First creation
        $address1 = $this->walletService->createDepositAddress($userId, $chainId);

        // Second attempt should return the same address
        $address2 = $this->walletService->createDepositAddress($userId, $chainId);

        $this->assertEquals($address1, $address2);

        // Verify only one record exists
        $count = DepositAddress::where('user_id', $userId)
            ->where('chain_id', $chainId)
            ->count();

        $this->assertEquals(1, $count);
    }

    /** @test */
    public function it_creates_different_addresses_for_different_users()
    {
        $chainId = $this->chain->chain_id;

        $address1 = $this->walletService->createDepositAddress(1, $chainId);
        $address2 = $this->walletService->createDepositAddress(2, $chainId);

        $this->assertNotEquals($address1, $address2);

        $this->assertDatabaseHas('sweeper_deposit_addresses', [
            'user_id' => 1,
            'address' => $address1,
        ]);

        $this->assertDatabaseHas('sweeper_deposit_addresses', [
            'user_id' => 2,
            'address' => $address2,
        ]);
    }

    /** @test */
    public function it_creates_different_addresses_for_different_chains()
    {
        // Create second chain
        $chain2 = Chain::create([
            'chain_id' => 56,
            'name' => 'BSC',
            'rpc_url' => 'https://bsc-dataseed.binance.org/',
            'master_wallet_address' => $this->createTestAddress(),
            'master_private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('2', 64)),
            'hot_wallet_address' => $this->createTestAddress(),
            'native_symbol' => 'BNB',
            'gas_amount_wei' => '0x' . dechex(10000000000000),
            'gas_limit_token_transfer' => 100000,
            'is_active' => true,
        ]);

        $userId = 1;

        $address1 = $this->walletService->createDepositAddress($userId, $this->chain->chain_id);
        $address2 = $this->walletService->createDepositAddress($userId, $chain2->chain_id);

        $this->assertNotEquals($address1, $address2);

        $this->assertDatabaseHas('sweeper_deposit_addresses', [
            'user_id' => $userId,
            'chain_id' => $this->chain->chain_id,
            'address' => $address1,
        ]);

        $this->assertDatabaseHas('sweeper_deposit_addresses', [
            'user_id' => $userId,
            'chain_id' => $chain2->chain_id,
            'address' => $address2,
        ]);
    }

    /** @test */
    public function it_encrypts_private_key_in_database()
    {
        $userId = 1;
        $chainId = $this->chain->chain_id;

        $address = $this->walletService->createDepositAddress($userId, $chainId);

        $depositAddress = DepositAddress::where('address', $address)->first();

        // Verify the encrypted private key is different from a known pattern
        $this->assertNotEquals('0x' . str_repeat('1', 64), $depositAddress->private_key_encrypted);

        // Verify it can be decrypted
        $decrypted = Crypt::decryptString($depositAddress->private_key_encrypted);
        $this->assertStringStartsWith('0x', $decrypted);
        $this->assertEquals(66, strlen($decrypted));
    }

    /** @test */
    public function it_retrieves_private_key_for_address()
    {
        $userId = 1;
        $chainId = $this->chain->chain_id;

        // Create deposit address
        $address = $this->walletService->createDepositAddress($userId, $chainId);

        // Retrieve private key
        $privateKey = $this->walletService->getPrivateKey($address, $chainId);

        $this->assertStringStartsWith('0x', $privateKey);
        $this->assertEquals(66, strlen($privateKey));
    }

    /** @test */
    public function it_throws_exception_when_retrieving_nonexistent_address()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->walletService->getPrivateKey($this->createTestAddress(), $this->chain->chain_id);
    }

    /** @test */
    public function it_retrieves_master_wallet_credentials()
    {
        $masterWallet = $this->walletService->getMasterWallet($this->chain->chain_id);

        $this->assertIsArray($masterWallet);
        $this->assertArrayHasKey('address', $masterWallet);
        $this->assertArrayHasKey('privateKey', $masterWallet);

        $this->assertEquals($this->chain->master_wallet_address, $masterWallet['address']);
        $this->assertStringStartsWith('0x', $masterWallet['privateKey']);
    }

    /** @test */
    public function it_throws_exception_when_retrieving_master_wallet_for_nonexistent_chain()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $this->walletService->getMasterWallet(999);
    }

    /** @test */
    public function it_retrieves_all_deposit_addresses_for_user()
    {
        $userId = 1;

        // Create addresses on multiple chains
        $chain2 = Chain::create([
            'chain_id' => 56,
            'name' => 'BSC',
            'rpc_url' => 'https://bsc-dataseed.binance.org/',
            'master_wallet_address' => $this->createTestAddress(),
            'master_private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('2', 64)),
            'hot_wallet_address' => $this->createTestAddress(),
            'native_symbol' => 'BNB',
            'gas_amount_wei' => '0x' . dechex(10000000000000),
            'gas_limit_token_transfer' => 100000,
            'is_active' => true,
        ]);

        $this->walletService->createDepositAddress($userId, $this->chain->chain_id);
        $this->walletService->createDepositAddress($userId, $chain2->chain_id);

        $addresses = $this->walletService->getUserDepositAddresses($userId);

        $this->assertEquals(2, $addresses->count());
        $this->assertTrue($addresses->every(fn($addr) => $addr->user_id === $userId));
    }

    /** @test */
    public function it_retrieves_all_deposit_addresses()
    {
        // Create addresses for different users
        $this->walletService->createDepositAddress(1, $this->chain->chain_id);
        $this->walletService->createDepositAddress(2, $this->chain->chain_id);
        $this->walletService->createDepositAddress(3, $this->chain->chain_id);

        $allAddresses = $this->walletService->getAllDepositAddresses();

        $this->assertEquals(3, $allAddresses->count());
    }

    /** @test */
    public function it_filters_deposit_addresses_by_chain()
    {
        // Create second chain
        $chain2 = Chain::create([
            'chain_id' => 56,
            'name' => 'BSC',
            'rpc_url' => 'https://bsc-dataseed.binance.org/',
            'master_wallet_address' => $this->createTestAddress(),
            'master_private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('2', 64)),
            'hot_wallet_address' => $this->createTestAddress(),
            'native_symbol' => 'BNB',
            'gas_amount_wei' => '0x' . dechex(10000000000000),
            'gas_limit_token_transfer' => 100000,
            'is_active' => true,
        ]);

        // Create addresses on both chains
        $this->walletService->createDepositAddress(1, $this->chain->chain_id);
        $this->walletService->createDepositAddress(2, $this->chain->chain_id);
        $this->walletService->createDepositAddress(3, $chain2->chain_id);

        $ethAddresses = $this->walletService->getAllDepositAddresses($this->chain->chain_id);
        $bscAddresses = $this->walletService->getAllDepositAddresses($chain2->chain_id);

        $this->assertEquals(2, $ethAddresses->count());
        $this->assertEquals(1, $bscAddresses->count());
    }

    /** @test */
    public function it_uses_scope_for_user_query()
    {
        $this->walletService->createDepositAddress(1, $this->chain->chain_id);
        $this->walletService->createDepositAddress(2, $this->chain->chain_id);

        $user1Addresses = DepositAddress::forUser(1)->get();
        $user2Addresses = DepositAddress::forUser(2)->get();

        $this->assertEquals(1, $user1Addresses->count());
        $this->assertEquals(1, $user2Addresses->count());
        $this->assertEquals(1, $user1Addresses->first()->user_id);
        $this->assertEquals(2, $user2Addresses->first()->user_id);
    }

    /** @test */
    public function it_uses_scope_for_chain_query()
    {
        // Create second chain
        $chain2 = Chain::create([
            'chain_id' => 56,
            'name' => 'BSC',
            'rpc_url' => 'https://bsc-dataseed.binance.org/',
            'master_wallet_address' => $this->createTestAddress(),
            'master_private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('2', 64)),
            'hot_wallet_address' => $this->createTestAddress(),
            'native_symbol' => 'BNB',
            'gas_amount_wei' => '0x' . dechex(10000000000000),
            'gas_limit_token_transfer' => 100000,
            'is_active' => true,
        ]);

        $this->walletService->createDepositAddress(1, $this->chain->chain_id);
        $this->walletService->createDepositAddress(1, $chain2->chain_id);

        $ethAddresses = DepositAddress::forChain($this->chain->chain_id)->get();
        $bscAddresses = DepositAddress::forChain($chain2->chain_id)->get();

        $this->assertEquals(1, $ethAddresses->count());
        $this->assertEquals(1, $bscAddresses->count());
        $this->assertEquals($this->chain->chain_id, $ethAddresses->first()->chain_id);
        $this->assertEquals($chain2->chain_id, $bscAddresses->first()->chain_id);
    }

    /** @test */
    public function it_establishes_relationship_with_chain()
    {
        $userId = 1;
        $address = $this->walletService->createDepositAddress($userId, $this->chain->chain_id);

        $depositAddress = DepositAddress::where('address', $address)->first();

        $this->assertInstanceOf(Chain::class, $depositAddress->chain);
        $this->assertEquals($this->chain->chain_id, $depositAddress->chain->chain_id);
        $this->assertEquals($this->chain->name, $depositAddress->chain->name);
    }

    /** @test */
    public function it_hides_private_key_in_model_serialization()
    {
        $userId = 1;
        $address = $this->walletService->createDepositAddress($userId, $this->chain->chain_id);

        $depositAddress = DepositAddress::where('address', $address)->first();
        $array = $depositAddress->toArray();

        $this->assertArrayNotHasKey('private_key_encrypted', $array);
    }

    /** @test */
    public function it_tracks_last_sweep_timestamp()
    {
        $userId = 1;
        $address = $this->walletService->createDepositAddress($userId, $this->chain->chain_id);

        $depositAddress = DepositAddress::where('address', $address)->first();

        $this->assertNull($depositAddress->last_sweep_at);

        $now = now();
        $depositAddress->update(['last_sweep_at' => $now]);
        $depositAddress->refresh();

        $this->assertNotNull($depositAddress->last_sweep_at);
        $this->assertInstanceOf(\Carbon\Carbon::class, $depositAddress->last_sweep_at);
    }

    /** @test */
    public function it_handles_concurrent_address_creation_attempts()
    {
        $userId = 1;
        $chainId = $this->chain->chain_id;

        // Simulate concurrent requests
        $address1 = $this->walletService->createDepositAddress($userId, $chainId);
        $address2 = $this->walletService->createDepositAddress($userId, $chainId);
        $address3 = $this->walletService->createDepositAddress($userId, $chainId);

        // All should return the same address
        $this->assertEquals($address1, $address2);
        $this->assertEquals($address2, $address3);

        // Only one record should exist
        $count = DepositAddress::where('user_id', $userId)
            ->where('chain_id', $chainId)
            ->count();

        $this->assertEquals(1, $count);
    }

    /** @test */
    public function it_supports_multiple_users_on_same_chain()
    {
        $chainId = $this->chain->chain_id;

        // Create addresses for 10 different users
        $addresses = [];
        for ($userId = 1; $userId <= 10; $userId++) {
            $addresses[$userId] = $this->walletService->createDepositAddress($userId, $chainId);
        }

        // Verify all addresses are unique
        $uniqueAddresses = array_unique($addresses);
        $this->assertEquals(10, count($uniqueAddresses));

        // Verify all were stored
        $count = DepositAddress::where('chain_id', $chainId)->count();
        $this->assertEquals(10, $count);
    }
}
