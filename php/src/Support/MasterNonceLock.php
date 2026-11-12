<?php

namespace Multicoin\TokenSweeper\Support;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;

class MasterNonceLock
{
    /**
     * Longer than the four RPC calls inside the lock, each with a 30 second
     * HTTP timeout, so the lock cannot expire before the transaction is broadcast.
     */
    public const HOLD_SECONDS = 180;

    public static function acquire(int $chainId): Lock
    {
        return Cache::lock('sweeper-master-nonce-' . $chainId, self::HOLD_SECONDS);
    }
}
