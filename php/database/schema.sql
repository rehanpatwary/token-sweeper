-- Database Schema for Multi-Chain Token Sweeper

CREATE TABLE chains (
    id SERIAL PRIMARY KEY,
    chain_id INTEGER UNIQUE NOT NULL,
    name VARCHAR(50) NOT NULL,
    rpc_url TEXT NOT NULL,
    master_wallet_address VARCHAR(66) NOT NULL,
    master_private_key_encrypted TEXT NOT NULL,
    hot_wallet_address VARCHAR(66) NOT NULL,
    native_symbol VARCHAR(10) NOT NULL,
    gas_amount_wei VARCHAR NOT NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE tokens (
    id SERIAL PRIMARY KEY,
    chain_id INTEGER REFERENCES chains(chain_id),
    symbol VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    contract_address VARCHAR(66) NOT NULL,
    decimals INTEGER NOT NULL,
    is_active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE(chain_id, contract_address)
);

CREATE TABLE deposit_addresses (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    chain_id INTEGER REFERENCES chains(chain_id),
    address VARCHAR(66) NOT NULL,
    private_key_encrypted TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_sweep_at TIMESTAMP,
    UNIQUE(chain_id, address),
    INDEX idx_user_chain (user_id, chain_id)
);

CREATE TABLE pending_sweeps (
    id SERIAL PRIMARY KEY,
    deposit_address VARCHAR(66) NOT NULL,
    chain_id INTEGER REFERENCES chains(chain_id),
    token_address VARCHAR(66) NOT NULL,
    token_symbol VARCHAR(20),
    amount VARCHAR NOT NULL,
    status VARCHAR(20) DEFAULT 'pending',
    funding_tx_hash VARCHAR(66),
    sweep_tx_hash VARCHAR(66),
    error_message TEXT,
    retry_count INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_deposit (deposit_address, chain_id)
);

CREATE TABLE sweep_logs (
    id SERIAL PRIMARY KEY,
    sweep_id INTEGER REFERENCES pending_sweeps(id),
    chain_id INTEGER,
    deposit_address VARCHAR(66),
    token_address VARCHAR(66),
    amount VARCHAR,
    funding_tx_hash VARCHAR(66),
    sweep_tx_hash VARCHAR(66),
    gas_used VARCHAR,
    status VARCHAR(20),
    completed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert sample chains
INSERT INTO chains (chain_id, name, rpc_url, master_wallet_address, master_private_key_encrypted, hot_wallet_address, native_symbol, gas_amount_wei) VALUES
(1, 'Ethereum', 'https://eth.nownodes.io/YOUR_API_KEY', '0x...', 'encrypted...', '0x...', 'ETH', '2000000000000000'),
(56, 'BSC', 'https://bsc.nownodes.io/YOUR_API_KEY', '0x...', 'encrypted...', '0x...', 'BNB', '1000000000000000'),
(137, 'Polygon', 'https://matic.nownodes.io/YOUR_API_KEY', '0x...', 'encrypted...', '0x...', 'MATIC', '100000000000000000'),
(42161, 'Arbitrum', 'https://arb.nownodes.io/YOUR_API_KEY', '0x...', 'encrypted...', '0x...', 'ETH', '1000000000000000'),
(10, 'Optimism', 'https://op.nownodes.io/YOUR_API_KEY', '0x...', 'encrypted...', '0x...', 'ETH', '1000000000000000');

-- Insert sample tokens
INSERT INTO tokens (chain_id, symbol, name, contract_address, decimals) VALUES
-- Ethereum
(1, 'USDT', 'Tether USD', '0xdAC17F958D2ee523a2206206994597C13D831ec7', 6),
(1, 'USDC', 'USD Coin', '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48', 6),
(1, 'DAI', 'Dai Stablecoin', '0x6B175474E89094C44Da98b954EedeAC495271d0F', 18),
-- BSC
(56, 'USDT', 'Tether USD', '0x55d398326f99059fF775485246999027B3197955', 18),
(56, 'USDC', 'USD Coin', '0x8AC76a51cc950d9822D68b83fE1Ad97B32Cd580d', 18),
-- Polygon
(137, 'USDT', 'Tether USD', '0xc2132D05D31c914a87C6611C10748AEb04B58e8F', 6),
(137, 'USDC', 'USD Coin', '0x2791Bca1f2de4661ED88A30C99A7a9449Aa84174', 6);