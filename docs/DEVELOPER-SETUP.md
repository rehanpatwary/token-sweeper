# Developer Setup Guide

Complete guide for setting up the Token Sweeper development environment for both PHP/Laravel and Node.js implementations.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Initial Setup](#initial-setup)
3. [PHP/Laravel Setup](#phplaravel-setup)
4. [Node.js Setup](#nodejs-setup)
5. [Database Configuration](#database-configuration)
6. [Environment Variables](#environment-variables)
7. [Running Tests](#running-tests)
8. [Development Workflow](#development-workflow)
9. [Troubleshooting](#troubleshooting)

## Prerequisites

### Required Software

**PHP Development:**
- PHP 8.2 or higher
- Composer 2.x
- PostgreSQL 12+ or MySQL 8+
- Redis 6+

**Node.js Development:**
- Node.js 18+
- npm 9+
- PostgreSQL 12+

**Development Tools:**
- Git
- Docker (optional, for containerized development)
- IDE with PHP/JavaScript support (PHPStorm, VS Code)

### Installation

**macOS (using Homebrew):**

```bash
# Install PHP
brew install php@8.2

# Install Composer
brew install composer

# Install PostgreSQL
brew install postgresql@14
brew services start postgresql@14

# Install Redis
brew install redis
brew services start redis

# Install Node.js
brew install node@18
```

**Ubuntu/Debian:**

```bash
# Add PHP repository
sudo add-apt-repository ppa:ondrej/php
sudo apt update

# Install PHP and extensions
sudo apt install php8.2 php8.2-cli php8.2-fpm php8.2-pgsql \
    php8.2-mbstring php8.2-xml php8.2-bcmath php8.2-curl \
    php8.2-gmp php8.2-redis

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Install PostgreSQL
sudo apt install postgresql postgresql-contrib

# Install Redis
sudo apt install redis-server
sudo systemctl start redis-server

# Install Node.js
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt install -y nodejs
```

**Windows:**

Download and install:
- [PHP 8.2](https://windows.php.net/download/)
- [Composer](https://getcomposer.org/download/)
- [PostgreSQL](https://www.postgresql.org/download/windows/)
- [Redis](https://github.com/microsoftarchive/redis/releases) (or use WSL2)
- [Node.js 18+](https://nodejs.org/en/download/)

## Initial Setup

### 1. Clone the Repository

```bash
git clone https://github.com/multicoin/token-sweeper.git
cd token-sweeper
```

### 2. Install All Dependencies

```bash
# Install both PHP and Node.js dependencies
npm run install:all

# Or install separately
composer install
npm install
```

## PHP/Laravel Setup

### 1. Configure PHP Extensions

Ensure required extensions are enabled in `php.ini`:

```ini
extension=pdo_pgsql
extension=mbstring
extension=xml
extension=bcmath
extension=curl
extension=gmp
extension=redis
```

Verify extensions:

```bash
php -m | grep -E 'pdo_pgsql|mbstring|xml|bcmath|gmp|redis'
```

### 2. Set Up Environment File

```bash
# Copy environment template
cp .env.example .env

# Edit environment variables
nano .env
```

**Required .env Variables:**

```ini
# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=token_sweeper
DB_USERNAME=postgres
DB_PASSWORD=your_password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Blockchain RPC URLs
ETH_RPC_URL=https://eth.nownodes.io/${NOWNODES_API_KEY}
BSC_RPC_URL=https://bsc.nownodes.io/${NOWNODES_API_KEY}
POLYGON_RPC_URL=https://matic.nownodes.io/${NOWNODES_API_KEY}
ARBITRUM_RPC_URL=https://arbitrum.nownodes.io/${NOWNODES_API_KEY}
OPTIMISM_RPC_URL=https://optimism.nownodes.io/${NOWNODES_API_KEY}

# NowNodes API Key (get free key at https://nownodes.io)
NOWNODES_API_KEY=your_api_key_here

# Master Wallet (funds gas for deposits)
ETH_MASTER_WALLET=0xYourMasterWalletAddress
ETH_MASTER_KEY_ENCRYPTED=encrypted_private_key_here

# Hot Wallet (receives swept tokens)
ETH_HOT_WALLET=0xYourHotWalletAddress

# Queue Configuration
QUEUE_CONNECTION=redis

# Logging
LOG_CHANNEL=stack
LOG_LEVEL=debug
```

### 3. Create Database

```bash
# PostgreSQL
createdb token_sweeper

# Or using psql
psql -U postgres
CREATE DATABASE token_sweeper;
\q
```

### 4. Run Migrations

```bash
# Run database migrations
php artisan migrate

# Or if testing the package
cd php
../vendor/bin/phpunit --migrate
```

### 5. Seed Initial Data

```bash
# Seed chains and tokens
php artisan sweeper:seed

# Or manually create seed data
php artisan tinker
```

In Tinker:

```php
// Create Ethereum chain
$eth = \Multicoin\TokenSweeper\Models\Chain::create([
    'chain_id' => 1,
    'name' => 'Ethereum Mainnet',
    'native_symbol' => 'ETH',
    'rpc_url' => config('token-sweeper.rpc_urls.1'),
    'is_active' => true
]);

// Create USDT token
\Multicoin\TokenSweeper\Models\Token::create([
    'chain_id' => 1,
    'contract_address' => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
    'symbol' => 'USDT',
    'name' => 'Tether USD',
    'decimals' => 6,
    'is_active' => true
]);
```

### 6. Generate Master Wallet Encryption Key

```bash
# Generate Laravel application key (used for encryption)
php artisan key:generate

# Encrypt your master wallet private key
php artisan tinker
```

In Tinker:

```php
use Illuminate\Support\Facades\Crypt;

$privateKey = 'your_master_wallet_private_key_without_0x';
$encrypted = Crypt::encryptString($privateKey);

echo "Add this to .env:\n";
echo "ETH_MASTER_KEY_ENCRYPTED={$encrypted}\n";
```

### 7. Test PHP Installation

```bash
# Run all tests
composer test

# Run specific test suite
composer test:unit
composer test:feature

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage
```

## Node.js Setup

### 1. Configure Environment

```bash
# Environment variables for Node.js (same .env file)
cp .env.example .env
```

**Additional Node.js Variables:**

```ini
# Server Configuration
PORT=3000
NODE_ENV=development

# Database URL format for pg library
DATABASE_URL=postgresql://postgres:password@localhost:5432/token_sweeper

# Master wallet private key (for Node.js, not encrypted)
MASTER_WALLET_PRIVATE_KEY=0xyour_private_key_here
```

### 2. Initialize Database

If you haven't already run migrations from PHP:

```bash
# Create database tables using schema
psql -U postgres -d token_sweeper -f php/database/schema.sql
```

### 3. Test Node.js Installation

```bash
# Run Node.js tests
npm run test:node

# Run in watch mode
npm run test:watch

# Start development server
npm run dev
```

### 4. Verify Node.js API

```bash
# Start server
npm start

# In another terminal, test endpoints
curl http://localhost:3000/health
curl http://localhost:3000/api/deposit-address -X POST \
  -H "Content-Type: application/json" \
  -d '{"userId": 1}'
```

## Database Configuration

### PostgreSQL Setup

**Create User and Database:**

```sql
-- Connect as postgres superuser
psql -U postgres

-- Create database user
CREATE USER sweeper_user WITH PASSWORD 'secure_password';

-- Create database
CREATE DATABASE token_sweeper OWNER sweeper_user;

-- Grant privileges
GRANT ALL PRIVILEGES ON DATABASE token_sweeper TO sweeper_user;

-- Connect to database
\c token_sweeper

-- Grant schema privileges
GRANT ALL ON SCHEMA public TO sweeper_user;

-- Exit
\q
```

**Update .env:**

```ini
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=token_sweeper
DB_USERNAME=sweeper_user
DB_PASSWORD=secure_password
```

### MySQL Alternative (Optional)

If using MySQL instead of PostgreSQL:

```bash
# Create database
mysql -u root -p
CREATE DATABASE token_sweeper;
CREATE USER 'sweeper_user'@'localhost' IDENTIFIED BY 'secure_password';
GRANT ALL PRIVILEGES ON token_sweeper.* TO 'sweeper_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

**Update .env:**

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=token_sweeper
DB_USERNAME=sweeper_user
DB_PASSWORD=secure_password
```

### Redis Configuration

**Test Redis Connection:**

```bash
# Start Redis CLI
redis-cli

# Test commands
PING
# Should return: PONG

SET test "Hello Redis"
GET test
# Should return: "Hello Redis"

EXIT
```

**Configure Redis Password (Production):**

Edit `/etc/redis/redis.conf`:

```conf
requirepass your_secure_password
```

Update .env:

```ini
REDIS_PASSWORD=your_secure_password
```

## Environment Variables

### Complete .env Template

```ini
# ==============================================
# Application Configuration
# ==============================================
APP_NAME=TokenSweeper
APP_ENV=development
APP_KEY=base64:generated_by_php_artisan_key_generate
APP_DEBUG=true
APP_URL=http://localhost:8000

# ==============================================
# Database Configuration
# ==============================================
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=token_sweeper
DB_USERNAME=postgres
DB_PASSWORD=

# Node.js Database URL (for pg library)
DATABASE_URL=postgresql://postgres:password@localhost:5432/token_sweeper

# ==============================================
# Redis Configuration
# ==============================================
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Queue Configuration
QUEUE_CONNECTION=redis

# ==============================================
# Blockchain RPC URLs
# ==============================================
# Get free API key: https://nownodes.io
NOWNODES_API_KEY=your_api_key_here

# Ethereum Mainnet (Chain ID: 1)
ETH_RPC_URL=https://eth.nownodes.io/${NOWNODES_API_KEY}

# BSC Mainnet (Chain ID: 56)
BSC_RPC_URL=https://bsc.nownodes.io/${NOWNODES_API_KEY}

# Polygon Mainnet (Chain ID: 137)
POLYGON_RPC_URL=https://matic.nownodes.io/${NOWNODES_API_KEY}

# Arbitrum One (Chain ID: 42161)
ARBITRUM_RPC_URL=https://arbitrum.nownodes.io/${NOWNODES_API_KEY}

# Optimism Mainnet (Chain ID: 10)
OPTIMISM_RPC_URL=https://optimism.nownodes.io/${NOWNODES_API_KEY}

# ==============================================
# Testnet RPC URLs (for development)
# ==============================================
# Sepolia Testnet (Chain ID: 11155111)
SEPOLIA_RPC_URL=https://eth-sepolia.g.alchemy.com/v2/your_alchemy_key

# BSC Testnet (Chain ID: 97)
BSC_TESTNET_RPC_URL=https://data-seed-prebsc-1-s1.binance.org:8545

# Polygon Mumbai (Chain ID: 80001)
MUMBAI_RPC_URL=https://rpc-mumbai.maticvigil.com

# ==============================================
# Wallet Configuration
# ==============================================
# Master Wallet: Funds deposit addresses with gas
ETH_MASTER_WALLET=0xYourMasterWalletAddress
ETH_MASTER_KEY_ENCRYPTED=encrypted_with_laravel_crypt

# For Node.js (plain text, be careful!)
MASTER_WALLET_PRIVATE_KEY=0xyour_private_key

# Hot Wallet: Receives swept tokens
ETH_HOT_WALLET=0xYourHotWalletAddress

# BSC wallets (if different from ETH)
BSC_MASTER_WALLET=0xYourBSCMasterWallet
BSC_HOT_WALLET=0xYourBSCHotWallet

# Polygon wallets (if different from ETH)
POLYGON_MASTER_WALLET=0xYourPolygonMasterWallet
POLYGON_HOT_WALLET=0xYourPolygonHotWallet

# ==============================================
# Sweeper Configuration
# ==============================================
# Monitoring interval in seconds
SWEEPER_CHECK_INTERVAL=5

# Required block confirmations before sweep
SWEEPER_CONFIRMATIONS=1

# Maximum retry attempts for failed sweeps
SWEEPER_MAX_RETRIES=3

# Retry delay in seconds
SWEEPER_RETRY_DELAY=60

# Default gas amounts (in native token)
ETH_GAS_AMOUNT=0.002
BSC_GAS_AMOUNT=0.001
POLYGON_GAS_AMOUNT=0.1

# ==============================================
# Node.js Server Configuration
# ==============================================
PORT=3000
NODE_ENV=development

# ==============================================
# Logging Configuration
# ==============================================
LOG_CHANNEL=stack
LOG_LEVEL=debug

# ==============================================
# Testing Configuration (for PHPUnit)
# ==============================================
DB_CONNECTION_TEST=sqlite
DB_DATABASE_TEST=:memory:
```

### Securing Environment Variables

**Never commit .env to version control:**

```bash
# Ensure .env is in .gitignore
echo ".env" >> .gitignore
```

**Production Environment:**

Use Laravel's encrypted environment files:

```bash
# Encrypt environment file
php artisan env:encrypt --key=base64:your_encryption_key

# Decrypt for deployment
php artisan env:decrypt --key=base64:your_encryption_key
```

## Running Tests

### PHP/Laravel Tests

```bash
# Run all tests (248 tests)
composer test

# Run with output
composer test -- --testdox

# Run specific test file
./vendor/bin/phpunit php/tests/Unit/Models/ChainTest.php

# Run specific test method
./vendor/bin/phpunit --filter testCanGenerateDepositAddress

# Run with coverage
./vendor/bin/phpunit --coverage-html coverage

# Run unit tests only
composer test:unit

# Run feature tests only
composer test:feature
```

### Node.js Tests

```bash
# Run all Node.js tests
npm run test:node

# Run in watch mode (auto-rerun on changes)
npm run test:watch

# Run with coverage
npm run test:node -- --coverage

# Run specific test file
npm run test:node -- nodejs/tests/TokenMonitor.test.js
```

### Combined Test Suite

```bash
# Run both PHP and Node.js tests
npm test
```

## Development Workflow

### Starting Development Servers

**Terminal 1 - Laravel API:**

```bash
# Start Laravel development server
php artisan serve

# Or specify port
php artisan serve --port=8000
```

**Terminal 2 - Node.js API:**

```bash
# Start Node.js server with auto-reload
npm run dev

# Or start normally
npm start
```

**Terminal 3 - Queue Worker:**

```bash
# Start Laravel queue worker
php artisan queue:work

# With auto-reload on code changes
php artisan queue:work --tries=3 --timeout=90
```

**Terminal 4 - Monitor Service:**

```bash
# Start token deposit monitoring
php artisan sweeper:monitor
```

### Using Artisan Commands

```bash
# Generate deposit address
php artisan sweeper:generate-address 12345 1

# Check address balance
php artisan sweeper:balance 0x742d35Cc... --chain_id=1

# Check pending sweeps
php artisan sweeper:pending

# Manually sweep tokens
php artisan sweeper:sweep 0x742d35Cc... 0xdAC17F95... 1

# Run monitor (continuous)
php artisan sweeper:monitor

# Install/setup package
php artisan sweeper:install
```

### Database Management

```bash
# Run migrations
php artisan migrate

# Rollback migration
php artisan migrate:rollback

# Reset database (WARNING: deletes all data)
php artisan migrate:fresh

# Seed data
php artisan sweeper:seed

# Check migration status
php artisan migrate:status
```

### Testing Individual Components

**Test Wallet Generation:**

```bash
php artisan tinker
```

```php
$walletService = app(\Multicoin\TokenSweeper\Services\WalletService::class);
$address = $walletService->createDepositAddress(12345, 1);
echo "Generated address: {$address}\n";
```

**Test Web3 Connection:**

```php
$web3 = app(\Multicoin\TokenSweeper\Services\Web3Service::class);
$balance = $web3->getBalance('0x742d35Cc...', 1);
echo "Balance: {$balance}\n";
```

**Test Token Balance:**

```php
$tokenBalance = $web3->getTokenBalance(
    '0x742d35Cc...',  // address
    '0xdAC17F95...',  // USDT contract
    1                 // Ethereum
);
echo "Token balance: {$tokenBalance}\n";
```

## Troubleshooting

### Common Issues

**1. Database Connection Failed**

```bash
# Check PostgreSQL is running
sudo systemctl status postgresql

# Or on macOS
brew services list

# Test connection
psql -U postgres -d token_sweeper -c "SELECT version();"
```

**2. Redis Connection Failed**

```bash
# Check Redis is running
redis-cli ping

# Check Redis configuration
redis-cli config get requirepass
```

**3. PHP Extensions Missing**

```bash
# Check installed extensions
php -m

# Install missing extension (Ubuntu)
sudo apt install php8.2-gmp php8.2-bcmath

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm
```

**4. Composer Install Fails**

```bash
# Update Composer
composer self-update

# Clear cache
composer clear-cache

# Install with verbose output
composer install -vvv
```

**5. Migration Errors**

```bash
# Check migration status
php artisan migrate:status

# Rollback and retry
php artisan migrate:rollback
php artisan migrate

# Fresh start (WARNING: deletes data)
php artisan migrate:fresh
```

**6. RPC Connection Issues**

```bash
# Test RPC endpoint
curl -X POST https://eth.nownodes.io/YOUR_API_KEY \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"eth_blockNumber","params":[],"id":1}'

# Should return current block number
```

**7. Queue Jobs Not Processing**

```bash
# Check queue connection
php artisan queue:failed

# Clear failed jobs
php artisan queue:flush

# Restart queue worker
php artisan queue:restart
```

**8. Tests Failing**

```bash
# Ensure test database is clean
php artisan migrate:fresh --env=testing

# Run with verbose output
./vendor/bin/phpunit --testdox --verbose

# Check for specific errors
./vendor/bin/phpunit --stop-on-failure
```

### Debug Mode

Enable detailed logging:

```ini
# .env
APP_DEBUG=true
LOG_LEVEL=debug
```

Check logs:

```bash
# Laravel logs
tail -f storage/logs/laravel.log

# Queue worker logs
php artisan queue:work --verbose
```

### Getting Help

- **Documentation**: Check `/docs` folder
- **Issues**: Report at https://github.com/multicoin/token-sweeper/issues
- **Laravel**: https://laravel.com/docs
- **Ethers.js**: https://docs.ethers.org

## Next Steps

After completing setup:

1. Read [ARCHITECTURE.md](./ARCHITECTURE.md) to understand system design
2. Review [API-GUIDE.md](./API-GUIDE.md) for API integration examples
3. Check [CLAUDE.md](../CLAUDE.md) for project-specific conventions
4. Start implementing features using TDD approach

## Production Deployment

For production deployment instructions, see:
- [DEPLOYMENT.md](./DEPLOYMENT.md) (to be created)
- Use environment-specific configurations
- Enable proper logging and monitoring
- Set up automated backups
- Configure SSL/TLS for APIs
- Implement rate limiting
- Use secrets management (AWS Secrets Manager, HashiCorp Vault)
