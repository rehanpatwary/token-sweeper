# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Multi-chain token sweeping system with dual implementations: Laravel (PHP) package and Node.js application. Automatically sweeps ERC-20 tokens from deposit addresses to hot wallets across Ethereum, BSC, Polygon, Arbitrum, and Optimism.

**Key Concept:** The "sweeper" monitors deposit addresses for incoming tokens. When detected, it funds the address with native gas tokens (ETH/BNB/MATIC), then sweeps the ERC-20 tokens to a hot wallet for collection.

## Commands

### Development & Testing

**PHP/Laravel:**
```bash
# Run all tests (248 tests total)
composer test

# Run specific test suites
composer test:unit
composer test:feature

# Run specific test file
./vendor/bin/phpunit php/tests/Unit/Models/ChainTest.php

# Run specific test method
./vendor/bin/phpunit --filter testCanGenerateDepositAddress

# Check autoloader
composer dump-autoload
```

**Node.js:**
```bash
# Start application (monitoring + API server)
npm start

# Development mode with auto-reload
npm run dev

# Run tests
npm run test:node

# Watch mode for testing
npm run test:watch
```

**Combined:**
```bash
# Install all dependencies
npm run install:all

# Run both PHP and Node.js tests
npm test
```

### Laravel Package Usage

```bash
# Initial setup
php artisan vendor:publish --tag=token-sweeper-config
php artisan migrate
php artisan sweeper:seed

# Core operations
php artisan sweeper:generate-address {user_id} {chain_id}
php artisan sweeper:monitor              # Start deposit monitoring
php artisan sweeper:sweep {address} {token} {chain_id}
php artisan sweeper:pending              # Check pending sweeps
php artisan sweeper:balance {address} {token?} {--chain_id=1}

# Installation command
php artisan sweeper:install
```

## Architecture & Design Patterns

### Dual Implementation Strategy

The codebase maintains **two independent implementations** of the same sweeper logic:

1. **PHP/Laravel Package** (`php/src/`): Laravel-native implementation for integration into existing Laravel applications
2. **Node.js Application** (`nodejs/src/`): Standalone Express.js application with similar functionality

**Critical:** Changes to business logic must be mirrored in both implementations.

### PHP Architecture

**Service Layer Pattern:**
- `SweeperService`: Core sweep orchestration (fund address → sweep tokens)
- `MonitorService`: Watches blockchain for deposits
- `WalletService`: HD wallet generation and key management
- `TransactionSignerService`: Signs raw Ethereum transactions
- `Web3Service`: RPC communication wrapper

**Event-Driven Workflow:**
- `DepositDetected` → triggers funding/sweep
- `SweepStarted` → emitted when sweep begins
- `SweepCompleted` → emitted when sweep finishes

**Queue Jobs:**
- `FundDepositAddress`: Sends native gas to deposit address
- `SweepTokens`: Transfers ERC-20 tokens to hot wallet
- `CheckPendingSweeps`: Monitors pending sweep confirmations

**Models & Relationships:**
- `Chain`: Blockchain configuration (1:many → Tokens, DepositAddresses)
- `Token`: ERC-20 token definitions (belongs to Chain)
- `DepositAddress`: User deposit addresses (belongs to Chain, User)
- `PendingSweep`: In-progress sweeps (belongs to Chain)
- `SweepLog`: Historical sweep records

**Database Migration Order:**
1. `chains` (base table)
2. `tokens` (references chains)
3. `deposit_addresses` (references chains)
4. `pending_sweeps` (references chains, tokens)
5. `sweep_logs` (references chains, tokens)

### Node.js Architecture

**Service-Based Structure:**
- `TokenMonitor.js`: Polls RPC for new deposits
- `TokenSweeper.js`: Executes sweep transactions
- `DepositAddressGenerator.js`: HD wallet address generation
- PostgreSQL with connection pooling for data persistence

**API Routes:**
- `POST /api/deposit-address`: Generate new deposit address
- `GET /api/sweep-status/:address`: Check sweep status
- `GET /health`: Health check endpoint

### Shared Concepts

**Sweep Workflow (Both Implementations):**
1. Monitor deposit address for token balance
2. Check if address has sufficient gas (native token)
3. If insufficient gas: Fund address from master wallet
4. Wait for funding confirmation
5. Sign and send token transfer to hot wallet
6. Log sweep with transaction hash and status

**Wallet Roles:**
- **Master Wallet**: Funds deposit addresses with gas (holds ETH/BNB/MATIC)
- **Hot Wallet**: Receives swept tokens for collection
- **Deposit Addresses**: Ephemeral addresses generated per user/deposit

## Code Organization

```
php/src/
├── Commands/           # 7 Artisan commands
├── Controllers/        # API controller
├── Events/            # 3 Laravel events
├── Facades/           # TokenSweeper facade
├── Jobs/              # 3 queue jobs
├── Models/            # 5 Eloquent models
├── Services/          # Business logic services
├── Sweeper/           # SweeperService, MonitorService
├── Config/            # token-sweeper.php config
└── routes/            # api.php routes

php/database/
├── migrations/        # 5 migrations (ordered)
└── schema.sql         # Complete database schema

nodejs/src/
├── controllers/       # API controllers
├── models/            # Database models
├── routes/            # Express routes
├── services/          # Business logic
└── index.js           # Main application entry

php/tests/
├── Unit/              # Unit tests (models, services)
└── Feature/           # Feature/integration tests
```

## Configuration & Environment

