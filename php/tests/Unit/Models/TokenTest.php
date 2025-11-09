<?php

namespace Multicoin\TokenSweeper\Tests\Unit\Models;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Models\Token;
use Multicoin\TokenSweeper\Tests\TestCase;

class TokenTest extends TestCase
{
    use RefreshDatabase;

    protected Chain $chain;
    protected Token $token;

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

        $this->token = Token::create([
            'chain_id' => $this->chain->chain_id,
            'symbol' => 'USDT',
            'name' => 'Tether USD',
            'contract_address' => '0xdac17f958d2ee523a2206206994597c13d831ec7',
            'decimals' => 6,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function it_can_create_a_token()
    {
        $this->assertInstanceOf(Token::class, $this->token);
        $this->assertDatabaseHas('sweeper_tokens', [
            'symbol' => 'USDT',
            'name' => 'Tether USD',
        ]);
    }

    /** @test */
    public function it_has_correct_table_name()
    {
        $this->assertEquals('sweeper_tokens', $this->token->getTable());
    }

    /** @test */
    public function it_has_fillable_attributes()
    {
        $fillable = [
            'chain_id',
            'symbol',
            'name',
            'contract_address',
            'decimals',
            'is_active',
        ];

        $this->assertEquals($fillable, $this->token->getFillable());
    }

    /** @test */
    public function it_casts_chain_id_to_integer()
    {
        $this->assertIsInt($this->token->chain_id);
        $this->assertEquals(1, $this->token->chain_id);
    }

    /** @test */
    public function it_casts_decimals_to_integer()
    {
        $this->assertIsInt($this->token->decimals);
        $this->assertEquals(6, $this->token->decimals);
    }

    /** @test */
    public function it_casts_is_active_to_boolean()
    {
        $this->assertIsBool($this->token->is_active);
        $this->assertTrue($this->token->is_active);

        $inactiveToken = Token::create([
            'chain_id' => $this->chain->chain_id,
            'symbol' => 'OLD',
            'name' => 'Old Token',
            'contract_address' => '0x' . str_repeat('3', 40),
            'decimals' => 18,
            'is_active' => false,
        ]);

        $this->assertFalse($inactiveToken->is_active);
    }

    /** @test */
    public function it_belongs_to_a_chain()
    {
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $this->token->chain());
        $this->assertInstanceOf(Chain::class, $this->token->chain);
        $this->assertEquals($this->chain->id, $this->token->chain->id);
        $this->assertEquals('Ethereum Mainnet', $this->token->chain->name);
    }

    /** @test */
    public function scope_active_returns_only_active_tokens()
    {
        Token::create([
            'chain_id' => $this->chain->chain_id,
            'symbol' => 'INACTIVE',
            'name' => 'Inactive Token',
            'contract_address' => '0x' . str_repeat('b', 40),
            'decimals' => 18,
            'is_active' => false,
        ]);

        $activeTokens = Token::active()->get();

        $this->assertCount(1, $activeTokens);
        $this->assertEquals('USDT', $activeTokens->first()->symbol);
        $this->assertTrue($activeTokens->first()->is_active);
    }

    /** @test */
    public function scope_active_returns_empty_collection_when_no_active_tokens()
    {
        $this->token->update(['is_active' => false]);

        $activeTokens = Token::active()->get();

        $this->assertCount(0, $activeTokens);
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

        Token::create([
            'chain_id' => $anotherChain->chain_id,
            'symbol' => 'BUSD',
            'name' => 'Binance USD',
            'contract_address' => '0x' . str_repeat('c', 40),
            'decimals' => 18,
            'is_active' => true,
        ]);

        $ethereumTokens = Token::forChain(1)->get();
        $bscTokens = Token::forChain(56)->get();

        $this->assertCount(1, $ethereumTokens);
        $this->assertEquals('USDT', $ethereumTokens->first()->symbol);

        $this->assertCount(1, $bscTokens);
        $this->assertEquals('BUSD', $bscTokens->first()->symbol);
    }

    /** @test */
    public function scope_for_chain_returns_empty_collection_for_chain_without_tokens()
    {
        $tokens = Token::forChain(999)->get();

        $this->assertCount(0, $tokens);
    }

    /** @test */
    public function it_can_combine_scopes()
    {
        Token::create([
            'chain_id' => $this->chain->chain_id,
            'symbol' => 'INACTIVE',
            'name' => 'Inactive Token',
            'contract_address' => '0x' . str_repeat('d', 40),
            'decimals' => 18,
            'is_active' => false,
        ]);

        $activeChainTokens = Token::active()->forChain(1)->get();

        $this->assertCount(1, $activeChainTokens);
        $this->assertEquals('USDT', $activeChainTokens->first()->symbol);
    }

    /** @test */
    public function it_stores_all_required_token_information()
    {
        $this->assertEquals($this->chain->chain_id, $this->token->chain_id);
        $this->assertEquals('USDT', $this->token->symbol);
        $this->assertEquals('Tether USD', $this->token->name);
        $this->assertEquals('0xdac17f958d2ee523a2206206994597c13d831ec7', $this->token->contract_address);
        $this->assertEquals(6, $this->token->decimals);
        $this->assertTrue($this->token->is_active);
    }

    /** @test */
    public function it_can_update_token_attributes()
    {
        $this->token->update([
            'name' => 'Updated Tether',
            'is_active' => false,
            'decimals' => 18,
        ]);

        $this->token->refresh();

        $this->assertEquals('Updated Tether', $this->token->name);
        $this->assertFalse($this->token->is_active);
        $this->assertEquals(18, $this->token->decimals);
    }

    /** @test */
    public function it_can_delete_a_token()
    {
        $tokenId = $this->token->id;
        $this->token->delete();

        $this->assertDatabaseMissing('sweeper_tokens', ['id' => $tokenId]);
    }

    /** @test */
    public function it_handles_tokens_with_different_decimal_places()
    {
        $token18 = Token::create([
            'chain_id' => $this->chain->chain_id,
            'symbol' => 'DAI',
            'name' => 'DAI Stablecoin',
            'contract_address' => '0x' . str_repeat('e', 40),
            'decimals' => 18,
            'is_active' => true,
        ]);

        $token8 = Token::create([
            'chain_id' => $this->chain->chain_id,
            'symbol' => 'WBTC',
            'name' => 'Wrapped Bitcoin',
            'contract_address' => '0x' . str_repeat('f', 40),
            'decimals' => 8,
            'is_active' => true,
        ]);

        $this->assertEquals(6, $this->token->decimals);
        $this->assertEquals(18, $token18->decimals);
        $this->assertEquals(8, $token8->decimals);
    }
}
