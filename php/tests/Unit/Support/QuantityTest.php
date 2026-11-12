<?php

namespace Multicoin\TokenSweeper\Tests\Unit\Support;

use Multicoin\TokenSweeper\Support\MasterNonceLock;
use Multicoin\TokenSweeper\Support\Quantity;
use PHPUnit\Framework\TestCase;

class QuantityTest extends TestCase
{
    /** @test */
    public function it_parses_decimal_wei_and_hex_wei_as_the_same_integer(): void
    {
        $decimal = '2000000000000000';
        $hex = '0x71afd498d0000';

        $this->assertSame(0, Quantity::compare($decimal, $hex));
        $this->assertSame($decimal, Quantity::toDecimal($hex));
        $this->assertSame($decimal, Quantity::toDecimal($decimal));
    }

    /** @test */
    public function it_preserves_token_amounts_above_php_int_max(): void
    {
        $amount = '100000000000000000000';

        $this->assertGreaterThan(PHP_INT_MAX, (float) $amount);
        $this->assertSame($amount, Quantity::toDecimal('0x56bc75e2d63100000'));
        $this->assertSame(
            '0000000000000000000000000000000000000000000000056bc75e2d63100000',
            Quantity::decimalToHex($amount, false, 32)
        );
    }

    /** @test */
    public function it_subtracts_gas_cost_from_a_balance_above_php_int_max(): void
    {
        $balance = '10000000000000000000';
        $gasCost = Quantity::mul('21000', '20000000000');

        $this->assertSame('420000000000000', $gasCost);
        $this->assertSame('9999580000000000000', Quantity::sub($balance, $gasCost));
    }

    /** @test */
    public function it_keeps_integer_results_when_bcmath_scale_is_nonzero(): void
    {
        $previous = bcscale(2);

        try {
            $amount = Quantity::hexToDecimal('0xa');

            $this->assertSame('10', $amount);
            $this->assertSame(0, Quantity::compare($amount, '10'));
            $this->assertSame('30', Quantity::add('10', '20'));
            $this->assertSame('5', Quantity::sub('25', '20'));
            $this->assertSame('200', Quantity::mul('10', '20'));
            $this->assertSame(
                '0000000000000000000000000000000000000000000000056bc75e2d63100000',
                Quantity::decimalToHex('100000000000000000000', false, 32)
            );
        } finally {
            bcscale($previous);
        }
    }

    /** @test */
    public function it_holds_the_master_nonce_lock_longer_than_the_rpc_calls_inside_it(): void
    {
        $this->assertGreaterThan(30 * 3, MasterNonceLock::HOLD_SECONDS);
    }

    /** @test */
    public function it_rejects_null_and_blank_quantities(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Quantity::toDecimal('   ');
    }
}
