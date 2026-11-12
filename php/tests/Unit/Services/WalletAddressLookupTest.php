<?php

namespace Multicoin\TokenSweeper\Tests\Unit\Services;

use Illuminate\Support\Facades\Crypt;
use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Models\DepositAddress;
use Multicoin\TokenSweeper\Services\WalletService;
use Multicoin\TokenSweeper\Tests\TestCase;

class WalletAddressLookupTest extends TestCase
{
    /** @test */
    public function it_finds_deposit_keys_for_checksummed_addresses(): void
    {
        $privateKey = '0x' . str_repeat('ab', 32);

        Chain::create([
            'chain_id' => 1,
            'name' => 'Ethereum',
            'rpc_url' => 'https://eth.example.com',
            'master_wallet_address' => '0x' . str_repeat('1', 40),
            'master_private_key_encrypted' => Crypt::encryptString($privateKey),
            'hot_wallet_address' => '0x' . str_repeat('2', 40),
            'native_symbol' => 'ETH',
            'gas_amount_wei' => '2000000000000000',
            'gas_limit_token_transfer' => 100000,
            'is_active' => true,
        ]);

        DepositAddress::create([
            'user_id' => 7,
            'chain_id' => 1,
            'address' => '0x5aaeb6053f3e94c9b9a09f33669435e7ef1beaed',
            'private_key_encrypted' => Crypt::encryptString($privateKey),
        ]);

        $resolved = (new WalletService())->getPrivateKey(
            '0x5aAeb6053F3E94C9b9A09f33669435E7Ef1BeAed',
            1
        );

        $this->assertSame($privateKey, $resolved);
    }
}
