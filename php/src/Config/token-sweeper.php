<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Monitoring Configuration
    |--------------------------------------------------------------------------
    */
    'monitoring' => [
        'check_interval' => env('SWEEPER_CHECK_INTERVAL', 5), // seconds
        'block_confirmations' => env('SWEEPER_CONFIRMATIONS', 1),
        'max_retry_attempts' => env('SWEEPER_MAX_RETRIES', 3),
        'retry_delay' => env('SWEEPER_RETRY_DELAY', 60), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Chain RPC URLs
    |--------------------------------------------------------------------------
    */
    'rpc_urls' => [
        1 => env('ETH_RPC_URL', 'https://eth.nownodes.io/' . env('NOWNODES_API_KEY')),
        56 => env('BSC_RPC_URL', 'https://bsc.nownodes.io/' . env('NOWNODES_API_KEY')),
        137 => env('POLYGON_RPC_URL', 'https://matic.nownodes.io/' . env('NOWNODES_API_KEY')),
        42161 => env('ARB_RPC_URL', 'https://arb.nownodes.io/' . env('NOWNODES_API_KEY')),
        10 => env('OP_RPC_URL', 'https://op.nownodes.io/' . env('NOWNODES_API_KEY')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Chains (for seeding)
    |--------------------------------------------------------------------------
    */
    'default_chains' => [
        [
            'chain_id' => 1,
            'name' => 'Ethereum',
            'rpc_url' => env('ETH_RPC_URL'),
            'master_wallet_address' => env('ETH_MASTER_WALLET'),
            'master_private_key_encrypted' => env('ETH_MASTER_KEY_ENCRYPTED'),
            'hot_wallet_address' => env('ETH_HOT_WALLET'),
            'native_symbol' => 'ETH',
            'gas_amount_wei' => '2000000000000000', // 0.002 ETH
            'gas_limit_token_transfer' => 100000,
        ],
        [
            'chain_id' => 56,
            'name' => 'BSC',
            'rpc_url' => env('BSC_RPC_URL'),
            'master_wallet_address' => env('BSC_MASTER_WALLET'),
            'master_private_key_encrypted' => env('BSC_MASTER_KEY_ENCRYPTED'),
            'hot_wallet_address' => env('BSC_HOT_WALLET'),
            'native_symbol' => 'BNB',
            'gas_amount_wei' => '1000000000000000', // 0.001 BNB
            'gas_limit_token_transfer' => 100000,
        ],
        [
            'chain_id' => 137,
            'name' => 'Polygon',
            'rpc_url' => env('POLYGON_RPC_URL'),
            'master_wallet_address' => env('POLYGON_MASTER_WALLET'),
            'master_private_key_encrypted' => env('POLYGON_MASTER_KEY_ENCRYPTED'),
            'hot_wallet_address' => env('POLYGON_HOT_WALLET'),
            'native_symbol' => 'MATIC',
            'gas_amount_wei' => '100000000000000000', // 0.1 MATIC
            'gas_limit_token_transfer' => 100000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Tokens (for seeding)
    |--------------------------------------------------------------------------
    */
    'default_tokens' => [
        // Ethereum
        ['chain_id' => 1, 'symbol' => 'USDT', 'name' => 'Tether USD', 'contract_address' => '0xdAC17F958D2ee523a2206206994597C13D831ec7', 'decimals' => 6],
        ['chain_id' => 1, 'symbol' => 'USDC', 'name' => 'USD Coin', 'contract_address' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48', 'decimals' => 6],
        ['chain_id' => 1, 'symbol' => 'DAI', 'name' => 'Dai Stablecoin', 'contract_address' => '0x6B175474E89094C44Da98b954EedeAC495271d0F', 'decimals' => 18],

        // BSC
        ['chain_id' => 56, 'symbol' => 'USDT', 'name' => 'Tether USD', 'contract_address' => '0x55d398326f99059fF775485246999027B3197955', 'decimals' => 18],
        ['chain_id' => 56, 'symbol' => 'USDC', 'name' => 'USD Coin', 'contract_address' => '0x8AC76a51cc950d9822D68b83fE1Ad97B32Cd580d', 'decimals' => 18],

        // Polygon
        ['chain_id' => 137, 'symbol' => 'USDT', 'name' => 'Tether USD', 'contract_address' => '0xc2132D05D31c914a87C6611C10748AEb04B58e8F', 'decimals' => 6],
        ['chain_id' => 137, 'symbol' => 'USDC', 'name' => 'USD Coin', 'contract_address' => '0x2791Bca1f2de4661ED88A30C99A7a9449Aa84174', 'decimals' => 6],
    ],

];
