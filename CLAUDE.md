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

# Run tests (note: no test files currently exist)
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
- `SweeperService` (`php/src/Sweeper/`): Core sweep orchestration (fund address -> sweep tokens)
- `MonitorService` (`php/src/Sweeper/`): Watches blockchain for deposits
- `WalletService` (`php/src/Services/`): HD wallet generation and key management
- `TransactionSignerService` (`php/src/Services/`): Signs raw Ethereum transactions
- `Web3Service` (`php/src/Services/`): RPC communication wrapper

**Event-Driven Workflow:**
- `DepositDetected` -> triggers funding/sweep
- `SweepStarted` -> emitted when sweep begins
- `SweepCompleted` -> emitted when sweep finishes

**Queue Jobs:**
- `FundDepositAddress`: Sends native gas to deposit address
- `SweepTokens`: Transfers ERC-20 tokens to hot wallet
- `CheckPendingSweeps`: Monitors pending sweep confirmations

**Models & Relationships:**
- `Chain`: Blockchain configuration (1:many -> Tokens, DepositAddresses)
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

**PHP API Routes** (mounted at `/api/sweeper/`):
- `GET /api/sweeper/health`: Health check
- `GET /api/sweeper/chains`: List supported chains
- `GET /api/sweeper/tokens`: List active tokens (optional `?chain_id=` filter)
- `POST /api/sweeper/deposit-address`: Generate deposit address (requires `user_id`, `chain_id`)
- `GET /api/sweeper/user-addresses`: List user addresses (requires `?user_id=`)
- `GET /api/sweeper/sweep-status`: Check sweep status (requires `?address=&chain_id=`)
- `GET /api/sweeper/pending-sweeps`: List pending sweeps (optional `?status=&chain_id=`)
- `GET /api/sweeper/sweep-logs`: View sweep history (optional `?chain_id=&limit=`)
- `POST /api/sweeper/process-sweep`: Trigger a sweep (requires `deposit_address`, `token_address`, `chain_id`)

### Node.js Architecture

**Service-Based Structure:**
- `TokenMonitor.js`: Polls RPC for new deposits
- `TokenSweeper.js`: Executes sweep transactions
- `DepositAddressGenerator.js`: HD wallet address generation
- PostgreSQL with connection pooling for data persistence

**Node.js API Routes** (mounted at `/api/`):
- `POST /api/deposit-address`: Generate new deposit address
- `GET /api/sweep-status/:address`: Check sweep status
- `GET /health`: Health check endpoint (separate from `/api/` prefix)

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
├── Config/             # token-sweeper.php config (note: capital C)
├── Controllers/        # TokenSweeperController (API)
├── Events/             # 3 Laravel events
├── Facades/            # TokenSweeper facade
├── Jobs/               # 3 queue jobs
├── Models/             # 5 Eloquent models
├── Services/           # WalletService, Web3Service, TransactionSignerService
├── Sweeper/            # SweeperService, MonitorService
├── routes/             # api.php route definitions
└── TokenSweeperServiceProvider.php

php/database/
├── migrations/         # 5 migrations (ordered)
└── schema.sql          # Complete database schema

php/tests/
├── TestCase.php        # Base test case (Orchestra Testbench)
├── Unit/
│   ├── Models/         # ChainTest, DepositAddressTest, PendingSweepTest, SweepLogTest, TokenTest
│   ├── Services/       # WalletServiceTest, Web3ServiceTest
│   └── Jobs/           # CheckPendingSweepsTest, FundDepositAddressTest, SweepTokensTest
└── Feature/            # CommandsTest, DepositAddressTest, SweepWorkflowTest

nodejs/src/
├── controllers/        # DepositController.js
├── routes/             # api.js
├── services/           # TokenMonitor.js, TokenSweeper.js, DepositAddressGenerator.js
└── index.js            # Main application entry (Express + monitoring startup)

docs/
├── README.md           # Documentation index
├── openapi.yaml        # OpenAPI/Swagger specification
├── guides/             # QUICK-START.md, DEVELOPER-SETUP.md, API-GUIDE.md, GIT-WORKFLOW.md
├── architecture/       # ARCHITECTURE.md
├── reference/          # MONOREPO-MIGRATION-REPORT.md, TEST-REPORT.md, VERIFICATION-REPORT.md, COMPLETE-PACKAGE-REPORT.md
└── api/                # index.html (generated API docs)

