<?php

namespace Multicoin\TokenSweeper\Tests\Unit\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Mockery;
use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Models\DepositAddress;
use Multicoin\TokenSweeper\Models\PendingSweep;
use Multicoin\TokenSweeper\Models\SweepLog;
use Multicoin\TokenSweeper\Models\Token;
use Multicoin\TokenSweeper\Services\SweeperService;
use Multicoin\TokenSweeper\Services\TransactionSignerService;
use Multicoin\TokenSweeper\Services\WalletService;
use Multicoin\TokenSweeper\Services\Web3Service;
use Multicoin\TokenSweeper\Support\Quantity;
use Multicoin\TokenSweeper\Tests\TestCase;

class SweeperServiceMoneyPathTest extends TestCase
{
    private const HOT_WALLET = '0x3535353535353535353535353535353535353535';

    private const TOKEN = '0xdAC17F958D2ee523a2206206994597C13D831ec7';

    private const DEPOSIT = '0x1111111111111111111111111111111111111111';

    private const ONE_HUNDRED_TOKENS = '100000000000000000000';

    private Chain $chain;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('token-sweeper.monitoring.post_funding_delay_seconds', 0);
        Config::set('token-sweeper.monitoring.max_retry_attempts', 3);

        $this->chain = Chain::create([
            'chain_id' => 1,
            'name' => 'Ethereum',
            'rpc_url' => 'https://eth.example.com',
            'master_wallet_address' => '0x' . str_repeat('a', 40),
            'master_private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('1', 64)),
            'hot_wallet_address' => self::HOT_WALLET,
            'native_symbol' => 'ETH',
            'gas_amount_wei' => '2000000000000000',
            'gas_limit_token_transfer' => 100000,
            'is_active' => true,
        ]);

        Token::create([
            'chain_id' => 1,
            'symbol' => 'DAI',
            'name' => 'Dai',
            'contract_address' => self::TOKEN,
            'decimals' => 18,
            'is_active' => true,
        ]);

