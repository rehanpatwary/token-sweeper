<?php

namespace Multicoin\TokenSweeper\Tests\Unit\Services;

use Elliptic\EC;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Models\DepositAddress;
use Multicoin\TokenSweeper\Services\WalletService;
use Multicoin\TokenSweeper\Tests\TestCase;
use Mockery;

class WalletServiceTest extends TestCase
{
    use RefreshDatabase;

    protected WalletService $walletService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->walletService = new WalletService();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that generateWallet returns an array with address and privateKey keys
     */
    public function test_generate_wallet_returns_correct_structure()
    {
        $wallet = $this->walletService->generateWallet();

        $this->assertIsArray($wallet);
        $this->assertArrayHasKey('address', $wallet);
        $this->assertArrayHasKey('privateKey', $wallet);
    }

    /**
     * Test that generateWallet creates valid Ethereum address format
     */
    public function test_generate_wallet_creates_valid_ethereum_address()
    {
        $wallet = $this->walletService->generateWallet();

        // Address should start with 0x
        $this->assertStringStartsWith('0x', $wallet['address']);

        // Address should be 42 characters (0x + 40 hex chars)
        $this->assertEquals(42, strlen($wallet['address']));

        // Address should only contain valid hex characters
        $this->assertMatchesRegularExpression('/^0x[a-f0-9]{40}$/i', $wallet['address']);
    }

    /**
     * Test that generateWallet creates valid private key format
     */
    public function test_generate_wallet_creates_valid_private_key()
    {
        $wallet = $this->walletService->generateWallet();

        // Private key should start with 0x
        $this->assertStringStartsWith('0x', $wallet['privateKey']);

        // Private key should be 66 characters (0x + 64 hex chars)
        $this->assertEquals(66, strlen($wallet['privateKey']));

        // Private key should only contain valid hex characters
        $this->assertMatchesRegularExpression('/^0x[a-f0-9]{64}$/i', $wallet['privateKey']);
    }

    /**
     * Test that generateWallet produces unique wallets on each call
     */
    public function test_generate_wallet_produces_unique_wallets()
    {
        $wallet1 = $this->walletService->generateWallet();
        $wallet2 = $this->walletService->generateWallet();

        $this->assertNotEquals($wallet1['address'], $wallet2['address']);
        $this->assertNotEquals($wallet1['privateKey'], $wallet2['privateKey']);
    }

    /**
     * Test that generated private key can derive the same public address
     */
    public function test_generate_wallet_private_key_derives_correct_address()
    {
        $wallet = $this->walletService->generateWallet();

        // Verify the private key generates the correct address
        $ec = new EC('secp256k1');
        $privateKeyHex = substr($wallet['privateKey'], 2); // Remove 0x prefix

        $keyPair = $ec->keyFromPrivate($privateKeyHex, 'hex');
        $publicKey = $keyPair->getPublic()->encode('hex');
        $publicKey = substr($publicKey, 2);

        $hash = \kornrunner\Keccak::hash(hex2bin($publicKey), 256);
        $derivedAddress = '0x' . substr($hash, -40);

        $this->assertEquals($wallet['address'], $derivedAddress);
    }

    /**
     * Test createDepositAddress creates new address for user and chain
     */
    public function test_create_deposit_address_creates_new_address()
    {
        $userId = 1;
        $chainId = 1;

        $address = $this->walletService->createDepositAddress($userId, $chainId);

        $this->assertStringStartsWith('0x', $address);
        $this->assertEquals(42, strlen($address));

        // Verify database record was created
        $this->assertDatabaseHas('sweeper_deposit_addresses', [
            'user_id' => $userId,
            'chain_id' => $chainId,
            'address' => $address,
        ]);
    }

    /**
     * Test createDepositAddress returns existing address if already exists
     */
    public function test_create_deposit_address_returns_existing_address()
    {
        $userId = 1;
        $chainId = 1;
        $existingAddress = '0x' . str_pad('123', 40, '0', STR_PAD_LEFT);

        // Create existing deposit address
        DepositAddress::create([
            'user_id' => $userId,
            'chain_id' => $chainId,
            'address' => $existingAddress,
            'private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('a', 64)),
        ]);

        $address = $this->walletService->createDepositAddress($userId, $chainId);

        // Should return existing address
        $this->assertEquals($existingAddress, $address);

        // Should not create duplicate
        $count = DepositAddress::where('user_id', $userId)
            ->where('chain_id', $chainId)
            ->count();

        $this->assertEquals(1, $count);
    }

