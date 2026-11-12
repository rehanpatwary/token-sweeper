<?php

namespace Multicoin\TokenSweeper\Support;

use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Services\Web3Service;

class ChainRpc
{
    public static function connect(Chain $chain): Web3Service
    {
        if (app()->bound(Web3Service::class)) {
            return app()->make(Web3Service::class);
        }

        return app()->makeWith(Web3Service::class, [
            'rpcUrl' => $chain->rpc_url,
            'chainId' => (int) $chain->chain_id,
        ]);
    }
}
