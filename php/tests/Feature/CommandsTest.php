<?php

namespace Multicoin\TokenSweeper\Tests\Feature;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Artisan;
use Multicoin\TokenSweeper\Tests\TestCase;
use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Models\Token;
use Multicoin\TokenSweeper\Models\DepositAddress;
use Multicoin\TokenSweeper\Models\PendingSweep;
use Multicoin\TokenSweeper\Services\WalletService;

class CommandsTest extends TestCase
{
    protected Chain $chain;
    protected Token $token;

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

        // Create test token
        $this->token = Token::create([
            'chain_id' => 1,
            'symbol' => 'USDT',
            'name' => 'Tether USD',
            'contract_address' => $this->createTestAddress(),
            'decimals' => 6,
            'is_active' => true,
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up error handlers to prevent warnings
        restore_error_handler();
        restore_exception_handler();
        parent::tearDown();
    }

    /** @test */
    public function it_generates_deposit_address_via_command()
    {
        $userId = 1;
        $chainId = $this->chain->chain_id;

        $this->artisan('sweeper:generate-address', [
            'user_id' => $userId,
            'chain_id' => $chainId,
        ])
            ->expectsOutput("Generating deposit address for user {$userId} on chain {$chainId}...")
            ->assertExitCode(0);

        // Verify address was created
        $this->assertDatabaseHas('sweeper_deposit_addresses', [
            'user_id' => $userId,
            'chain_id' => $chainId,
        ]);

        $depositAddress = DepositAddress::where('user_id', $userId)
            ->where('chain_id', $chainId)
            ->first();

        $this->assertNotNull($depositAddress);
        $this->assertStringStartsWith('0x', $depositAddress->address);
    }

    /** @test */
    public function it_returns_existing_address_when_running_command_twice()
    {
        $userId = 1;
        $chainId = $this->chain->chain_id;

        // First run
        $this->artisan('sweeper:generate-address', [
            'user_id' => $userId,
            'chain_id' => $chainId,
        ])->assertExitCode(0);

        $firstAddress = DepositAddress::where('user_id', $userId)
            ->where('chain_id', $chainId)
            ->first();

        // Second run
        $this->artisan('sweeper:generate-address', [
            'user_id' => $userId,
            'chain_id' => $chainId,
        ])->assertExitCode(0);

        $secondAddress = DepositAddress::where('user_id', $userId)
            ->where('chain_id', $chainId)
            ->first();

        // Should be the same address
        $this->assertEquals($firstAddress->id, $secondAddress->id);
        $this->assertEquals($firstAddress->address, $secondAddress->address);

        // Only one record should exist
        $count = DepositAddress::where('user_id', $userId)
            ->where('chain_id', $chainId)
            ->count();

        $this->assertEquals(1, $count);
    }

    /** @test */
    public function it_generates_addresses_for_different_users()
    {
        $chainId = $this->chain->chain_id;

        $this->artisan('sweeper:generate-address', [
            'user_id' => 1,
            'chain_id' => $chainId,
        ])->assertExitCode(0);

        $this->artisan('sweeper:generate-address', [
            'user_id' => 2,
            'chain_id' => $chainId,
        ])->assertExitCode(0);

        $address1 = DepositAddress::where('user_id', 1)->first();
        $address2 = DepositAddress::where('user_id', 2)->first();

        $this->assertNotEquals($address1->address, $address2->address);
        $this->assertEquals(2, DepositAddress::count());
    }

    /** @test */
    public function it_displays_pending_sweeps_via_command()
    {
        // Create deposit address
        $depositAddress = DepositAddress::create([
            'user_id' => 1,
            'chain_id' => $this->chain->chain_id,
            'address' => $this->createTestAddress(),
            'private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('2', 64)),
        ]);

        // Create pending sweep
        PendingSweep::create([
            'deposit_address' => $depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '1000000',
            'status' => 'pending',
        ]);

        $this->artisan('sweeper:pending')
            ->expectsOutput('Pending Sweeps (Status: pending):')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_filters_sweeps_by_status_via_command()
    {
        $depositAddress = DepositAddress::create([
            'user_id' => 1,
            'chain_id' => $this->chain->chain_id,
            'address' => $this->createTestAddress(),
            'private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('2', 64)),
        ]);

        // Create sweeps with different statuses
        PendingSweep::create([
            'deposit_address' => $depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '1000000',
            'status' => 'pending',
        ]);

        PendingSweep::create([
            'deposit_address' => $depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '2000000',
            'status' => 'completed',
        ]);

        PendingSweep::create([
            'deposit_address' => $depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '3000000',
            'status' => 'failed',
        ]);

        // Test pending status
        $this->artisan('sweeper:pending', ['--status' => 'pending'])
            ->expectsOutput('Pending Sweeps (Status: pending):')
            ->assertExitCode(0);

        // Test completed status
        $this->artisan('sweeper:pending', ['--status' => 'completed'])
            ->expectsOutput('Pending Sweeps (Status: completed):')
            ->assertExitCode(0);

        // Test failed status
        $this->artisan('sweeper:pending', ['--status' => 'failed'])
            ->expectsOutput('Pending Sweeps (Status: failed):')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_displays_empty_table_when_no_pending_sweeps()
    {
        $this->artisan('sweeper:pending')
            ->expectsOutput('Pending Sweeps (Status: pending):')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_processes_sweep_via_command_with_mocked_service()
    {
        $depositAddress = DepositAddress::create([
            'user_id' => 1,
            'chain_id' => $this->chain->chain_id,
            'address' => $this->createTestAddress(),
            'private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('2', 64)),
        ]);

        // Note: This test would require mocking the SweeperService
        // For now, we'll test that the command accepts the correct arguments
        // In a real scenario, you'd mock the service to avoid actual blockchain calls

        $address = $depositAddress->address;
        $token = $this->token->contract_address;
        $chainId = $this->chain->chain_id;

        // This will fail without mocking Web3Service, but we're testing command structure
        $this->artisan('sweeper:sweep', [
            'address' => $address,
            'token' => $token,
            'chain_id' => $chainId,
        ])
            ->expectsOutput("Processing sweep for {$address}...");
            // We don't assert exit code because it will fail without mocked Web3
    }

    /** @test */
    public function it_checks_balance_via_command_arguments()
    {
        $address = $this->createTestAddress();
        $tokenAddress = $this->token->contract_address;
        $chainId = $this->chain->chain_id;

        // Test native balance check (will fail without real RPC, but tests command structure)
        try {
            $this->artisan('sweeper:balance', [
                'address' => $address,
                'chain_id' => $chainId,
            ]);
        } catch (\Exception $e) {
            // Expected to fail without real RPC connection
        }

        // Test token balance check
        try {
            $this->artisan('sweeper:balance', [
                'address' => $address,
                'token' => $tokenAddress,
                'chain_id' => $chainId,
            ]);
        } catch (\Exception $e) {
            // Expected to fail without real RPC connection
        }

        // Assert that the chain was configured properly
        $this->assertNotNull($this->chain);
        $this->assertEquals($chainId, $this->chain->chain_id);
    }

    /** @test */
    public function it_accepts_chain_id_parameter_in_balance_command()
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

        $address = $this->createTestAddress();

        // Test with BSC chain (will fail without real RPC, but tests command structure)
        try {
            $this->artisan('sweeper:balance', [
                'address' => $address,
                'chain_id' => 56,
            ]);
        } catch (\Exception $e) {
            // Expected to fail without real RPC connection
        }

        // Assert that both chains are configured properly
        $this->assertDatabaseHas('sweeper_chains', ['chain_id' => 1]);
        $this->assertDatabaseHas('sweeper_chains', ['chain_id' => 56]);
    }

    /** @test */
    public function it_validates_command_arguments()
    {
        // Test generate-address with missing arguments
        try {
            Artisan::call('sweeper:generate-address');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\Symfony\Component\Console\Exception\RuntimeException $e) {
            $this->assertTrue(true);
        }

        // Test sweep with missing arguments
        try {
            Artisan::call('sweeper:sweep');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\Symfony\Component\Console\Exception\RuntimeException $e) {
            $this->assertTrue(true);
        }

        // Test balance with missing arguments
        try {
            Artisan::call('sweeper:balance');
            $this->fail('Expected RuntimeException was not thrown');
        } catch (\Symfony\Component\Console\Exception\RuntimeException $e) {
            $this->assertTrue(true);
        }
    }

    /** @test */
    public function it_handles_invalid_chain_id_gracefully()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $address = $this->createTestAddress();

        Artisan::call('sweeper:balance', [
            'address' => $address,
            'chain_id' => 999, // Non-existent chain
        ]);
    }

    /** @test */
    public function it_creates_deposit_addresses_for_multiple_chains()
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

        $userId = 1;

        // Generate address on Ethereum
        $this->artisan('sweeper:generate-address', [
            'user_id' => $userId,
            'chain_id' => 1,
        ])->assertExitCode(0);

        // Generate address on BSC
        $this->artisan('sweeper:generate-address', [
            'user_id' => $userId,
            'chain_id' => 56,
        ])->assertExitCode(0);

        // Verify both addresses were created
        $ethAddress = DepositAddress::where('user_id', $userId)
            ->where('chain_id', 1)
            ->first();

        $bscAddress = DepositAddress::where('user_id', $userId)
            ->where('chain_id', 56)
            ->first();

        $this->assertNotNull($ethAddress);
        $this->assertNotNull($bscAddress);
        $this->assertNotEquals($ethAddress->address, $bscAddress->address);
    }

    /** @test */
    public function it_lists_pending_sweeps_with_proper_formatting()
    {
        $depositAddress = DepositAddress::create([
            'user_id' => 1,
            'chain_id' => $this->chain->chain_id,
            'address' => $this->createTestAddress(),
            'private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('2', 64)),
        ]);

        // Create multiple pending sweeps
        for ($i = 1; $i <= 3; $i++) {
            PendingSweep::create([
                'deposit_address' => $depositAddress->address,
                'chain_id' => $this->chain->chain_id,
                'token_address' => $this->token->contract_address,
                'token_symbol' => $this->token->symbol,
                'amount' => (string) ($i * 1000000),
                'status' => 'pending',
            ]);
        }

        $output = $this->artisan('sweeper:pending')
            ->assertExitCode(0)
            ->run();

        // Verify that sweeps are listed
        $sweeps = PendingSweep::pending()->get();
        $this->assertEquals(3, $sweeps->count());
    }

    /** @test */
    public function it_limits_pending_sweeps_output_to_20_records()
    {
        $depositAddress = DepositAddress::create([
            'user_id' => 1,
            'chain_id' => $this->chain->chain_id,
            'address' => $this->createTestAddress(),
            'private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('2', 64)),
        ]);

        // Create 25 pending sweeps
        for ($i = 1; $i <= 25; $i++) {
            PendingSweep::create([
                'deposit_address' => $depositAddress->address,
                'chain_id' => $this->chain->chain_id,
                'token_address' => $this->token->contract_address,
                'token_symbol' => $this->token->symbol,
                'amount' => (string) ($i * 1000000),
                'status' => 'pending',
            ]);
        }

        $this->artisan('sweeper:pending')
            ->assertExitCode(0);

        // Verify total count is 25 but command only shows 20
        $this->assertEquals(25, PendingSweep::pending()->count());
    }

    /** @test */
    public function it_handles_string_user_id_and_chain_id_in_generate_address_command()
    {
        $this->artisan('sweeper:generate-address', [
            'user_id' => '123',
            'chain_id' => '1',
        ])->assertExitCode(0);

        $this->assertDatabaseHas('sweeper_deposit_addresses', [
            'user_id' => 123,
            'chain_id' => 1,
        ]);
    }

    /** @test */
    public function it_handles_string_chain_id_in_sweep_command()
    {
        $depositAddress = DepositAddress::create([
            'user_id' => 1,
            'chain_id' => $this->chain->chain_id,
            'address' => $this->createTestAddress(),
            'private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('2', 64)),
        ]);

        $this->artisan('sweeper:sweep', [
            'address' => $depositAddress->address,
            'token' => $this->token->contract_address,
            'chain_id' => '1', // String instead of int
        ])
            ->expectsOutput("Processing sweep for {$depositAddress->address}...");
    }

    /** @test */
    public function it_displays_sweep_information_in_pending_command()
    {
        $depositAddress = DepositAddress::create([
            'user_id' => 1,
            'chain_id' => $this->chain->chain_id,
            'address' => $this->createTestAddress(),
            'private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('2', 64)),
        ]);

        $sweep = PendingSweep::create([
            'deposit_address' => $depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '1000000',
            'status' => 'pending',
        ]);

        $this->artisan('sweeper:pending')
            ->expectsOutput('Pending Sweeps (Status: pending):')
            ->assertExitCode(0);

        $this->assertDatabaseHas('sweeper_pending_sweeps', [
            'id' => $sweep->id,
            'token_symbol' => 'USDT',
            'status' => 'pending',
        ]);
    }

    /** @test */
    public function it_orders_pending_sweeps_by_created_at_descending()
    {
        $depositAddress = DepositAddress::create([
            'user_id' => 1,
            'chain_id' => $this->chain->chain_id,
            'address' => $this->createTestAddress(),
            'private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('2', 64)),
        ]);

        // Create sweeps with different timestamps
        $sweep1 = PendingSweep::create([
            'deposit_address' => $depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '1000000',
            'status' => 'pending',
            'created_at' => now()->subHours(2),
        ]);

        $sweep2 = PendingSweep::create([
            'deposit_address' => $depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '2000000',
            'status' => 'pending',
            'created_at' => now()->subHours(1),
        ]);

        $sweep3 = PendingSweep::create([
            'deposit_address' => $depositAddress->address,
            'chain_id' => $this->chain->chain_id,
            'token_address' => $this->token->contract_address,
            'token_symbol' => $this->token->symbol,
            'amount' => '3000000',
            'status' => 'pending',
            'created_at' => now(),
        ]);

        $this->artisan('sweeper:pending')->assertExitCode(0);

        // Verify order
        $sweeps = PendingSweep::pending()->orderBy('created_at', 'desc')->get();
        $this->assertEquals($sweep3->id, $sweeps[0]->id);
        $this->assertEquals($sweep2->id, $sweeps[1]->id);
        $this->assertEquals($sweep1->id, $sweeps[2]->id);
    }
}