php/docs/               # USAGE.md, API-REFERENCE.md, TROUBLESHOOTING.md
```

## Configuration & Environment

**PHP Configuration** (`php/src/Config/token-sweeper.php`):
- `monitoring.check_interval`: Seconds between deposit checks (default: 5)
- `monitoring.block_confirmations`: Required confirmations (default: 1)
- `monitoring.max_retry_attempts`: Max sweep retries (default: 3)
- `monitoring.retry_delay`: Seconds between retries (default: 60)
- `rpc_urls`: Chain ID -> RPC endpoint mapping (1=ETH, 56=BSC, 137=Polygon, 42161=Arbitrum, 10=Optimism)
- `default_chains`: Seeded chain configurations (Ethereum, BSC, Polygon)
- `default_tokens`: Seeded token definitions (USDT, USDC, DAI on ETH; USDT, USDC on BSC and Polygon)

**Environment Variables:**
- `ETH_RPC_URL`, `BSC_RPC_URL`, `POLYGON_RPC_URL`, `ARB_RPC_URL`, `OP_RPC_URL`: RPC endpoints
- `ETH_MASTER_WALLET`, `ETH_MASTER_KEY_ENCRYPTED`: Master wallet credentials (per chain: BSC_, POLYGON_)
- `ETH_HOT_WALLET`: Hot wallet address for token collection (per chain: BSC_, POLYGON_)
- `NOWNODES_API_KEY`: NowNodes API key for RPC access
- `SWEEPER_CHECK_INTERVAL`, `SWEEPER_CONFIRMATIONS`, `SWEEPER_MAX_RETRIES`, `SWEEPER_RETRY_DELAY`: Monitoring config overrides
- `DB_CONNECTION`, `DB_HOST`, `DB_DATABASE`: Database configuration
- `PORT`: Node.js server port (default: 3000)

## Testing Strategy

**PHPUnit Configuration** (`phpunit.xml`):
- Uses Orchestra Testbench for Laravel package testing
- In-memory SQLite database (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`)
- Test environment variables set automatically
- 248 total tests across Unit and Feature suites
- `failOnWarning` and `failOnRisky` both set to `false`

**Test Status:**
- 217/248 tests passing (87.5%)
- 22 errors + 9 failures, all in Job tests and some Feature/CommandsTest
- Failing tests are concentrated in:
  - `Unit/Jobs/CheckPendingSweepsTest` (9 errors)
  - `Unit/Jobs/FundDepositAddressTest` (4 errors + 4 failures)
  - `Unit/Jobs/SweepTokensTest` (7 errors + 5 failures)
  - `Feature/CommandsTest` (2 errors + 1 failure)
- 217 PHPUnit deprecation warnings (non-blocking)
- Mock Web3 interactions to avoid live blockchain calls

**Node.js Tests:**
- Jest is configured in `package.json` but no test files currently exist under `nodejs/`
- `npm run test:node` will exit with an error (no tests found)

**Running Specific Tests:**
- Filter by test name: `--filter testMethodName`
- Test specific file: `./vendor/bin/phpunit path/to/TestFile.php`
- Test specific suite: `--testsuite=Unit` or `--testsuite=Feature`

## Important Implementation Details

### Service Provider Paths

The `TokenSweeperServiceProvider` (`php/src/TokenSweeperServiceProvider.php`) loads:
- Config from `__DIR__ . '/Config/token-sweeper.php'` (capital C in Config)
- Migrations from `__DIR__ . '/../database/migrations'` (up one level to `php/database/`)
- Routes from `__DIR__ . '/routes/api.php'`

The base `TestCase` also loads migrations independently from `__DIR__ . '/../database/migrations'` (relative to `php/tests/`).

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
- Ethereum: 0.002 ETH (2000000000000000 wei)
- BSC: 0.001 BNB (1000000000000000 wei)
- Polygon: 0.1 MATIC (100000000000000000 wei)

**Gas Limit for Token Transfers:** 100,000 (configurable per chain)

### Transaction Confirmation

Monitor transaction confirmations before marking sweep as complete. Default: 1 confirmation (configurable via `monitoring.block_confirmations`).

### Error Handling

Retry failed sweeps up to `max_retry_attempts` (default: 3) with `retry_delay` (default: 60) seconds between attempts. Log all failures to `sweep_logs` table with error messages.

## Package Installation (for Laravel Apps)

```json
{
    "require": {
        "multicoin/token-sweeper": "^1.0"
    }
}
```

**Auto-Discovery:** Service provider and facade automatically registered via `composer.json` extra section.

## Common Development Tasks

**Adding a New Blockchain:**
1. Add chain configuration to `php/src/Config/token-sweeper.php` default_chains
2. Add RPC URL environment variable
3. Seed chain data: `php artisan sweeper:seed`
4. Update both PHP and Node.js implementations
5. Add integration tests

**Adding a New Token:**
1. Add to `php/src/Config/token-sweeper.php` default_tokens
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

**Runtime Requirements:**
- PHP >= 8.2
- Node.js >= 18.0.0

**PHP Core:**
- `illuminate/support`, `illuminate/database`, `illuminate/console`: ^10.0|^11.0
- `simplito/elliptic-php`: Elliptic curve cryptography
- `kornrunner/keccak`: Keccak-256 hashing
- `guzzlehttp/guzzle`: HTTP client for RPC calls

**PHP Dev:**
- `phpunit/phpunit`: ^11.0
- `orchestra/testbench`: ^8.0|^9.0
- `mockery/mockery`: ^1.6

**Node.js:**
- `ethers`: ^6.9.0 (v6 API, not v5)
- `express`: ^4.18.2
- `pg`: ^8.11.3 (PostgreSQL client)
- `dotenv`: ^16.3.1

**Node.js Dev:**
- `jest`: ^29.7.0
- `nodemon`: ^3.0.1

## Namespace & PSR-4

**Namespace:** `Multicoin\TokenSweeper`
**Autoload:** `"Multicoin\\TokenSweeper\\": "php/src/"`
**Tests:** `"Multicoin\\TokenSweeper\\Tests\\": "php/tests/"`

All PHP classes follow PSR-4 autoloading. One class per file.