    /**
     * Test createDepositAddress encrypts private key
     */
    public function test_create_deposit_address_encrypts_private_key()
    {
        $userId = 1;
        $chainId = 1;

        $address = $this->walletService->createDepositAddress($userId, $chainId);

        $depositAddress = DepositAddress::where('address', $address)->first();

        // Verify encrypted private key exists
        $this->assertNotNull($depositAddress->private_key_encrypted);

        // Verify it can be decrypted
        $decrypted = Crypt::decryptString($depositAddress->private_key_encrypted);
        $this->assertStringStartsWith('0x', $decrypted);
        $this->assertEquals(66, strlen($decrypted));
    }

    /**
     * Test createDepositAddress with different users creates different addresses
     */
    public function test_create_deposit_address_creates_different_addresses_for_different_users()
    {
        $chainId = 1;

        $address1 = $this->walletService->createDepositAddress(1, $chainId);
        $address2 = $this->walletService->createDepositAddress(2, $chainId);

        $this->assertNotEquals($address1, $address2);
    }

    /**
     * Test createDepositAddress with different chains creates different addresses
     */
    public function test_create_deposit_address_creates_different_addresses_for_different_chains()
    {
        $userId = 1;

        $address1 = $this->walletService->createDepositAddress($userId, 1);
        $address2 = $this->walletService->createDepositAddress($userId, 56);

        $this->assertNotEquals($address1, $address2);
    }

    /**
     * Test getPrivateKey returns correct decrypted private key
     */
    public function test_get_private_key_returns_decrypted_key()
    {
        $userId = 1;
        $chainId = 1;
        $privateKey = '0x' . str_repeat('a', 64);
        $address = '0x' . str_pad('123', 40, '0', STR_PAD_LEFT);

        DepositAddress::create([
            'user_id' => $userId,
            'chain_id' => $chainId,
            'address' => $address,
            'private_key_encrypted' => Crypt::encryptString($privateKey),
        ]);

        $retrievedKey = $this->walletService->getPrivateKey($address, $chainId);

        $this->assertEquals($privateKey, $retrievedKey);
    }

    /**
     * Test getPrivateKey throws exception when address not found
     */
    public function test_get_private_key_throws_exception_when_address_not_found()
    {
        $this->expectException(ModelNotFoundException::class);

        $this->walletService->getPrivateKey('0x' . str_repeat('0', 40), 1);
    }

    /**
     * Test getPrivateKey throws exception when chain_id does not match
     */
    public function test_get_private_key_throws_exception_when_chain_id_mismatch()
    {
        $userId = 1;
        $chainId = 1;
        $privateKey = '0x' . str_repeat('a', 64);
        $address = '0x' . str_pad('123', 40, '0', STR_PAD_LEFT);

        DepositAddress::create([
            'user_id' => $userId,
            'chain_id' => $chainId,
            'address' => $address,
            'private_key_encrypted' => Crypt::encryptString($privateKey),
        ]);

        $this->expectException(ModelNotFoundException::class);

        // Try to get with different chain_id
        $this->walletService->getPrivateKey($address, 999);
    }

    /**
     * Test getPrivateKey correctly decrypts multiple different keys
     */
    public function test_get_private_key_decrypts_multiple_different_keys()
    {
        $testData = [
            ['address' => '0x' . str_pad('111', 40, '0', STR_PAD_LEFT), 'key' => '0x' . str_repeat('1', 64)],
            ['address' => '0x' . str_pad('222', 40, '0', STR_PAD_LEFT), 'key' => '0x' . str_repeat('2', 64)],
            ['address' => '0x' . str_pad('333', 40, '0', STR_PAD_LEFT), 'key' => '0x' . str_repeat('3', 64)],
        ];

        $chainId = 1;

        foreach ($testData as $data) {
            DepositAddress::create([
                'user_id' => 1,
                'chain_id' => $chainId,
                'address' => $data['address'],
                'private_key_encrypted' => Crypt::encryptString($data['key']),
            ]);
        }

        foreach ($testData as $data) {
            $retrievedKey = $this->walletService->getPrivateKey($data['address'], $chainId);
            $this->assertEquals($data['key'], $retrievedKey);
        }
    }

