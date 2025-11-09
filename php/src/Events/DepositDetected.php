<?php

namespace Multicoin\TokenSweeper\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DepositDetected
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public string $depositAddress,
        public string $tokenAddress,
        public int $chainId,
        public string $amount
    ) {}
}
