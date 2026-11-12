<?php

namespace Multicoin\TokenSweeper\Tests\Unit\Support;

use Multicoin\TokenSweeper\Support\Rlp;
use PHPUnit\Framework\TestCase;

class RlpTest extends TestCase
{
    /** @test */
    public function it_encodes_zero_as_an_empty_byte_string(): void
    {
        $this->assertSame('80', Rlp::encodeInteger(0));
        $this->assertSame('80', Rlp::encodeInteger('0x0'));
        $this->assertSame('80', Rlp::encodeInteger('0'));
    }

    /** @test */
    public function it_encodes_small_and_odd_nibble_integers_canonically(): void
    {
        $this->assertSame('01', Rlp::encodeInteger(1));
        $this->assertSame('81ff', Rlp::encodeInteger(255));
        $this->assertSame('820100', Rlp::encodeInteger(256));
        $this->assertSame('820100', Rlp::encodeInteger('0x0100'));
    }

    /** @test */
    public function it_strips_leading_zero_bytes_from_integers(): void
    {
        $this->assertSame(Rlp::encodeInteger(255), Rlp::encodeInteger('0x00ff'));
        $this->assertSame('81ff', Rlp::encodeInteger('0x00ff'));
    }

    /** @test */
    public function it_encodes_byte_strings_longer_than_55_bytes(): void
    {
        $payload = str_repeat('ab', 68);

        $this->assertSame('b844' . $payload, Rlp::encodeBytes($payload));
    }

    /** @test */
    public function it_encodes_a_256_byte_string_with_a_two_byte_length(): void
    {
        $payload = str_repeat('11', 256);

        $this->assertSame('b90100' . $payload, Rlp::encodeBytes($payload));
    }
}