    /**
     * Test getMasterWallet returns correct wallet data
     */
    public function test_get_master_wallet_returns_correct_data()
    {
        $chainId = 1;
        $masterAddress = '0x' . str_pad('master', 40, '0', STR_PAD_LEFT);
        $masterKey = '0x' . str_repeat('f', 64);

        Chain::create([
            'chain_id' => $chainId,
            'name' => 'Ethereum',
            'rpc_url' => 'https://eth.example.com',
            'master_wallet_address' => $masterAddress,
            'master_private_key_encrypted' => Crypt::encryptString($masterKey),
            'hot_wallet_address' => '0x' . str_repeat('0', 40),
            'native_symbol' => 'ETH',
            'gas_amount_wei' => '21000000000000000',
            'gas_limit_token_transfer' => 100000,
            'is_active' => true,
        ]);

        $wallet = $this->walletService->getMasterWallet($chainId);

        $this->assertIsArray($wallet);
        $this->assertArrayHasKey('address', $wallet);
        $this->assertArrayHasKey('privateKey', $wallet);
        $this->assertEquals($masterAddress, $wallet['address']);
        $this->assertEquals($masterKey, $wallet['privateKey']);
    }

    /**
     * Test getMasterWallet throws exception when chain not found
     */
    public function test_get_master_wallet_throws_exception_when_chain_not_found()
    {
        $this->expectException(ModelNotFoundException::class);

        $this->walletService->getMasterWallet(999);
    }

    /**
     * Test getMasterWallet decrypts private key correctly
     */
    public function test_get_master_wallet_decrypts_private_key()
    {
        $chainId = 1;
        $masterKey = '0xabcdef1234567890abcdef1234567890abcdef1234567890abcdef1234567890';

        Chain::create([
            'chain_id' => $chainId,
            'name' => 'Ethereum',
            'rpc_url' => 'https://eth.example.com',
            'master_wallet_address' => '0x' . str_repeat('0', 40),
            'master_private_key_encrypted' => Crypt::encryptString($masterKey),
            'hot_wallet_address' => '0x' . str_repeat('0', 40),
            'native_symbol' => 'ETH',
            'gas_amount_wei' => '21000000000000000',
            'gas_limit_token_transfer' => 100000,
            'is_active' => true,
        ]);

        $wallet = $this->walletService->getMasterWallet($chainId);

        $this->assertEquals($masterKey, $wallet['privateKey']);
        $this->assertMatchesRegularExpression('/^0x[a-f0-9]{64}$/i', $wallet['privateKey']);
    }

    /**
     * Test getUserDepositAddresses returns all addresses for a user
     */
    public function test_get_user_deposit_addresses_returns_all_user_addresses()
    {
        $userId = 1;

        // Create deposit addresses for the user on different chains
        $addresses = [
            ['chain_id' => 1, 'address' => '0x' . str_pad('111', 40, '0', STR_PAD_LEFT)],
            ['chain_id' => 56, 'address' => '0x' . str_pad('222', 40, '0', STR_PAD_LEFT)],
            ['chain_id' => 137, 'address' => '0x' . str_pad('333', 40, '0', STR_PAD_LEFT)],
        ];

        foreach ($addresses as $data) {
            DepositAddress::create([
                'user_id' => $userId,
                'chain_id' => $data['chain_id'],
                'address' => $data['address'],
                'private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('a', 64)),
            ]);
        }

        // Create address for different user (should not be included)
        DepositAddress::create([
            'user_id' => 2,
            'chain_id' => 1,
            'address' => '0x' . str_pad('999', 40, '0', STR_PAD_LEFT),
            'private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('b', 64)),
        ]);

        $result = $this->walletService->getUserDepositAddresses($userId);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(3, $result);

        $resultAddresses = $result->pluck('address')->toArray();
        foreach ($addresses as $data) {
            $this->assertContains($data['address'], $resultAddresses);
        }
    }

    /**
     * Test getUserDepositAddresses returns empty collection when user has no addresses
     */
    public function test_get_user_deposit_addresses_returns_empty_when_no_addresses()
    {
        $result = $this->walletService->getUserDepositAddresses(999);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
        $this->assertTrue($result->isEmpty());
    }