        DepositAddress::create([
            'user_id' => 1,
            'chain_id' => 1,
            'address' => self::DEPOSIT,
            'private_key_encrypted' => Crypt::encryptString('0x' . str_repeat('2', 64)),
        ]);
    }

    protected function tearDown(): void
    {
        Cache::flush();
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_does_not_start_a_second_sweep_while_one_is_in_progress(): void
    {
        PendingSweep::create([
            'deposit_address' => self::DEPOSIT,
            'chain_id' => 1,
            'token_address' => strtolower(self::TOKEN),
            'token_symbol' => 'DAI',
            'amount' => self::ONE_HUNDRED_TOKENS,
            'status' => 'funding',
        ]);

        $web3 = Mockery::mock(Web3Service::class);
        $web3->shouldNotReceive('sendRawTransaction');
        $web3->shouldNotReceive('callContract');
        $this->app->instance(Web3Service::class, $web3);

        $result = $this->sweeper()->processSweep(self::DEPOSIT, strtolower(self::TOKEN), 1);

        $this->assertTrue($result);
        $this->assertSame(1, PendingSweep::count());
        $this->assertSame('funding', PendingSweep::first()->status);
    }

    /** @test */
    public function it_does_not_fund_gas_when_the_token_balance_is_zero(): void
    {
        $web3 = Mockery::mock(Web3Service::class);
        $web3->shouldReceive('callContract')->once()->andReturn('0x0');
        $web3->shouldNotReceive('sendRawTransaction');
        $web3->shouldNotReceive('getBalance');
        $this->app->instance(Web3Service::class, $web3);

        $result = $this->sweeper()->processSweep(self::DEPOSIT, strtolower(self::TOKEN), 1);

        $this->assertFalse($result);
        $this->assertSame(0, PendingSweep::count());
    }

    /** @test */
    public function it_treats_a_reverted_funding_transaction_as_failure_and_does_not_sweep(): void
    {
        $web3 = Mockery::mock(Web3Service::class);
        $web3->shouldReceive('callContract')->andReturn('0x56bc75e2d63100000');
        $web3->shouldReceive('getBalance')->andReturn('0x0');
        $web3->shouldReceive('getTransactionCount')->andReturn('0x0');
        $web3->shouldReceive('gasPrice')->andReturn('0x4a817c800');
        $web3->shouldReceive('sendRawTransaction')->once()->andReturn('0x' . str_repeat('ab', 32));
        $web3->shouldReceive('waitForConfirmation')->once()->andReturn(false);
        $this->app->instance(Web3Service::class, $web3);

        $signer = Mockery::mock(TransactionSignerService::class);
        $signer->shouldReceive('signTransaction')->andReturn('0xsigned');

        $result = $this->sweeper($signer)->processSweep(self::DEPOSIT, strtolower(self::TOKEN), 1);

        $this->assertFalse($result);
        $sweep = PendingSweep::first();
        $this->assertNotNull($sweep);
        $this->assertSame('failed', $sweep->status);
        $this->assertSame(self::ONE_HUNDRED_TOKENS, $sweep->amount);
        $this->assertSame(0, SweepLog::count());
        $this->assertSame(1, PendingSweep::count());
    }

    /** @test */
    public function it_records_the_exact_base_unit_amount_and_reuses_a_failed_sweep(): void
    {
        $failed = PendingSweep::create([
            'deposit_address' => strtolower(self::DEPOSIT),
            'chain_id' => 1,
            'token_address' => strtolower(self::TOKEN),
            'token_symbol' => 'DAI',
            'amount' => '0',
            'status' => 'failed',
            'retry_count' => 1,
            'error_message' => 'Funding transaction reverted',
        ]);

        $signed = [];
        $signer = Mockery::mock(TransactionSignerService::class);
        $signer->shouldReceive('signTransaction')->once()->andReturnUsing(function (array $transaction) use (&$signed) {
            $signed[] = $transaction;

            return '0xsigned';
        });

        $sweepHash = '0x' . str_repeat('cd', 32);
        $web3 = Mockery::mock(Web3Service::class);
        $web3->shouldReceive('callContract')->andReturn('0x56bc75e2d63100000');
        $web3->shouldReceive('getBalance')->andReturn('0x71afd498d0000', '0x0');
        $web3->shouldReceive('getTransactionCount')->andReturn('0x0');
        $web3->shouldReceive('gasPrice')->andReturn('0x4a817c800');
        $web3->shouldReceive('sendRawTransaction')->once()->andReturn($sweepHash);
        $web3->shouldReceive('waitForConfirmation')->once()->andReturn(true);
        $this->app->instance(Web3Service::class, $web3);

        $result = $this->sweeper($signer)->processSweep(self::DEPOSIT, self::TOKEN, 1);

        $this->assertTrue($result);
        $this->assertSame(1, PendingSweep::count());
        $failed->refresh();
        $this->assertSame('completed', $failed->status);
        $this->assertSame(self::ONE_HUNDRED_TOKENS, $failed->amount);
        $this->assertSame($sweepHash, $failed->sweep_tx_hash);

        $log = SweepLog::first();
        $this->assertNotNull($log);
        $this->assertSame(self::ONE_HUNDRED_TOKENS, $log->amount);
        $this->assertNull($log->funding_tx_hash);

        $this->assertCount(1, $signed);
        $this->assertStringStartsWith('0xa9059cbb', $signed[0]['data']);
        $this->assertStringEndsWith(
            '0000000000000000000000000000000000000000000000056bc75e2d63100000',
            strtolower($signed[0]['data'])
        );
        $this->assertStringContainsString(
            str_pad(strtolower(substr(self::HOT_WALLET, 2)), 64, '0', STR_PAD_LEFT),
            strtolower($signed[0]['data'])
        );
    }

    /** @test */
    public function it_does_not_fund_again_when_the_decimal_gas_threshold_is_already_met(): void
    {
        $signer = Mockery::mock(TransactionSignerService::class);
        $signer->shouldReceive('signTransaction')->once()->andReturn('0xsigned');

        $web3 = Mockery::mock(Web3Service::class);
        $web3->shouldReceive('callContract')->andReturn('0x56bc75e2d63100000');
        $web3->shouldReceive('getBalance')->andReturn('0x71afd498d0000', '0x0');
        $web3->shouldReceive('getTransactionCount')->andReturn('0x1');
        $web3->shouldReceive('gasPrice')->andReturn('0x4a817c800');
        $web3->shouldReceive('sendRawTransaction')->once()->andReturn('0x' . str_repeat('11', 32));
        $web3->shouldReceive('waitForConfirmation')->once()->andReturn(true);
        $this->app->instance(Web3Service::class, $web3);

        $this->assertTrue($this->sweeper($signer)->processSweep(self::DEPOSIT, self::TOKEN, 1));
        $this->assertNull(PendingSweep::first()->funding_tx_hash);
    }

    /** @test */
    public function it_refuses_to_fund_when_the_hot_wallet_is_missing(): void
    {
        $this->chain->update(['hot_wallet_address' => '']);

        $web3 = Mockery::mock(Web3Service::class);
        $web3->shouldNotReceive('sendRawTransaction');
        $web3->shouldNotReceive('getBalance');
        $this->app->instance(Web3Service::class, $web3);

        $this->assertFalse($this->sweeper()->processSweep(self::DEPOSIT, self::TOKEN, 1));
        $this->assertSame(0, PendingSweep::count());
    }

    /** @test */
    public function it_sweeps_remaining_gas_using_exact_wei_above_php_int_max(): void
    {
        $captured = null;
        $signer = Mockery::mock(TransactionSignerService::class);
        $signer->shouldReceive('signTransaction')->once()->withArgs(function (array $transaction) use (&$captured) {
            $captured = $transaction['value'];

            return true;
        })->andReturn('0xsigned');

        $web3 = Mockery::mock(Web3Service::class);
        $web3->shouldReceive('getBalance')->andReturn('0x8ac7230489e80000');
        $web3->shouldReceive('gasPrice')->andReturn('0x4a817c800');
        $web3->shouldReceive('getTransactionCount')->andReturn('0x1');
        $web3->shouldReceive('sendRawTransaction')->once()->andReturn('0x' . str_repeat('22', 32));

        $method = new \ReflectionMethod(SweeperService::class, 'sweepRemainingGas');
        $method->invoke($this->sweeper($signer), self::DEPOSIT, $this->chain, $web3);

        $this->assertNotNull($captured);
        $this->assertSame(0, Quantity::compare($captured, '9999580000000000000'));
    }

    private function sweeper(?TransactionSignerService $signer = null): SweeperService
    {
        return new SweeperService(new WalletService(), $signer ?? new TransactionSignerService());
    }
}