**PHP Configuration** (`config/token-sweeper.php`):
- `monitoring.check_interval`: Seconds between deposit checks (default: 5)
- `monitoring.block_confirmations`: Required confirmations (default: 1)
- `monitoring.max_retry_attempts`: Max sweep retries (default: 3)
- `rpc_urls`: Chain ID → RPC endpoint mapping
- `default_chains`: Seeded chain configurations
- `default_tokens`: Seeded token definitions (USDT, USDC, DAI)

**Environment Variables:**
- `ETH_RPC_URL`, `BSC_RPC_URL`, `POLYGON_RPC_URL`, etc.: RPC endpoints
- `ETH_MASTER_WALLET`, `ETH_MASTER_KEY_ENCRYPTED`: Master wallet credentials
- `ETH_HOT_WALLET`: Hot wallet address for token collection
- `NOWNODES_API_KEY`: NowNodes API key for RPC access
- `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`: Database configuration

## Testing Strategy

**PHPUnit Configuration** (`phpunit.xml`):
- Uses Orchestra Testbench for Laravel package testing
- In-memory SQLite database (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`)
- Test environment variables set automatically
- 248 total tests across Unit and Feature suites

**Test Coverage:**
- 217/248 tests passing (87.5% as documented)
- Focus on models, services, commands, and jobs
- Mock Web3 interactions to avoid live blockchain calls

**Running Specific Tests:**
- Filter by test name: `--filter testMethodName`
- Test specific file: `./vendor/bin/phpunit path/to/TestFile.php`
- Test specific suite: `--testsuite=Unit` or `--testsuite=Feature`

## Important Implementation Details

### Cryptographic Signing

**PHP:** Uses `simplito/elliptic-php` for secp256k1 ECDSA signatures. Private keys are encrypted using Laravel's `Crypt` facade (AES-256-GCM).

**Node.js:** Uses `ethers.js` v6 for wallet operations and transaction signing.

### HD Wallet Derivation

**Derivation Path:** `m/44'/60'/0'/0/{index}`
- BIP-44 standard for Ethereum (coin type 60)
- Each user gets unique index
- Both implementations must use identical derivation

### Gas Estimation

**Default Gas Amounts** (from config):
- Ethereum: 0.002 ETH
- BSC: 0.001 BNB
- Polygon: 0.1 MATIC

**Gas Limit for Token Transfers:** 100,000 (configurable per chain)

### Transaction Confirmation

Monitor transaction confirmations before marking sweep as complete. Default: 1 confirmation (configurable via `monitoring.block_confirmations`).

### Error Handling

Retry failed sweeps up to `max_retry_attempts` with `retry_delay` seconds between attempts. Log all failures to `sweep_logs` table with error messages.

## Package Installation (for Laravel Apps)

```json
// composer.json
{
    "require": {
        "multicoin/token-sweeper": "^1.0"
    }
}
```

**Auto-Discovery:** Service provider and facade automatically registered via `composer.json` extra section.

## Common Development Tasks

**Adding a New Blockchain:**
1. Add chain configuration to `config/token-sweeper.php` default_chains
2. Add RPC URL environment variable
3. Seed chain data: `php artisan sweeper:seed`
4. Update both PHP and Node.js implementations
5. Add integration tests

**Adding a New Token:**
1. Add to `config/token-sweeper.php` default_tokens
2. Ensure chain exists first
3. Run seed command or manually create Token record
4. Test with small amount first

**Debugging Sweep Failures:**
1. Check `pending_sweeps` table for status
2. Review `sweep_logs` for error messages
3. Verify RPC connectivity: `php artisan sweeper:balance {address} --chain_id=1`
4. Check master wallet has sufficient gas
5. Verify block confirmations completed

**Modifying Sweep Logic:**
1. Update `SweeperService.php` (PHP) or `TokenSweeper.js` (Node.js)
2. Update corresponding tests
3. Test on testnet first (Goerli, BSC Testnet, Mumbai)
4. Ensure both implementations stay synchronized

## Security Considerations

- **Private Keys:** Encrypted at rest using Laravel Crypt (PHP) or environment variables (Node.js)
- **Never Log Private Keys:** Grep for sensitive data before commits
- **RPC Rate Limiting:** Implement retry logic and backoff for RPC calls
- **Gas Price Spikes:** Monitor gas prices before funding/sweeping
- **Wallet Separation:** Master and hot wallets should be separate addresses
- **Database Encryption:** Consider column-level encryption for private_key_encrypted fields

## Dependencies

**PHP Core:**
- `illuminate/support`, `illuminate/database`, `illuminate/console`: ^10.0|^11.0
- `simplito/elliptic-php`: Elliptic curve cryptography
- `kornrunner/keccak`: Keccak-256 hashing
- `guzzlehttp/guzzle`: HTTP client for RPC calls

**PHP Dev:**
- `phpunit/phpunit`: ^11.0
- `orchestra/testbench`: Laravel package testing
- `mockery/mockery`: Mocking framework

**Node.js:**
- `ethers`: ^6.9.0 (v6 API, not v5)
- `express`: ^4.18.2
- `pg`: ^8.11.3 (PostgreSQL client)
- `dotenv`: ^16.3.1

## Namespace & PSR-4

**Namespace:** `Multicoin\TokenSweeper`
**Autoload:** `"Multicoin\\TokenSweeper\\": "php/src/"`
**Tests:** `"Multicoin\\TokenSweeper\\Tests\\": "php/tests/"`

All PHP classes follow PSR-4 autoloading. One class per file.