    /**
     * Test getUserDepositAddresses includes chain relationship
     */
    public function test_get_user_deposit_addresses_includes_chain_relationship()
    {
        $userId = 1;
        $chainId = 1;

        // Create chain
        Chain::create([
            'chain_id' => $chainId,
            'name' => 'Ethereum',
            'rpc_url' => 'https://eth.example.com',
            'master_wallet_address' => '0x' . str_repeat('0', 40),
            'master_private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('a', 64)),
            'hot_wallet_address' => '0x' . str_repeat('0', 40),
            'native_symbol' => 'ETH',
            'gas_amount_wei' => '21000000000000000',
            'gas_limit_token_transfer' => 100000,
            'is_active' => true,
        ]);

        // Create deposit address
        DepositAddress::create([
            'user_id' => $userId,
            'chain_id' => $chainId,
            'address' => '0x' . str_pad('111', 40, '0', STR_PAD_LEFT),
            'private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('a', 64)),
        ]);

        $result = $this->walletService->getUserDepositAddresses($userId);

        $this->assertCount(1, $result);
        $this->assertTrue($result->first()->relationLoaded('chain'));
        $this->assertEquals('Ethereum', $result->first()->chain->name);
    }

    /**
     * Test getAllDepositAddresses returns all addresses without filter
     */
    public function test_get_all_deposit_addresses_returns_all_without_filter()
    {
        // Create deposit addresses for different users and chains
        $addresses = [
            ['user_id' => 1, 'chain_id' => 1],
            ['user_id' => 1, 'chain_id' => 56],
            ['user_id' => 2, 'chain_id' => 1],
            ['user_id' => 2, 'chain_id' => 137],
        ];

        foreach ($addresses as $data) {
            DepositAddress::create([
                'user_id' => $data['user_id'],
                'chain_id' => $data['chain_id'],
                'address' => '0x' . str_pad(uniqid(), 40, '0', STR_PAD_LEFT),
                'private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('a', 64)),
            ]);
        }

        $result = $this->walletService->getAllDepositAddresses();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(4, $result);
    }

    /**
     * Test getAllDepositAddresses filters by chain_id
     */
    public function test_get_all_deposit_addresses_filters_by_chain_id()
    {
        $targetChainId = 1;

        // Create deposit addresses for different chains
        $addresses = [
            ['user_id' => 1, 'chain_id' => 1],
            ['user_id' => 2, 'chain_id' => 1],
            ['user_id' => 3, 'chain_id' => 56],
            ['user_id' => 4, 'chain_id' => 137],
        ];

        foreach ($addresses as $data) {
            DepositAddress::create([
                'user_id' => $data['user_id'],
                'chain_id' => $data['chain_id'],
                'address' => '0x' . str_pad(uniqid(), 40, '0', STR_PAD_LEFT),
                'private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('a', 64)),
            ]);
        }

        $result = $this->walletService->getAllDepositAddresses($targetChainId);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(2, $result);

        // Verify all results are for the target chain
        foreach ($result as $address) {
            $this->assertEquals($targetChainId, $address->chain_id);
        }
    }

    /**
     * Test getAllDepositAddresses returns empty collection when no addresses exist
     */
    public function test_get_all_deposit_addresses_returns_empty_when_none_exist()
    {
        $result = $this->walletService->getAllDepositAddresses();

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
        $this->assertTrue($result->isEmpty());
    }

    /**
     * Test getAllDepositAddresses returns empty collection when filtered chain has no addresses
     */
    public function test_get_all_deposit_addresses_returns_empty_for_nonexistent_chain()
    {
        // Create address on chain 1
        DepositAddress::create([
            'user_id' => 1,
            'chain_id' => 1,
            'address' => '0x' . str_pad('111', 40, '0', STR_PAD_LEFT),
            'private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('a', 64)),
        ]);

        // Query for chain 999
        $result = $this->walletService->getAllDepositAddresses(999);

        $this->assertInstanceOf(Collection::class, $result);
        $this->assertCount(0, $result);
        $this->assertTrue($result->isEmpty());
    }

    /**
     * Test edge case: createDepositAddress with user_id 0
     */
    public function test_create_deposit_address_with_zero_user_id()
    {
        $userId = 0;
        $chainId = 1;

        $address = $this->walletService->createDepositAddress($userId, $chainId);

        $this->assertStringStartsWith('0x', $address);

        $this->assertDatabaseHas('sweeper_deposit_addresses', [
            'user_id' => $userId,
            'chain_id' => $chainId,
            'address' => $address,
        ]);
    }

