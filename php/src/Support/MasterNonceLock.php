<?php

namespace Multicoin\TokenSweeper\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

class MasterNonceLock
{
    /**
     * Longer than three RPC calls at the 30 second HTTP timeout, so the lock
     * cannot expire while the nonce is being read and the transaction broadcast.
     */
    public const HOLD_SECONDS = 120;

    public static function acquire(int $chainId): Lock
    {
        return Cache::lock('sweeper-master-nonce-' . $chainId, self::HOLD_SECONDS);
    }
}
