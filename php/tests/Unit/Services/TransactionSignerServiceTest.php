<?php

namespace Multicoin\TokenSweeper\Tests\Unit\Services;

use Multicoin\TokenSweeper\Services\TransactionSignerService;
use PHPUnit\Framework\TestCase;

class TransactionSignerServiceTest extends TestCase
{
    private const PRIVATE_KEY = '0x4c0883a69102937d6231471b5dbb6204fe5129617082792ae468d01a3f362318';

    /**
     * Legacy EIP-155 vectors generated with ethers.js v6.
     * The funding value is the decimal wei string used in config (0.002 ETH),
     * which must not be RLP-encoded as if those digits were already hex.
     */
    private const FUNDING_TX = '0xf86b808504a817c80082520894353535353535353535353535353535353535353587071afd498d00008025a06e2dd78cb9ffbfdb7c0ee3aee6e8b4e933761616169b16936e13c7444d98930ea0215d80dbde69570cc444c66f7fb30124896147108b3b7471a31d2c00958c9132';

    private const SWEEP_TX = '0xf8ac808504a817c800830186a094dac17f958d2ee523a2206206994597c13d831ec780b844a9059cbb00000000000000000000000035353535353535353535353535353535353535350000000000000000000000000000000000000000000000056bc75e2d63100000820136a0c22cdb0ad5450aaabcdd1efd92a65a90005933854ca13356360be8995c45ee1ca0563b1bb6854705bf21e4e885259791a35f9722288108fe1eb20ce44dd0053a03';

    /** @test */
    public function it_signs_a_nonce_zero_funding_transaction_for_a_decimal_wei_value(): void
    {
        $signer = new TransactionSignerService();

        $raw = $signer->signTransaction([
            'nonce' => 0,
            'gasPrice' => 20000000000,
            'gasLimit' => 21000,
            'to' => '0x3535353535353535353535353535353535353535',
            'value' => '2000000000000000',
            'data' => '0x',
        ], self::PRIVATE_KEY, 1);

        $this->assertSame(self::FUNDING_TX, $raw);
        $this->assertSame(0, strlen(substr($raw, 2)) % 2);
    }

    /** @test */
    public function it_signs_a_polygon_token_sweep_whose_gas_limit_hex_has_an_odd_nibble(): void
    {
        $signer = new TransactionSignerService();
        $amount = str_pad('56bc75e2d63100000', 64, '0', STR_PAD_LEFT);
        $to = str_pad('3535353535353535353535353535353535353535', 64, '0', STR_PAD_LEFT);

        $raw = $signer->signTransaction([
            'nonce' => 0,
            'gasPrice' => '0x4a817c800',
            'gasLimit' => 100000,
            'to' => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
            'value' => '0x0',
            'data' => '0xa9059cbb' . $to . $amount,
        ], self::PRIVATE_KEY, 137);

        $this->assertSame(self::SWEEP_TX, $raw);
    }
}