    /**
     * Test edge case: very large user_id
     */
    public function test_create_deposit_address_with_large_user_id()
    {
        $userId = PHP_INT_MAX;
        $chainId = 1;

        $address = $this->walletService->createDepositAddress($userId, $chainId);

        $this->assertStringStartsWith('0x', $address);

        $this->assertDatabaseHas('sweeper_deposit_addresses', [
            'user_id' => $userId,
            'chain_id' => $chainId,
        ]);
    }

    /**
     * Test that private keys remain consistent through encryption/decryption cycle
     */
    public function test_encryption_decryption_cycle_maintains_key_integrity()
    {
        $originalKeys = [
            '0x1234567890abcdef1234567890abcdef1234567890abcdef1234567890abcdef',
            '0xfedcba0987654321fedcba0987654321fedcba0987654321fedcba0987654321',
            '0x0000000000000000000000000000000000000000000000000000000000000001',
            '0xffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffffff',
        ];

        foreach ($originalKeys as $index => $originalKey) {
            $encrypted = Crypt::encryptString($originalKey);
            $decrypted = Crypt::decryptString($encrypted);

            $this->assertEquals($originalKey, $decrypted, "Key {$index} did not survive encryption/decryption cycle");
        }
    }

    /**
     * Test concurrent address creation for same user and chain
     */
    public function test_concurrent_address_creation_returns_same_address()
    {
        $userId = 1;
        $chainId = 1;

        // First call creates the address
        $address1 = $this->walletService->createDepositAddress($userId, $chainId);

        // Second call should return the same address
        $address2 = $this->walletService->createDepositAddress($userId, $chainId);

        $this->assertEquals($address1, $address2);

        // Verify only one record exists
        $count = DepositAddress::where('user_id', $userId)
            ->where('chain_id', $chainId)
            ->count();

        $this->assertEquals(1, $count);
    }

    /**
     * Test address format is lowercase
     */
    public function test_generated_addresses_are_lowercase()
    {
        $wallet = $this->walletService->generateWallet();

        // Ethereum addresses should be lowercase (non-checksummed)
        $addressWithoutPrefix = substr($wallet['address'], 2);
        $this->assertEquals(strtolower($addressWithoutPrefix), $addressWithoutPrefix);
    }

    /**
     * Test private key format consistency
     */
    public function test_private_key_padding_is_correct()
    {
        // Generate multiple wallets to test padding
        for ($i = 0; $i < 10; $i++) {
            $wallet = $this->walletService->generateWallet();
            $privateKey = substr($wallet['privateKey'], 2); // Remove 0x

            // Should always be 64 characters (properly padded)
            $this->assertEquals(64, strlen($privateKey), "Private key should always be 64 hex chars");
        }
    }

    /**
     * Test getUserDepositAddresses with specific user returns correct subset
     */
    public function test_get_user_deposit_addresses_filters_correctly()
    {
        $user1Id = 1;
        $user2Id = 2;

        // Create addresses for user 1
        for ($i = 0; $i < 3; $i++) {
            DepositAddress::create([
                'user_id' => $user1Id,
                'chain_id' => $i + 1,
                'address' => '0x' . str_pad("user1_{$i}", 40, '0', STR_PAD_LEFT),
                'private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('a', 64)),
            ]);
        }

        // Create addresses for user 2
        for ($i = 0; $i < 2; $i++) {
            DepositAddress::create([
                'user_id' => $user2Id,
                'chain_id' => $i + 1,
                'address' => '0x' . str_pad("user2_{$i}", 40, '0', STR_PAD_LEFT),
                'private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('b', 64)),
            ]);
        }

        $user1Addresses = $this->walletService->getUserDepositAddresses($user1Id);
        $user2Addresses = $this->walletService->getUserDepositAddresses($user2Id);

        $this->assertCount(3, $user1Addresses);
        $this->assertCount(2, $user2Addresses);

        // Verify no overlap
        $user1AddressList = $user1Addresses->pluck('address')->toArray();
        $user2AddressList = $user2Addresses->pluck('address')->toArray();

        $this->assertEmpty(array_intersect($user1AddressList, $user2AddressList));
    }
}
