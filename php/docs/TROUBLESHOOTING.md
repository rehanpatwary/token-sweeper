# Laravel Token Sweeper - Troubleshooting Guide

This comprehensive guide helps you diagnose and resolve common issues with the Laravel Token Sweeper package.

---

## Table of Contents

1. [Common Installation Issues](#1-common-installation-issues)
2. [Configuration Problems](#2-configuration-problems)
3. [Database Issues](#3-database-issues)
4. [RPC Connection Errors](#4-rpc-connection-errors)
5. [Transaction Failures](#5-transaction-failures)
6. [Sweep Workflow Issues](#6-sweep-workflow-issues)
7. [Queue and Job Problems](#7-queue-and-job-problems)
8. [Performance Issues](#8-performance-issues)
9. [Security Concerns](#9-security-concerns)
10. [Debugging Tips](#10-debugging-tips)
11. [FAQ](#11-faq)

---

## 1. Common Installation Issues

### Issue 1.1: Composer Installation Fails

**Problem:**
```bash
composer require multicoin/token-sweeper
```
Returns error: `Package not found` or dependency conflicts.

**Possible Causes:**
- Package not published to Packagist
- PHP version incompatibility
- Laravel version mismatch
- Missing required PHP extensions

**Solutions:**

**Step 1: Verify PHP Requirements**
```bash
php -v  # Should be 8.2 or higher
php -m | grep -i gmp  # Verify GMP extension is installed
php -m | grep -i bcmath  # Verify BCMath extension is installed
```

**Step 2: Install Missing Extensions**
```bash
# Ubuntu/Debian
sudo apt-get install php8.2-gmp php8.2-bcmath

# macOS (Homebrew)
brew install php@8.2
brew install gmp

# CentOS/RHEL
sudo yum install php82-gmp php82-bcmath
```

**Step 3: Check Laravel Version**
```bash
php artisan --version  # Should be 10.x or 11.x
```

**Step 4: Clear Composer Cache**
```bash
composer clear-cache
composer update
```

**Prevention Tips:**
- Always check package requirements before installation
- Keep PHP and Laravel versions up to date
- Maintain a `composer.lock` file in version control

---

### Issue 1.2: Migration Fails During Installation

**Problem:**
```bash
php artisan sweeper:install
```
Returns database migration errors.

**Example Error:**
```
SQLSTATE[42S01]: Base table or view already exists: 1050 Table 'sweeper_chains' already exists
```

**Possible Causes:**
- Migrations already run
- Database connection issues
- Insufficient database permissions
- Table name conflicts

**Solutions:**

**Step 1: Check if Tables Already Exist**
```bash
php artisan tinker
>>> Schema::hasTable('sweeper_chains')
```

**Step 2: Roll Back and Re-migrate**
```bash
# View migration status
php artisan migrate:status

# Roll back package migrations only
php artisan migrate:rollback --path=/vendor/multicoin/token-sweeper/database/migrations

# Re-run migrations
php artisan migrate --path=/vendor/multicoin/token-sweeper/database/migrations
```

**Step 3: Verify Database Permissions**
```sql
-- MySQL
SHOW GRANTS FOR 'your_db_user'@'localhost';

-- Required permissions:
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, INDEX, ALTER ON database_name.* TO 'user'@'localhost';
```

**Step 4: Manual Fresh Installation**
```bash
# Drop all sweeper tables
php artisan tinker
>>> DB::statement('DROP TABLE IF EXISTS sweeper_logs');
>>> DB::statement('DROP TABLE IF EXISTS sweeper_pending_sweeps');
>>> DB::statement('DROP TABLE IF EXISTS sweeper_deposit_addresses');
>>> DB::statement('DROP TABLE IF EXISTS sweeper_tokens');
>>> DB::statement('DROP TABLE IF EXISTS sweeper_chains');
>>> exit

# Re-run installation
php artisan sweeper:install
```

**Prevention Tips:**
- Use database migrations tracking
- Keep backups before major changes
- Test in development environment first

---

### Issue 1.3: Service Provider Not Loading

**Problem:**
Package features not available, facades return errors.

**Example Error:**
```
Target class [Multicoin\TokenSweeper\TokenSweeperServiceProvider] does not exist.
```

**Possible Causes:**
- Auto-discovery not working
- Cached configuration files
- Composer autoload not updated

**Solutions:**

**Step 1: Clear All Caches**
```bash
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan view:clear
composer dump-autoload
```

**Step 2: Verify Package in composer.json**
```json
{
    "require": {
        "multicoin/token-sweeper": "^1.0"
    }
}
```

**Step 3: Manual Service Provider Registration (Laravel 10 and below)**
```php
// config/app.php
'providers' => [
    // ...
    Multicoin\TokenSweeper\TokenSweeperServiceProvider::class,
],

'aliases' => [
    // ...
    'TokenSweeper' => Multicoin\TokenSweeper\Facades\TokenSweeper::class,
],
```

**Step 4: Verify Package Discovery**
```bash
php artisan package:discover
```

**Prevention Tips:**
- Always run `composer dump-autoload` after package installation
- Clear caches in development environment regularly

---

## 2. Configuration Problems

### Issue 2.1: RPC URL Not Configured

**Problem:**
Monitor or sweep commands fail with connection errors.

**Example Error:**
```
cURL error 6: Could not resolve host: eth.nownodes.io/
```

**Possible Causes:**
- Missing `NOWNODES_API_KEY` environment variable
- Incorrect RPC URL format
- Configuration not published

**Solutions:**

**Step 1: Publish Configuration**
```bash
php artisan vendor:publish --tag=token-sweeper-config
```

**Step 2: Verify .env Configuration**
```env
# Required
NOWNODES_API_KEY=your_actual_api_key_here

# Chain-specific RPC URLs (automatically constructed)
ETH_RPC_URL=https://eth.nownodes.io/${NOWNODES_API_KEY}
BSC_RPC_URL=https://bsc.nownodes.io/${NOWNODES_API_KEY}
POLYGON_RPC_URL=https://matic.nownodes.io/${NOWNODES_API_KEY}
```

**Step 3: Test RPC Connection**
```bash
# Test RPC manually
curl -X POST https://eth.nownodes.io/YOUR_API_KEY \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"eth_blockNumber","params":[],"id":1}'

# Expected response:
{"jsonrpc":"2.0","id":1,"result":"0x..."}
```

**Step 4: Update Database Chain Configuration**
```bash
php artisan tinker
>>> use Multicoin\TokenSweeper\Models\Chain;
>>> $chain = Chain::where('chain_id', 1)->first();
>>> $chain->rpc_url = 'https://eth.nownodes.io/YOUR_API_KEY';
>>> $chain->save();
```

**Prevention Tips:**
- Store API keys in `.env`, never in code
- Test RPC endpoints before deploying
- Monitor RPC provider status pages

---

### Issue 2.2: Encrypted Private Key Invalid

**Problem:**
Sweeper fails when trying to decrypt master wallet private key.

**Example Error:**
```
The payload is invalid.
Illuminate\Contracts\Encryption\DecryptException
```

**Possible Causes:**
- `APP_KEY` changed after encryption
- Incorrect encryption format
- Private key not properly encrypted
- Wrong key stored in configuration

**Solutions:**

**Step 1: Verify APP_KEY Hasn't Changed**
```bash
# Check current APP_KEY
php artisan tinker
>>> config('app.key')
```

**Step 2: Re-encrypt Private Keys**
```bash
php artisan tinker
```

```php
use Illuminate\Support\Facades\Crypt;

// Encrypt your master wallet private key (without 0x prefix works too)
$privateKey = '0xYourActualPrivateKeyHere';
$encrypted = Crypt::encryptString($privateKey);
echo $encrypted;
```

**Step 3: Update .env File**
```env
ETH_MASTER_KEY_ENCRYPTED="eyJpdiI6IjVkN..."
BSC_MASTER_KEY_ENCRYPTED="eyJpdiI6IjhkM..."
POLYGON_MASTER_KEY_ENCRYPTED="eyJpdiI6IjJkN..."
```

**Step 4: Update Database Records**
```bash
php artisan tinker
```

```php
use Multicoin\TokenSweeper\Models\Chain;
use Illuminate\Support\Facades\Crypt;

$chain = Chain::where('chain_id', 1)->first();
$chain->master_private_key_encrypted = Crypt::encryptString('0xYourPrivateKey');
$chain->save();
```

**Step 5: Test Decryption**
```bash
php artisan tinker
```

```php
use Illuminate\Support\Facades\Crypt;
$encrypted = env('ETH_MASTER_KEY_ENCRYPTED');
$decrypted = Crypt::decryptString($encrypted);
echo $decrypted; // Should return your private key
```

**Prevention Tips:**
- Never change `APP_KEY` in production without re-encrypting data
- Backup encrypted values before key rotation
- Document encryption process for team
- Use Laravel Vault or similar for key management in production

---

### Issue 2.3: Gas Configuration Too Low

**Problem:**
Token transfers fail with "out of gas" error.

**Example Error:**
```
Transaction execution error: out of gas
```

**Possible Causes:**
- `gas_limit_token_transfer` set too low
- Complex token contracts requiring more gas
- `gas_amount_wei` insufficient for network fees

**Solutions:**

**Step 1: Check Current Gas Configuration**
```bash
php artisan tinker
```

```php
use Multicoin\TokenSweeper\Models\Chain;
$chain = Chain::where('chain_id', 1)->first();
echo "Gas Limit: " . $chain->gas_limit_token_transfer;
echo "Gas Amount: " . $chain->gas_amount_wei;
```

**Step 2: Estimate Required Gas**
```bash
php artisan tinker
```

```php
use Multicoin\TokenSweeper\Services\Web3Service;
use Multicoin\TokenSweeper\Models\Chain;

$chain = Chain::where('chain_id', 1)->first();
$web3 = new Web3Service($chain->rpc_url, $chain->chain_id);

// Estimate gas for token transfer
$transaction = [
    'from' => '0xDepositAddress',
    'to' => '0xTokenContractAddress',
    'data' => '0xa9059cbb...' // Transfer method signature
];

$estimatedGas = hexdec($web3->estimateGas($transaction));
echo "Estimated Gas: " . $estimatedGas;
```

**Step 3: Update Gas Configuration**
```bash
php artisan tinker
```

```php
use Multicoin\TokenSweeper\Models\Chain;

// Update Ethereum configuration
$eth = Chain::where('chain_id', 1)->first();
$eth->gas_limit_token_transfer = 150000; // Increase from 100000
$eth->gas_amount_wei = '3000000000000000'; // 0.003 ETH instead of 0.002
$eth->save();

// Update BSC configuration
$bsc = Chain::where('chain_id', 56)->first();
$bsc->gas_limit_token_transfer = 150000;
$bsc->gas_amount_wei = '2000000000000000'; // 0.002 BNB
$bsc->save();
```

**Step 4: Alternative - Configure in .env**
```env
# config/token-sweeper.php reads these
SWEEPER_GAS_LIMIT_ETH=150000
SWEEPER_GAS_AMOUNT_ETH=3000000000000000
```

**Prevention Tips:**
- Test gas limits on testnets first
- Monitor gas usage in logs
- Set buffer of 20-30% above estimated gas
- Different tokens may require different limits

---

## 3. Database Issues

### Issue 3.1: Connection Timeout Errors

**Problem:**
Database queries timeout during sweeps or monitoring.

**Example Error:**
```
SQLSTATE[HY000]: General error: 2006 MySQL server has gone away
```

**Possible Causes:**
- Database connection timeout too short
- Long-running transactions
- Network issues between app and database
- Database server overloaded

**Solutions:**

**Step 1: Increase Database Timeout**
```php
// config/database.php
'mysql' => [
    'driver' => 'mysql',
    // ...
    'options' => [
        PDO::ATTR_TIMEOUT => 30,
        PDO::ATTR_PERSISTENT => false,
    ],
    'connect_timeout' => 10,
],
```

**Step 2: Adjust MySQL Server Settings**
```sql
-- MySQL configuration (my.cnf or my.ini)
[mysqld]
wait_timeout = 600
interactive_timeout = 600
max_allowed_packet = 64M
```

**Step 3: Reconnect in Long-Running Jobs**
```php
// In your custom jobs or commands
use Illuminate\Support\Facades\DB;

// Before database operations
DB::reconnect();

// Your database queries here
```

**Step 4: Use Database Connection Pooling**
```php
// config/database.php
'mysql' => [
    'driver' => 'mysql',
    // ...
    'pool' => [
        'max_connections' => 10,
        'max_idle_time' => 30,
    ],
],
```

**Prevention Tips:**
- Monitor database connection pool
- Use queue timeout shorter than database timeout
- Implement retry logic for failed connections
- Consider using Redis for caching to reduce DB load

---

### Issue 3.2: Duplicate Entry Errors

**Problem:**
Cannot create deposit address or sweep record.

**Example Error:**
```
SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '0x123...' for key 'sweeper_deposit_addresses.address'
```

**Possible Causes:**
- Attempting to create duplicate deposit address
- Race condition in address generation
- Duplicate sweep triggered
- Database constraints working correctly (expected behavior)

**Solutions:**

**Step 1: Check for Existing Record**
```php
use Multicoin\TokenSweeper\Models\DepositAddress;

// Before creating new address
$existing = DepositAddress::where('address', $address)
    ->where('chain_id', $chainId)
    ->first();

if ($existing) {
    // Address already exists, handle accordingly
    return $existing->address;
}
```

**Step 2: Use updateOrCreate**
```php
use Multicoin\TokenSweeper\Models\PendingSweep;

// Instead of create(), use updateOrCreate
$sweep = PendingSweep::updateOrCreate(
    [
        'deposit_address' => $depositAddress,
        'chain_id' => $chainId,
        'token_address' => $tokenAddress,
    ],
    [
        'token_symbol' => $token->symbol,
        'amount' => '0',
        'status' => 'pending',
    ]
);
```

**Step 3: Handle Race Conditions**
```php
use Illuminate\Support\Facades\DB;

try {
    DB::beginTransaction();

    // Your operations here
    $address = DepositAddress::create([...]);

    DB::commit();
} catch (\Illuminate\Database\QueryException $e) {
    DB::rollBack();

    if ($e->errorInfo[1] == 1062) { // Duplicate entry
        // Handle duplicate gracefully
        $address = DepositAddress::where('address', $addressValue)->first();
    } else {
        throw $e;
    }
}
```

**Step 4: Add Unique Job IDs**
```php
// In Job classes
class FundDepositAddress implements ShouldQueue, ShouldBeUnique
{
    // Prevents duplicate jobs for same sweep
    public function uniqueId(): string
    {
        return "fund-{$this->sweep->id}";
    }
}
```

**Prevention Tips:**
- Use database transactions for related operations
- Implement proper unique constraints
- Use `firstOrCreate()` or `updateOrCreate()` methods
- Add unique job IDs to prevent duplicate processing

---

### Issue 3.3: Foreign Key Constraint Failures

**Problem:**
Cannot delete or update records due to foreign key constraints.

**Example Error:**
```
SQLSTATE[23000]: Integrity constraint violation: 1451 Cannot delete or update a parent row
```

**Possible Causes:**
- Attempting to delete chain with existing deposits
- Orphaned records in related tables
- Missing cascade delete configuration

**Solutions:**

**Step 1: Check Related Records**
```bash
php artisan tinker
```

```php
use Multicoin\TokenSweeper\Models\Chain;

$chain = Chain::find(1);

// Check dependencies
echo "Tokens: " . $chain->tokens()->count();
echo "Deposit Addresses: " . $chain->depositAddresses()->count();
echo "Pending Sweeps: " . $chain->pendingSweeps()->count();
```

**Step 2: Delete Related Records First**
```php
use Illuminate\Support\Facades\DB;

DB::transaction(function () use ($chainId) {
    // Delete in correct order
    SweepLog::where('chain_id', $chainId)->delete();
    PendingSweep::where('chain_id', $chainId)->delete();
    DepositAddress::where('chain_id', $chainId)->delete();
    Token::where('chain_id', $chainId)->delete();
    Chain::where('chain_id', $chainId)->delete();
});
```

**Step 3: Use Soft Deletes Instead**
```php
// In model
use Illuminate\Database\Eloquent\SoftDeletes;

class Chain extends Model
{
    use SoftDeletes;
}

// Usage
$chain->delete(); // Soft delete
$chain->forceDelete(); // Permanent delete
```

**Step 4: Update Migration for Cascade**
```php
// database/migrations/xxx_create_tokens_table.php
Schema::create('sweeper_tokens', function (Blueprint $table) {
    // ...
    $table->foreign('chain_id')
        ->references('chain_id')
        ->on('sweeper_chains')
        ->onDelete('cascade'); // Add this
});
```

**Prevention Tips:**
- Plan deletion strategy before removing parent records
- Use soft deletes for important data
- Document foreign key relationships
- Test deletions in development first

---

## 4. RPC Connection Errors

### Issue 4.1: Connection Refused / Timeout

**Problem:**
Cannot connect to blockchain RPC endpoint.

**Example Error:**
```
cURL error 7: Failed to connect to eth.nownodes.io port 443: Connection refused
cURL error 28: Operation timed out after 30000 milliseconds
```

**Possible Causes:**
- RPC provider is down
- API key invalid or expired
- Firewall blocking outbound connections
- DNS resolution issues
- Rate limiting

**Solutions:**

**Step 1: Test RPC Endpoint Manually**
```bash
# Test with curl
curl -X POST https://eth.nownodes.io/YOUR_API_KEY \
  -H "Content-Type: application/json" \
  -d '{"jsonrpc":"2.0","method":"eth_blockNumber","params":[],"id":1}' \
  -v

# Check DNS resolution
nslookup eth.nownodes.io
ping eth.nownodes.io
```

**Step 2: Verify API Key**
```bash
# Login to NOWNodes dashboard
# Check API key status and limits
# Regenerate if necessary
```

**Step 3: Check Firewall Rules**
```bash
# Linux - check iptables
sudo iptables -L -n -v

# Allow HTTPS outbound
sudo iptables -A OUTPUT -p tcp --dport 443 -j ACCEPT

# Check if server can reach external HTTPS
curl -I https://google.com
```

**Step 4: Increase Timeout in Web3Service**
```php
// src/Services/Web3Service.php
public function __construct(string $rpcUrl, int $chainId)
{
    $this->client = new Client([
        'timeout' => 60, // Increase from 30
        'connect_timeout' => 10,
        'retry_on_timeout' => true,
    ]);
    $this->rpcUrl = $rpcUrl;
    $this->chainId = $chainId;
}
```

**Step 5: Implement Retry Logic**
```php
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

public function __construct(string $rpcUrl, int $chainId)
{
    $handlerStack = HandlerStack::create();

    // Retry middleware
    $handlerStack->push(Middleware::retry(function ($retries, $request, $response, $exception) {
        // Retry on connection errors
        if ($exception instanceof ConnectException) {
            return $retries < 3;
        }
        return false;
    }));

    $this->client = new Client([
        'handler' => $handlerStack,
        'timeout' => 60,
    ]);
    // ...
}
```

**Step 6: Use Backup RPC Endpoints**
```php
// config/token-sweeper.php
'rpc_urls' => [
    1 => [
        env('ETH_RPC_URL'), // Primary
        'https://eth.llamarpc.com', // Backup 1
        'https://rpc.ankr.com/eth', // Backup 2
    ],
],
```

**Prevention Tips:**
- Monitor RPC provider status
- Implement failover to backup providers
- Set up alerts for connection failures
- Cache non-critical RPC responses

---

### Issue 4.2: Rate Limiting / Too Many Requests

**Problem:**
RPC requests are being throttled.

**Example Error:**
```
RPC Error: {"code":-32005,"message":"Request rate exceeded"}
429 Too Many Requests
```

**Possible Causes:**
- Exceeding NOWNodes API rate limits
- Too many concurrent requests
- Monitor polling too frequently
- Insufficient API plan tier

**Solutions:**

**Step 1: Check Current Rate Limits**
```bash
# NOWNodes free tier: 50 requests/second
# Pro tier: varies by plan

# Login to NOWNodes dashboard to check your limits
```

**Step 2: Implement Request Rate Limiting**
```php
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

// In Web3Service
public function call(string $method, array $params = []): mixed
{
    $key = 'rpc_' . $this->chainId;

    // Wait if rate limit exceeded
    RateLimiter::attempt(
        $key,
        $perMinute = 2000, // Adjust based on your plan
        function() use ($method, $params) {
            return $this->executeRequest($method, $params);
        }
    );
}
```

**Step 3: Reduce Monitor Polling Frequency**
```env
# .env - Increase check interval
SWEEPER_CHECK_INTERVAL=10  # From 5 to 10 seconds
```

```php
// config/token-sweeper.php
'monitoring' => [
    'check_interval' => env('SWEEPER_CHECK_INTERVAL', 10),
],
```

**Step 4: Implement Caching**
```php
// Cache gas prices
public function gasPrice(): string
{
    $cacheKey = "gas_price_{$this->chainId}";

    return Cache::remember($cacheKey, 30, function () { // Cache for 30 seconds
        return $this->call('eth_gasPrice', []);
    });
}

// Cache block number
public function getBlockNumber(): string
{
    $cacheKey = "block_number_{$this->chainId}";

    return Cache::remember($cacheKey, 5, function () {
        return $this->call('eth_blockNumber', []);
    });
}
```

**Step 5: Batch RPC Requests**
```php
// Instead of multiple single calls
public function batchCall(array $requests): array
{
    $batch = [];
    foreach ($requests as $id => $request) {
        $batch[] = [
            'jsonrpc' => '2.0',
            'method' => $request['method'],
            'params' => $request['params'] ?? [],
            'id' => $id,
        ];
    }

    $response = $this->client->post($this->rpcUrl, ['json' => $batch]);
    return json_decode($response->getBody()->getContents(), true);
}
```

**Step 6: Upgrade NOWNodes Plan**
```bash
# If hitting limits frequently, upgrade to:
# - Pro: 100 req/s
# - Business: 200 req/s
# - Enterprise: Custom limits
```

**Prevention Tips:**
- Monitor API usage metrics
- Implement exponential backoff on rate limit errors
- Cache frequently accessed data
- Use batch requests when possible
- Consider running your own RPC node for high volume

---

### Issue 4.3: Invalid RPC Response / Parsing Errors

**Problem:**
RPC returns unexpected response format.

**Example Error:**
```
Trying to access array offset on value of type null
Undefined array key "result"
```

**Possible Causes:**
- RPC endpoint returning error
- Network issues causing incomplete response
- RPC provider API changes
- Invalid request parameters

**Solutions:**

**Step 1: Add Response Validation**
```php
// In Web3Service::call()
public function call(string $method, array $params = []): mixed
{
    try {
        $response = $this->client->post($this->rpcUrl, [
            'json' => [
                'jsonrpc' => '2.0',
                'method' => $method,
                'params' => $params,
                'id' => time(),
            ],
        ]);

        $body = $response->getBody()->getContents();

        // Validate response is valid JSON
        $result = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Invalid JSON response: " . json_last_error_msg());
        }

        // Check for RPC error
        if (isset($result['error'])) {
            throw new \Exception(
                "RPC Error [{$result['error']['code']}]: {$result['error']['message']}"
            );
        }

        // Validate result exists
        if (!array_key_exists('result', $result)) {
            throw new \Exception("RPC response missing 'result' field: " . $body);
        }

        return $result['result'];

    } catch (\GuzzleHttp\Exception\GuzzleException $e) {
        throw new \Exception("RPC request failed: " . $e->getMessage());
    }
}
```

**Step 2: Log Full Responses for Debugging**
```php
use Illuminate\Support\Facades\Log;

public function call(string $method, array $params = []): mixed
{
    $response = $this->client->post($this->rpcUrl, [
        'json' => [
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
            'id' => time(),
        ],
    ]);

    $body = $response->getBody()->getContents();

    // Log for debugging
    Log::debug("RPC Request: {$method}", [
        'params' => $params,
        'response' => $body,
    ]);

    // Continue with parsing...
}
```

**Step 3: Test RPC Methods Manually**
```bash
# Test specific method
curl -X POST https://eth.nownodes.io/YOUR_API_KEY \
  -H "Content-Type: application/json" \
  -d '{
    "jsonrpc":"2.0",
    "method":"eth_getBalance",
    "params":["0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb", "latest"],
    "id":1
  }'
```

**Step 4: Handle Null Responses Gracefully**
```php
public function getBalance(string $address): string
{
    $balance = $this->call('eth_getBalance', [$address, 'latest']);

    // Handle null/empty response
    if ($balance === null || $balance === '') {
        throw new \Exception("Failed to get balance for address: {$address}");
    }

    return $balance;
}
```

**Prevention Tips:**
- Always validate RPC responses
- Implement comprehensive error handling
- Log unexpected responses for analysis
- Test with different RPC providers
- Monitor RPC provider status pages

---

## 5. Transaction Failures

### Issue 5.1: Insufficient Funds for Gas

**Problem:**
Transaction fails due to insufficient native token balance.

**Example Error:**
```
Transaction execution error: insufficient funds for gas * price + value
RPC Error: {"code":-32000,"message":"insufficient funds for transfer"}
```

**Possible Causes:**
- Master wallet has insufficient native token (ETH/BNB/MATIC)
- Gas prices spiked unexpectedly
- Deposit address not funded before sweep
- Concurrent transactions depleting master wallet

**Solutions:**

**Step 1: Check Master Wallet Balance**
```bash
php artisan sweeper:balance 0xMasterWalletAddress "" 1
```

Or via tinker:
```php
use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Services\Web3Service;

$chain = Chain::where('chain_id', 1)->first();
$web3 = new Web3Service($chain->rpc_url, $chain->chain_id);

$balance = hexdec($web3->getBalance($chain->master_wallet_address));
$balanceEth = $balance / 1e18;

echo "Master Wallet Balance: {$balanceEth} ETH\n";
```

**Step 2: Fund Master Wallet**
```bash
# Send ETH/BNB/MATIC to master wallet
# Recommended minimum balances:
# - Ethereum: 0.1 ETH
# - BSC: 0.05 BNB
# - Polygon: 10 MATIC
```

**Step 3: Implement Balance Monitoring**
```php
// Create a scheduled job to check master wallet balances
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        $chains = Chain::active()->get();

        foreach ($chains as $chain) {
            $web3 = new Web3Service($chain->rpc_url, $chain->chain_id);
            $balance = hexdec($web3->getBalance($chain->master_wallet_address));
            $balanceFloat = $balance / 1e18;

            // Minimum thresholds
            $minimums = [
                1 => 0.05,    // ETH
                56 => 0.02,   // BSC
                137 => 5,     // Polygon
            ];

            if ($balanceFloat < ($minimums[$chain->chain_id] ?? 0.01)) {
                // Send alert
                Log::alert("Low master wallet balance on {$chain->name}: {$balanceFloat}");

                // Send notification (email, Slack, etc.)
                Notification::route('slack', config('services.slack.webhook'))
                    ->notify(new LowBalanceAlert($chain, $balanceFloat));
            }
        }
    })->hourly();
}
```

**Step 4: Adjust Gas Amount for Funding**
```php
// Reduce gas amount sent to deposit addresses
use Multicoin\TokenSweeper\Models\Chain;

$chain = Chain::where('chain_id', 1)->first();
$chain->gas_amount_wei = '1500000000000000'; // Reduce to 0.0015 ETH
$chain->save();
```

**Step 5: Implement Gas Price Ceiling**
```php
// In SweeperService or Web3Service
public function getGasPrice(): int
{
    $gasPrice = hexdec($this->call('eth_gasPrice', []));

    // Set maximum gas price (e.g., 100 Gwei for Ethereum)
    $maxGasPrice = 100 * 1e9; // 100 Gwei

    if ($gasPrice > $maxGasPrice) {
        throw new \Exception(
            "Gas price too high: " . ($gasPrice / 1e9) . " Gwei. Max: " . ($maxGasPrice / 1e9)
        );
    }

    return $gasPrice;
}
```

**Prevention Tips:**
- Monitor master wallet balances continuously
- Set up low balance alerts
- Keep buffer of 2x expected daily usage
- Implement automatic top-up mechanisms
- Monitor gas prices and pause sweeps during spikes

---

### Issue 5.2: Nonce Too Low / Too High

**Problem:**
Transaction fails due to incorrect nonce.

**Example Error:**
```
RPC Error: {"code":-32000,"message":"nonce too low"}
RPC Error: {"code":-32000,"message":"nonce too high"}
Transaction has been mined but is not included in the block
```

**Possible Causes:**
- Multiple transactions sent simultaneously
- RPC returning stale nonce
- Transaction stuck in mempool
- Concurrent sweeps from same wallet

**Solutions:**

**Step 1: Implement Nonce Management**
```php
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class NonceManager
{
    public function getNextNonce(string $address, int $chainId): int
    {
        $key = "nonce_{$chainId}_{$address}";

        return Redis::eval(<<<'LUA'
            local nonce = redis.call('GET', KEYS[1])
            if not nonce then
                nonce = ARGV[1]
            end
            nonce = tonumber(nonce) + 1
            redis.call('SET', KEYS[1], nonce)
            redis.call('EXPIRE', KEYS[1], 300)
            return nonce
        LUA, 1, $key, $this->getRpcNonce($address, $chainId));
    }

    protected function getRpcNonce(string $address, int $chainId): int
    {
        $web3 = new Web3Service($rpcUrl, $chainId);
        return hexdec($web3->getTransactionCount($address));
    }
}
```

**Step 2: Add Nonce Lock**
```php
use Illuminate\Support\Facades\Cache;

public function fundAddressWithGas(string $depositAddress, Chain $chain, Web3Service $web3): string
{
    $lockKey = "nonce_lock_{$chain->chain_id}_{$chain->master_wallet_address}";

    $lock = Cache::lock($lockKey, 10); // 10 second lock

    try {
        $lock->block(5); // Wait up to 5 seconds to acquire lock

        // Get nonce with lock held
        $nonce = hexdec($web3->getTransactionCount($chain->master_wallet_address));

        // Build and send transaction
        $transaction = [
            'nonce' => $nonce,
            // ... rest of transaction
        ];

        $signedTx = $this->signer->signTransaction($transaction, $privateKey, $chain->chain_id);
        $txHash = $web3->sendRawTransaction($signedTx);

        return $txHash;

    } finally {
        $lock->release();
    }
}
```

**Step 3: Handle Nonce Conflicts**
```php
public function sendTransactionWithRetry(array $transaction, string $privateKey, int $chainId, int $maxRetries = 3): string
{
    $attempt = 0;

    while ($attempt < $maxRetries) {
        try {
            // Get fresh nonce
            $nonce = hexdec($this->web3->getTransactionCount($transaction['from']));
            $transaction['nonce'] = $nonce;

            $signedTx = $this->signer->signTransaction($transaction, $privateKey, $chainId);
            return $this->web3->sendRawTransaction($signedTx);

        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'nonce too low')) {
                // Nonce conflict, retry with fresh nonce
                $attempt++;
                sleep(1);
                continue;
            }

            throw $e;
        }
    }

    throw new \Exception("Failed to send transaction after {$maxRetries} attempts");
}
```

**Step 4: Clear Stuck Transactions**
```php
// If a transaction is stuck with nonce 5, send a replacement with higher gas
public function replaceStuckTransaction(string $from, int $stuckNonce, int $chainId): string
{
    $web3 = new Web3Service($rpcUrl, $chainId);
    $gasPrice = hexdec($web3->gasPrice());

    // Send transaction with same nonce but 10% higher gas price
    $transaction = [
        'nonce' => $stuckNonce,
        'gasPrice' => $gasPrice * 1.1,
        'gasLimit' => 21000,
        'to' => $from, // Send to self
        'value' => '0x0',
        'data' => ''
    ];

    $signedTx = $this->signer->signTransaction($transaction, $privateKey, $chainId);
    return $web3->sendRawTransaction($signedTx);
}
```

**Step 5: Monitor Nonce Gaps**
```bash
php artisan tinker
```

```php
use Multicoin\TokenSweeper\Services\Web3Service;

$web3 = new Web3Service($rpcUrl, 1);
$address = '0xYourMasterWallet';

// Get current nonce from RPC
$rpcNonce = hexdec($web3->getTransactionCount($address));

// Compare with pending nonce
$pendingNonce = hexdec($web3->call('eth_getTransactionCount', [$address, 'pending']));

echo "RPC Nonce: {$rpcNonce}\n";
echo "Pending Nonce: {$pendingNonce}\n";
echo "Stuck transactions: " . ($pendingNonce - $rpcNonce) . "\n";
```

**Prevention Tips:**
- Use nonce management with locks for concurrent operations
- Avoid parallel transactions from same address
- Monitor pending transactions
- Implement transaction queuing
- Use database-backed nonce tracking for production

---

### Issue 5.3: Transaction Reverted / Failed

**Problem:**
Transaction is mined but execution fails.

**Example Error:**
```
Transaction has been mined but execution failed
Receipt status: 0x0 (failed)
```

**Possible Causes:**
- Smart contract logic rejection
- Insufficient token balance in deposit address
- Allowance not set (shouldn't happen with transfer)
- Reentrancy guard triggered
- Gas limit too low causing out-of-gas

**Solutions:**

**Step 1: Check Transaction Receipt**
```bash
php artisan tinker
```

```php
use Multicoin\TokenSweeper\Services\Web3Service;

$web3 = new Web3Service($rpcUrl, 1);
$txHash = '0xYourTransactionHash';

$receipt = $web3->getTransactionReceipt($txHash);
print_r($receipt);

// Check status
echo "Status: " . ($receipt['status'] === '0x1' ? 'Success' : 'Failed') . "\n";
echo "Gas Used: " . hexdec($receipt['gasUsed']) . "\n";

// If failed, check logs for revert reason
if (isset($receipt['logs']) && empty($receipt['logs'])) {
    echo "Transaction reverted with no logs (possible require/revert)\n";
}
```

**Step 2: Verify Token Balance Before Sweep**
```php
// In SweepTokens job, add validation
public function handle(WalletService $walletService, TransactionSignerService $signer): void
{
    // ... existing code ...

    // Get token balance
    $balanceHex = $web3->callContract(
        $this->sweep->token_address,
        '0x70a08231' . str_pad(str_replace('0x', '', $this->sweep->deposit_address), 64, '0', STR_PAD_LEFT)
    );

    $balance = hexdec($balanceHex);

    if ($balance === 0) {
        throw new \Exception('No tokens to sweep - balance is 0');
    }

    // Log the balance for debugging
    Log::info("Token balance to sweep", [
        'address' => $this->sweep->deposit_address,
        'token' => $this->sweep->token_address,
        'balance' => $balance,
        'balance_readable' => $balance / (10 ** $token->decimals),
    ]);

    // Continue with sweep...
}
```

**Step 3: Test Contract Call Before Sending**
```php
// Use eth_call to simulate transaction before sending
public function simulateTokenTransfer(string $from, string $to, string $tokenAddress, int $amount): bool
{
    $transferData = '0xa9059cbb' .
        str_pad(str_replace('0x', '', $to), 64, '0', STR_PAD_LEFT) .
        str_pad(dechex($amount), 64, '0', STR_PAD_LEFT);

    try {
        $result = $this->web3->call('eth_call', [
            [
                'from' => $from,
                'to' => $tokenAddress,
                'data' => $transferData
            ],
            'latest'
        ]);

        // If call succeeds, transaction should work
        return true;

    } catch (\Exception $e) {
        Log::error("Simulation failed", [
            'from' => $from,
            'to' => $to,
            'error' => $e->getMessage()
        ]);
        return false;
    }
}
```

**Step 4: Decode Revert Reason**
```php
public function getRevertReason(string $txHash): ?string
{
    try {
        // Get transaction
        $tx = $this->web3->call('eth_getTransactionByHash', [$txHash]);

        if (!$tx) {
            return null;
        }

        // Replay transaction to get revert reason
        $result = $this->web3->call('eth_call', [
            [
                'from' => $tx['from'],
                'to' => $tx['to'],
                'data' => $tx['input'],
                'gas' => $tx['gas'],
                'gasPrice' => $tx['gasPrice'],
                'value' => $tx['value'],
            ],
            $tx['blockNumber']
        ]);

        return $result;

    } catch (\Exception $e) {
        // Extract revert reason from error message
        if (preg_match('/execution reverted: (.+)/', $e->getMessage(), $matches)) {
            return $matches[1];
        }
        return $e->getMessage();
    }
}
```

**Step 5: Increase Gas Limit**
```php
// If seeing out-of-gas errors
use Multicoin\TokenSweeper\Models\Chain;

$chain = Chain::where('chain_id', 1)->first();
$chain->gas_limit_token_transfer = 200000; // Increase significantly
$chain->save();
```

**Prevention Tips:**
- Always simulate transactions before sending
- Verify all prerequisites (balance, gas, etc.)
- Log detailed transaction parameters
- Monitor for patterns in failed transactions
- Test with different tokens to identify token-specific issues

---

## 6. Sweep Workflow Issues

### Issue 6.1: Deposits Not Detected

**Problem:**
Monitor service running but deposits not triggering sweeps.

**Possible Causes:**
- Monitor not checking correct addresses
- RPC not returning recent blocks
- Deposit address not in database
- Event detection logic issue
- Block confirmation requirement too high

**Solutions:**

**Step 1: Verify Monitor is Running**
```bash
# Check process
ps aux | grep "sweeper:monitor"

# Check logs
tail -f storage/logs/laravel.log | grep -i "monitor"
```

**Step 2: Verify Deposit Addresses in Database**
```bash
php artisan tinker
```

```php
use Multicoin\TokenSweeper\Models\DepositAddress;

// Check if address exists
$address = '0xYourDepositAddress';
$depositAddress = DepositAddress::where('address', $address)->first();

if (!$depositAddress) {
    echo "Address not found in database!\n";
} else {
    echo "Address found: Chain {$depositAddress->chain_id}, User {$depositAddress->user_id}\n";
}

// List all deposit addresses for a chain
$addresses = DepositAddress::where('chain_id', 1)->get();
echo "Total deposit addresses: " . $addresses->count() . "\n";
```

**Step 3: Manually Check Balance on RPC**
```bash
php artisan tinker
```

```php
use Multicoin\TokenSweeper\Services\Web3Service;

$web3 = new Web3Service('https://eth.nownodes.io/YOUR_KEY', 1);
$address = '0xYourDepositAddress';
$tokenAddress = '0xdAC17F958D2ee523a2206206994597C13D831ec7'; // USDT

// Check native balance
$nativeBalance = hexdec($web3->getBalance($address));
echo "Native balance: " . ($nativeBalance / 1e18) . " ETH\n";

// Check token balance
$tokenBalanceHex = $web3->callContract(
    $tokenAddress,
    '0x70a08231' . str_pad(str_replace('0x', '', $address), 64, '0', STR_PAD_LEFT)
);
$tokenBalance = hexdec($tokenBalanceHex);
echo "Token balance: " . ($tokenBalance / 1e6) . " USDT\n"; // USDT has 6 decimals
```

**Step 4: Check Monitor Configuration**
```php
// config/token-sweeper.php
'monitoring' => [
    'check_interval' => env('SWEEPER_CHECK_INTERVAL', 5),
    'block_confirmations' => env('SWEEPER_CONFIRMATIONS', 1), // Try reducing to 0 or 1
],
```

**Step 5: Test Detection Manually**
```bash
# Trigger manual sweep
php artisan sweeper:sweep 0xDepositAddress 0xTokenAddress 1
```

**Step 6: Review Monitor Logic**
```php
// src/Sweeper/MonitorService.php
// Add debug logging

use Illuminate\Support\Facades\Log;

public function checkForDeposits(): void
{
    $addresses = DepositAddress::all();

    Log::debug("Checking deposits", [
        'total_addresses' => $addresses->count(),
    ]);

    foreach ($addresses as $depositAddress) {
        Log::debug("Checking address", [
            'address' => $depositAddress->address,
            'chain_id' => $depositAddress->chain_id,
        ]);

        // ... rest of logic
    }
}
```

**Prevention Tips:**
- Monitor the monitor process with Supervisor
- Implement health check endpoint
- Log every scan iteration
- Set up alerts for monitor downtime
- Test deposit detection in development

---

### Issue 6.2: Sweep Stuck in "Funding" Status

**Problem:**
Sweep status remains "funding" and never progresses to "sweeping".

**Possible Causes:**
- Funding transaction not confirmed
- Funding transaction failed but status not updated
- Job not dispatched to sweep after funding
- Queue worker not processing jobs

**Solutions:**

**Step 1: Check Pending Sweep Status**
```bash
php artisan tinker
```

```php
use Multicoin\TokenSweeper\Models\PendingSweep;

$sweep = PendingSweep::where('status', 'funding')
    ->orderBy('updated_at', 'desc')
    ->first();

if ($sweep) {
    echo "Sweep ID: {$sweep->id}\n";
    echo "Status: {$sweep->status}\n";
    echo "Funding TX: {$sweep->funding_tx_hash}\n";
    echo "Updated: {$sweep->updated_at}\n";
}
```

**Step 2: Verify Funding Transaction**
```bash
php artisan tinker
```

```php
use Multicoin\TokenSweeper\Services\Web3Service;
use Multicoin\TokenSweeper\Models\Chain;

$chain = Chain::where('chain_id', 1)->first();
$web3 = new Web3Service($chain->rpc_url, $chain->chain_id);

$txHash = '0xYourFundingTxHash';
$receipt = $web3->getTransactionReceipt($txHash);

if ($receipt) {
    echo "Status: " . ($receipt['status'] === '0x1' ? 'Success' : 'Failed') . "\n";
    echo "Block: " . hexdec($receipt['blockNumber']) . "\n";
    echo "Gas Used: " . hexdec($receipt['gasUsed']) . "\n";
} else {
    echo "Transaction not found or not mined yet\n";
}
```

**Step 3: Check Queue for Stuck Jobs**
```bash
# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all

# Check queue status (if using Redis)
redis-cli
> LLEN queues:default
> LRANGE queues:default 0 -1
```

**Step 4: Manually Progress Sweep**
```bash
php artisan tinker
```

```php
use Multicoin\TokenSweeper\Models\PendingSweep;
use Multicoin\TokenSweeper\Jobs\SweepTokens;

// Find stuck sweep
$sweep = PendingSweep::find(123);

// Check if funding actually completed
$chain = $sweep->chain;
$web3 = new Web3Service($chain->rpc_url, $chain->chain_id);

if ($sweep->funding_tx_hash) {
    $receipt = $web3->getTransactionReceipt($sweep->funding_tx_hash);

    if ($receipt && $receipt['status'] === '0x1') {
        // Funding was successful, dispatch sweep job
        $sweep->update(['status' => 'funded']);
        SweepTokens::dispatch($sweep);
        echo "Sweep job dispatched\n";
    } else {
        echo "Funding transaction failed or not confirmed\n";
    }
}
```

**Step 5: Implement Timeout Recovery**
```php
// Create scheduled job to recover stuck sweeps
// app/Console/Kernel.php

protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        $stuckSweeps = PendingSweep::where('status', 'funding')
            ->where('updated_at', '<', now()->subMinutes(10))
            ->get();

        foreach ($stuckSweeps as $sweep) {
            try {
                $chain = $sweep->chain;
                $web3 = new Web3Service($chain->rpc_url, $chain->chain_id);

                if ($sweep->funding_tx_hash) {
                    $receipt = $web3->getTransactionReceipt($sweep->funding_tx_hash);

                    if ($receipt) {
                        if ($receipt['status'] === '0x1') {
                            // Success - continue to sweep
                            SweepTokens::dispatch($sweep);
                        } else {
                            // Failed - mark and retry
                            $sweep->markAsFailed('Funding transaction failed');
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::error("Failed to recover stuck sweep", [
                    'sweep_id' => $sweep->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
    })->everyFiveMinutes();
}
```

**Prevention Tips:**
- Implement sweep timeout monitoring
- Add automatic recovery for stuck sweeps
- Monitor queue depth
- Set job timeouts appropriately
- Log state transitions

---

### Issue 6.3: Multiple Sweeps for Same Deposit

**Problem:**
Same deposit address being swept multiple times.

**Possible Causes:**
- Monitor detecting same deposit repeatedly
- Duplicate jobs dispatched
- Race condition in sweep creation
- No deduplication logic

**Solutions:**

**Step 1: Check for Duplicate Pending Sweeps**
```bash
php artisan tinker
```

```php
use Multicoin\TokenSweeper\Models\PendingSweep;
use Illuminate\Support\Facades\DB;

// Find duplicates
$duplicates = PendingSweep::select('deposit_address', 'token_address', 'chain_id', DB::raw('COUNT(*) as count'))
    ->groupBy('deposit_address', 'token_address', 'chain_id')
    ->having('count', '>', 1)
    ->get();

foreach ($duplicates as $dup) {
    echo "Duplicate: {$dup->deposit_address} - {$dup->token_address} ({$dup->count} times)\n";
}
```

**Step 2: Implement Unique Job IDs**
```php
// In FundDepositAddress job
use Illuminate\Contracts\Queue\ShouldBeUnique;

class FundDepositAddress implements ShouldQueue, ShouldBeUnique
{
    public $tries = 3;
    public $timeout = 300;

    // Prevent duplicate jobs
    public function uniqueId(): string
    {
        return "fund-sweep-{$this->sweep->deposit_address}-{$this->sweep->token_address}-{$this->sweep->chain_id}";
    }

    // How long to maintain uniqueness (seconds)
    public $uniqueFor = 3600; // 1 hour
}

// Similarly for SweepTokens job
class SweepTokens implements ShouldQueue, ShouldBeUnique
{
    public function uniqueId(): string
    {
        return "sweep-{$this->sweep->id}";
    }

    public $uniqueFor = 3600;
}
```

**Step 3: Add Database Constraint**
```php
// Create migration to add unique constraint
php artisan make:migration add_unique_constraint_to_pending_sweeps

// In migration
public function up()
{
    Schema::table('sweeper_pending_sweeps', function (Blueprint $table) {
        // Add unique index on active sweeps
        $table->unique(
            ['deposit_address', 'token_address', 'chain_id', 'status'],
            'unique_active_sweep'
        );
    });
}
```

**Step 4: Implement Check Before Creating Sweep**
```php
// In MonitorService or wherever sweeps are initiated
use Multicoin\TokenSweeper\Models\PendingSweep;

public function initiateSweep(string $depositAddress, string $tokenAddress, int $chainId): void
{
    // Check for existing pending/in-progress sweep
    $existingSweep = PendingSweep::where('deposit_address', $depositAddress)
        ->where('token_address', $tokenAddress)
        ->where('chain_id', $chainId)
        ->whereIn('status', ['pending', 'funding', 'sweeping'])
        ->first();

    if ($existingSweep) {
        Log::info("Sweep already in progress", [
            'sweep_id' => $existingSweep->id,
            'status' => $existingSweep->status,
        ]);
        return;
    }

    // Create new sweep
    $sweep = PendingSweep::create([
        'deposit_address' => $depositAddress,
        'token_address' => $tokenAddress,
        'chain_id' => $chainId,
        'status' => 'pending',
        // ...
    ]);

    FundDepositAddress::dispatch($sweep);
}
```

**Step 5: Add Idempotency Key**
```php
// Add to PendingSweep model
protected $fillable = [
    // ... existing fields
    'idempotency_key',
];

// Generate unique key when creating sweep
$idempotencyKey = hash('sha256', $depositAddress . $tokenAddress . $chainId . time());

$sweep = PendingSweep::updateOrCreate(
    ['idempotency_key' => $idempotencyKey],
    [
        'deposit_address' => $depositAddress,
        'token_address' => $tokenAddress,
        'chain_id' => $chainId,
        'status' => 'pending',
    ]
);
```

**Prevention Tips:**
- Always check for existing sweeps before creating new ones
- Use unique job IDs
- Implement database constraints
- Add idempotency keys
- Monitor for duplicate patterns

---

## 7. Queue and Job Problems

### Issue 7.1: Queue Worker Not Processing Jobs

**Problem:**
Jobs dispatched but not executing.

**Possible Causes:**
- Queue worker not running
- Wrong queue connection configured
- Redis connection issues
- Job serialization errors
- Worker crashed

**Solutions:**

**Step 1: Verify Queue Worker is Running**
```bash
# Check process
ps aux | grep "queue:work"

# Check supervisor status
sudo supervisorctl status token-sweeper-queue

# Start queue worker manually
php artisan queue:work --queue=default --tries=3 --timeout=300
```

**Step 2: Check Queue Configuration**
```php
// config/queue.php
'default' => env('QUEUE_CONNECTION', 'redis'),

'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 300,
        'block_for' => null,
    ],
],
```

```env
# .env
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

**Step 3: Test Redis Connection**
```bash
# Test Redis
redis-cli ping
# Should return: PONG

# Check queue length
redis-cli
> LLEN queues:default
> LRANGE queues:default 0 10

# Clear stuck jobs (caution!)
> DEL queues:default
```

**Step 4: Check for Failed Jobs**
```bash
# List failed jobs
php artisan queue:failed

# Retry specific job
php artisan queue:retry <job-id>

# Retry all failed jobs
php artisan queue:retry all

# Clear failed jobs
php artisan queue:flush
```

**Step 5: Check Job Serialization**
```bash
php artisan tinker
```

```php
use Multicoin\TokenSweeper\Models\PendingSweep;
use Multicoin\TokenSweeper\Jobs\FundDepositAddress;

$sweep = PendingSweep::first();

try {
    $job = new FundDepositAddress($sweep);
    $serialized = serialize($job);
    $unserialized = unserialize($serialized);
    echo "Serialization OK\n";
} catch (\Exception $e) {
    echo "Serialization error: " . $e->getMessage() . "\n";
}
```

**Step 6: Setup Supervisor (Production)**
```ini
# /etc/supervisor/conf.d/token-sweeper-queue.conf
[program:token-sweeper-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/artisan queue:work redis --queue=default --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/var/log/token-sweeper-queue.log
stopwaitsecs=3600
```

```bash
# Reload supervisor
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start token-sweeper-queue:*
```

**Prevention Tips:**
- Always run queue worker in production
- Use Supervisor or similar process manager
- Monitor queue depth
- Set up alerts for worker crashes
- Consider Laravel Horizon for better visibility

---

### Issue 7.2: Jobs Timing Out

**Problem:**
Jobs fail with timeout errors.

**Example Error:**
```
Illuminate\Queue\MaxAttemptsExceededException
Job has been attempted too many times or has timed out
```

**Possible Causes:**
- Job taking longer than timeout setting
- Waiting for blockchain confirmations
- RPC responses slow
- Network latency
- Database locks

**Solutions:**

**Step 1: Increase Job Timeout**
```php
// In FundDepositAddress.php and SweepTokens.php
class FundDepositAddress implements ShouldQueue
{
    public $tries = 3;
    public $timeout = 600; // Increase from 300 to 600 seconds (10 minutes)
}
```

**Step 2: Increase Queue Worker Timeout**
```bash
# When starting worker
php artisan queue:work --timeout=600

# In Supervisor config
command=php /var/www/artisan queue:work redis --timeout=600
```

**Step 3: Optimize Confirmation Waiting**
```php
// In Web3Service::waitForConfirmation()
public function waitForConfirmation(string $txHash, int $maxAttempts = 60, int $pollInterval = 2): bool
{
    // Reduce polling for faster responses
    $pollInterval = 1; // Check every 1 second instead of 2

    for ($i = 0; $i < $maxAttempts; $i++) {
        sleep($pollInterval);

        try {
            $receipt = $this->getTransactionReceipt($txHash);

            if ($receipt && isset($receipt['status'])) {
                return $receipt['status'] === '0x1';
            }
        } catch (\Exception $e) {
            // Continue polling on errors
            Log::warning("Error checking transaction receipt", [
                'tx_hash' => $txHash,
                'attempt' => $i,
                'error' => $e->getMessage()
            ]);
        }
    }

    throw new \Exception("Transaction confirmation timeout: $txHash");
}
```

**Step 4: Make Confirmation Asynchronous**
```php
// Instead of waiting in job, dispatch another job to check confirmation
class FundDepositAddress implements ShouldQueue
{
    public function handle(WalletService $walletService, TransactionSignerService $signer): void
    {
        // ... send transaction ...
        $txHash = $web3->sendRawTransaction($signedTx);

        $this->sweep->update([
            'status' => 'funding',
            'funding_tx_hash' => $txHash
        ]);

        // Don't wait for confirmation - dispatch job to check it
        CheckTransactionConfirmation::dispatch($this->sweep, $txHash, 'funding')
            ->delay(now()->addSeconds(10));
    }
}

// New job: CheckTransactionConfirmation
class CheckTransactionConfirmation implements ShouldQueue
{
    public function __construct(
        public PendingSweep $sweep,
        public string $txHash,
        public string $step // 'funding' or 'sweeping'
    ) {}

    public function handle(): void
    {
        $chain = $this->sweep->chain;
        $web3 = new Web3Service($chain->rpc_url, $chain->chain_id);

        $receipt = $web3->getTransactionReceipt($this->txHash);

        if (!$receipt) {
            // Not confirmed yet, check again later
            if ($this->attempts() < 30) {
                $this->release(5); // Check again in 5 seconds
            } else {
                $this->sweep->markAsFailed('Transaction confirmation timeout');
            }
            return;
        }

        if ($receipt['status'] === '0x1') {
            // Success
            if ($this->step === 'funding') {
                SweepTokens::dispatch($this->sweep);
            } else {
                $this->sweep->markAsCompleted($this->txHash);
            }
        } else {
            // Failed
            $this->sweep->markAsFailed('Transaction failed on blockchain');
        }
    }
}
```

**Step 5: Configure Job-Specific Timeouts**
```php
// config/queue.php
'connections' => [
    'redis' => [
        'driver' => 'redis',
        'connection' => 'default',
        'queue' => env('REDIS_QUEUE', 'default'),
        'retry_after' => 600, // Must be larger than job timeout
        'block_for' => null,
    ],
],
```

**Prevention Tips:**
- Set appropriate timeouts for blockchain operations
- Implement asynchronous confirmation checking
- Monitor job execution times
- Use separate queues for different job types
- Consider job batching for bulk operations

---

### Issue 7.3: Failed Jobs Not Retrying

**Problem:**
Jobs fail but don't retry or exceed max attempts.

**Possible Causes:**
- `$tries` set too low
- Exception not being caught properly
- Job marked as non-retryable
- Failed jobs table full

**Solutions:**

**Step 1: Increase Retry Attempts**
```php
// In job classes
class FundDepositAddress implements ShouldQueue
{
    public $tries = 5; // Increase from 3
    public $backoff = [60, 120, 300]; // Wait 1min, 2min, 5min between retries
}
```

**Step 2: Implement Custom Retry Logic**
```php
class FundDepositAddress implements ShouldQueue
{
    public $tries = 5;

    public function retryUntil(): DateTime
    {
        return now()->addHours(1); // Keep trying for 1 hour
    }

    public function failed(Throwable $exception): void
    {
        // Called when job finally fails
        Log::error("Funding job failed permanently", [
            'sweep_id' => $this->sweep->id,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts()
        ]);

        $this->sweep->markAsFailed($exception->getMessage());

        // Send notification
        Notification::route('slack', config('services.slack.webhook'))
            ->notify(new JobFailedNotification($this->sweep, $exception));
    }
}
```

**Step 3: Handle Specific Exceptions**
```php
class SweepTokens implements ShouldQueue
{
    public $tries = 5;

    public function handle(): void
    {
        try {
            // Job logic...
        } catch (InsufficientFundsException $e) {
            // Don't retry for insufficient funds
            $this->fail($e);
        } catch (InvalidTokenException $e) {
            // Don't retry for invalid tokens
            $this->fail($e);
        } catch (\Exception $e) {
            // Retry other exceptions
            throw $e;
        }
    }
}
```

**Step 4: Manual Retry Management**
```bash
# View failed jobs
php artisan queue:failed

# Output:
# +------+-------------+------------+--------------------+---------------------+
# | ID   | Connection  | Queue      | Class              | Failed At           |
# +------+-------------+------------+--------------------+---------------------+
# | 1    | redis       | default    | FundDepositAddress | 2024-11-09 10:30:00 |
# +------+-------------+------------+--------------------+---------------------+

# Retry specific job
php artisan queue:retry 1

# Retry all failed jobs
php artisan queue:retry all

# Delete specific failed job
php artisan queue:forget 1
```

**Step 5: Create Retry Command**
```php
// app/Console/Commands/RetryFailedSweeps.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Multicoin\TokenSweeper\Models\PendingSweep;
use Multicoin\TokenSweeper\Jobs\FundDepositAddress;

class RetryFailedSweeps extends Command
{
    protected $signature = 'sweeper:retry-failed {--age=1 : Age in hours}';
    protected $description = 'Retry failed sweeps';

    public function handle(): void
    {
        $age = $this->option('age');

        $failedSweeps = PendingSweep::where('status', 'failed')
            ->where('retry_count', '<', 3)
            ->where('updated_at', '>', now()->subHours($age))
            ->get();

        $this->info("Found {$failedSweeps->count()} failed sweeps to retry");

        foreach ($failedSweeps as $sweep) {
            $sweep->update(['status' => 'pending']);
            FundDepositAddress::dispatch($sweep);

            $this->info("Retrying sweep {$sweep->id}");
        }

        $this->info("Done!");
    }
}
```

```bash
# Run retry command
php artisan sweeper:retry-failed --age=2
```

**Prevention Tips:**
- Set appropriate retry counts and backoffs
- Implement failed job notifications
- Monitor failed jobs queue
- Schedule automatic retry for transient failures
- Log detailed error information

---

## 8. Performance Issues

### Issue 8.1: Slow Sweep Processing

**Problem:**
Sweeps taking too long to complete.

**Possible Causes:**
- Sequential processing instead of parallel
- Slow RPC responses
- Excessive confirmations wait time
- Database queries not optimized
- No caching

**Solutions:**

**Step 1: Enable Parallel Job Processing**
```bash
# Run multiple queue workers
php artisan queue:work --queue=default --tries=3 &
php artisan queue:work --queue=default --tries=3 &
php artisan queue:work --queue=default --tries=3 &

# Or use Supervisor with numprocs
[program:token-sweeper-queue]
numprocs=5  # Run 5 workers in parallel
```

**Step 2: Optimize RPC Calls**
```php
// Cache gas prices
use Illuminate\Support\Facades\Cache;

public function gasPrice(): string
{
    $cacheKey = "gas_price_{$this->chainId}";

    // Cache for 30 seconds
    return Cache::remember($cacheKey, 30, function () {
        return $this->call('eth_gasPrice', []);
    });
}

// Batch balance checks
public function batchGetBalances(array $addresses): array
{
    $requests = [];
    foreach ($addresses as $i => $address) {
        $requests[$i] = [
            'method' => 'eth_getBalance',
            'params' => [$address, 'latest']
        ];
    }

    return $this->batchCall($requests);
}
```

**Step 3: Reduce Confirmation Wait Time**
```php
// config/token-sweeper.php
'monitoring' => [
    'block_confirmations' => env('SWEEPER_CONFIRMATIONS', 1), // Reduce from 3 to 1
],

// In Web3Service, optimize polling
public function waitForConfirmation(string $txHash, int $maxAttempts = 30): bool
{
    // Start with short delays, increase if needed
    $delays = [1, 1, 1, 2, 2, 3, 3, 5, 5, 10];

    for ($i = 0; $i < $maxAttempts; $i++) {
        sleep($delays[$i] ?? 10);

        $receipt = $this->getTransactionReceipt($txHash);

        if ($receipt && isset($receipt['status'])) {
            return $receipt['status'] === '0x1';
        }
    }

    throw new \Exception("Transaction confirmation timeout: $txHash");
}
```

**Step 4: Optimize Database Queries**
```php
// Eager load relationships
use Multicoin\TokenSweeper\Models\PendingSweep;

// Bad - N+1 queries
$sweeps = PendingSweep::all();
foreach ($sweeps as $sweep) {
    echo $sweep->chain->name; // Queries database each time
}

// Good - Eager loading
$sweeps = PendingSweep::with('chain')->get();
foreach ($sweeps as $sweep) {
    echo $sweep->chain->name; // No additional queries
}

// Add indexes to migrations
Schema::table('sweeper_pending_sweeps', function (Blueprint $table) {
    $table->index(['status', 'created_at']);
    $table->index(['deposit_address', 'chain_id']);
});
```

**Step 5: Use Horizon for Queue Monitoring**
```bash
composer require laravel/horizon
php artisan horizon:install
php artisan horizon
```

```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['high', 'default', 'low'],
            'balance' => 'auto',
            'processes' => 10, // Adjust based on load
            'tries' => 3,
        ],
    ],
],
```

**Prevention Tips:**
- Monitor queue metrics
- Use database indexes
- Implement caching
- Run multiple queue workers
- Optimize RPC calls

---

### Issue 8.2: High Memory Usage

**Problem:**
Queue workers or monitor consuming excessive memory.

**Possible Causes:**
- Memory leaks in jobs
- Large result sets loaded into memory
- Circular references
- Not clearing models/collections
- Long-running processes

**Solutions:**

**Step 1: Use Chunking for Large Datasets**
```php
// Instead of loading all at once
// Bad
$addresses = DepositAddress::all(); // Loads everything into memory

// Good
DepositAddress::chunk(100, function ($addresses) {
    foreach ($addresses as $address) {
        // Process
    }
}); // Processes in batches of 100
```

**Step 2: Stop Worker After Processing Jobs**
```bash
# Restart worker after 100 jobs to clear memory
php artisan queue:work --max-jobs=100

# Restart worker after 1 hour
php artisan queue:work --max-time=3600

# In Supervisor config
command=php /var/www/artisan queue:work redis --max-jobs=100 --sleep=3
```

**Step 3: Optimize Monitor Service**
```php
// In MonitorService
public function checkForDeposits(): void
{
    // Don't load all addresses at once
    DepositAddress::chunk(50, function ($addresses) {
        foreach ($addresses as $depositAddress) {
            $this->checkAddress($depositAddress);

            // Clear model to free memory
            $depositAddress = null;
        }

        // Force garbage collection periodically
        gc_collect_cycles();
    });
}
```

**Step 4: Monitor Memory Usage**
```php
// Add memory monitoring to jobs
class FundDepositAddress implements ShouldQueue
{
    public function handle(): void
    {
        $startMemory = memory_get_usage(true);

        try {
            // Job logic...

        } finally {
            $endMemory = memory_get_usage(true);
            $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024;

            Log::debug("Job memory usage", [
                'job' => 'FundDepositAddress',
                'memory_mb' => round($memoryUsed, 2),
                'peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);
        }
    }
}
```

**Step 5: Increase PHP Memory Limit**
```ini
; php.ini
memory_limit = 512M  ; Increase from 128M

; Or in .env
PHP_MEMORY_LIMIT=512M
```

```bash
# For specific command
php -d memory_limit=512M artisan queue:work
```

**Prevention Tips:**
- Use chunking for large datasets
- Restart workers periodically
- Monitor memory usage
- Profile application for memory leaks
- Use proper pagination

---

### Issue 8.3: Database Deadlocks

**Problem:**
Concurrent sweeps causing database deadlocks.

**Example Error:**
```
SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock
```

**Possible Causes:**
- Multiple jobs updating same record simultaneously
- Transactions locking resources
- Long-running transactions
- Lock wait timeout too short

**Solutions:**

**Step 1: Reduce Transaction Scope**
```php
// Keep transactions short and focused
use Illuminate\Support\Facades\DB;

// Bad - Long transaction
DB::beginTransaction();
try {
    $sweep = PendingSweep::create([...]);
    $web3Call = $web3->someSlowCall(); // Slow operation in transaction
    $sweep->update(['result' => $web3Call]);
    DB::commit();
} catch (\Exception $e) {
    DB::rollBack();
}

// Good - Minimal transaction
$web3Call = $web3->someSlowCall(); // Do slow operations outside transaction

DB::transaction(function () use ($web3Call) {
    $sweep = PendingSweep::create([...]);
    $sweep->update(['result' => $web3Call]);
});
```

**Step 2: Use Lock for Updates**
```php
use Illuminate\Support\Facades\DB;

// Lock specific sweep for update
$sweep = PendingSweep::where('id', $sweepId)
    ->lockForUpdate()
    ->first();

if ($sweep && $sweep->status === 'pending') {
    $sweep->update(['status' => 'processing']);
}
```

**Step 3: Implement Retry on Deadlock**
```php
public function handleWithDeadlockRetry(callable $callback, int $maxRetries = 3)
{
    $attempt = 0;

    while ($attempt < $maxRetries) {
        try {
            return DB::transaction(function () use ($callback) {
                return $callback();
            });
        } catch (\PDOException $e) {
            if ($e->getCode() == '40001' && $attempt < $maxRetries - 1) {
                // Deadlock detected, retry
                $attempt++;
                usleep(rand(100000, 500000)); // Random delay 100-500ms
                continue;
            }
            throw $e;
        }
    }
}

// Usage
$this->handleWithDeadlockRetry(function () use ($sweep) {
    $sweep->update(['status' => 'completed']);
});
```

**Step 4: Increase Lock Timeout**
```sql
-- MySQL
SET innodb_lock_wait_timeout = 120; -- Default is 50

-- In Laravel migration or config
DB::statement('SET innodb_lock_wait_timeout = 120');
```

**Step 5: Use Optimistic Locking**
```php
// Add version column to model
Schema::table('sweeper_pending_sweeps', function (Blueprint $table) {
    $table->integer('version')->default(0);
});

// In model
class PendingSweep extends Model
{
    public function update(array $attributes = [], array $options = [])
    {
        $currentVersion = $this->version;
        $attributes['version'] = $currentVersion + 1;

        $updated = $this->newQuery()
            ->where('id', $this->id)
            ->where('version', $currentVersion)
            ->update($attributes);

        if (!$updated) {
            throw new \Exception('Concurrent modification detected');
        }

        return parent::update($attributes, $options);
    }
}
```

**Prevention Tips:**
- Keep transactions short
- Use locks judiciously
- Implement retry logic for deadlocks
- Avoid cross-table locking
- Monitor slow queries

---

## 9. Security Concerns

### Issue 9.1: Private Keys Compromised

**Problem:**
Suspicion that private keys may be exposed or compromised.

**Immediate Actions:**

**Step 1: Immediately Stop All Operations**
```bash
# Stop monitor
sudo supervisorctl stop token-sweeper-monitor

# Stop queue workers
sudo supervisorctl stop token-sweeper-queue:*

# Or kill processes manually
pkill -f "sweeper:monitor"
pkill -f "queue:work"
```

**Step 2: Assess Exposure**
```bash
# Check for keys in logs
grep -r "0x[a-fA-F0-9]\{64\}" storage/logs/

# Check git history
git log --all --full-history --source -- '*.env*'

# Check if .env was committed
git log --all --full-history -- .env
```

**Step 3: Transfer Funds to Safe Wallets**
```bash
php artisan tinker
```

```php
// Manually sweep all funds to new safe wallets
use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Services\{Web3Service, TransactionSignerService};

// For each chain
$chain = Chain::find(1);
$web3 = new Web3Service($chain->rpc_url, $chain->chain_id);

// Check balances
$masterBalance = hexdec($web3->getBalance($chain->master_wallet_address));
echo "Master Balance: " . ($masterBalance / 1e18) . " ETH\n";

// Manually send to new safe wallet
// Use a hardware wallet or secure offline signing
```

**Step 4: Rotate Keys**
```bash
# Generate new wallets (use hardware wallet or secure offline method)
# Update .env with new encrypted keys

php artisan tinker
```

```php
use Illuminate\Support\Facades\Crypt;

// Generate new encrypted keys
$newMasterKey = '0xNewPrivateKey';
$encrypted = Crypt::encryptString($newMasterKey);
echo $encrypted;
```

Update .env:
```env
ETH_MASTER_WALLET=0xNewMasterWalletAddress
ETH_MASTER_KEY_ENCRYPTED="new_encrypted_value"
ETH_HOT_WALLET=0xNewHotWalletAddress
```

**Step 5: Rotate APP_KEY**
```bash
# Generate new application key
php artisan key:generate

# Re-encrypt all database encrypted values with new key
# This requires custom script or manual process
```

**Step 6: Update Database**
```bash
php artisan tinker
```

```php
use Multicoin\TokenSweeper\Models\Chain;
use Illuminate\Support\Facades\Crypt;

$chain = Chain::find(1);
$chain->master_wallet_address = '0xNewMasterWallet';
$chain->master_private_key_encrypted = Crypt::encryptString('0xNewPrivateKey');
$chain->hot_wallet_address = '0xNewHotWallet';
$chain->save();
```

**Prevention Measures:**

1. **Never Commit Sensitive Data**
```bash
# Add to .gitignore
echo ".env" >> .gitignore
echo ".env.*" >> .gitignore
echo "!.env.example" >> .gitignore

# Remove from git history if already committed
git filter-branch --force --index-filter \
  "git rm --cached --ignore-unmatch .env" \
  --prune-empty --tag-name-filter cat -- --all
```

2. **Use Environment Variable Encryption**
```bash
# Use Laravel's encrypted environment files (Laravel 9.38+)
php artisan env:encrypt --env=production
```

3. **Implement Key Management**
```php
// Use AWS KMS, HashiCorp Vault, or similar
// Example with AWS KMS (requires aws/aws-sdk-php)

use Aws\Kms\KmsClient;

class KmsKeyManager
{
    protected $kms;

    public function __construct()
    {
        $this->kms = new KmsClient([
            'version' => 'latest',
            'region' => env('AWS_DEFAULT_REGION'),
        ]);
    }

    public function encrypt(string $plaintext): string
    {
        $result = $this->kms->encrypt([
            'KeyId' => env('AWS_KMS_KEY_ID'),
            'Plaintext' => $plaintext,
        ]);

        return base64_encode($result['CiphertextBlob']);
    }

    public function decrypt(string $ciphertext): string
    {
        $result = $this->kms->decrypt([
            'CiphertextBlob' => base64_decode($ciphertext),
        ]);

        return $result['Plaintext'];
    }
}
```

4. **Audit Access**
```bash
# Review server access logs
tail -f /var/log/auth.log

# Check active SSH sessions
who

# Review file access
auditctl -w /var/www/.env -p war -k env-access
```

5. **Implement Multi-Signature**
```solidity
// Consider using multi-sig wallets for master/hot wallets
// Gnosis Safe, etc.
```

**Prevention Tips:**
- Use hardware wallets for master keys
- Implement multi-signature wallets
- Regular security audits
- Monitor wallet activity
- Use AWS KMS or similar for key management
- Never log private keys
- Implement key rotation schedule

---

### Issue 9.2: Unauthorized API Access

**Problem:**
Sweeper API endpoints accessed by unauthorized users.

**Example:**
```bash
# Unauthorized user creating deposit addresses
curl -X POST http://yoursite.com/api/sweeper/deposit-address \
  -d '{"user_id": 999999, "chain_id": 1}'
```

**Solutions:**

**Step 1: Implement Authentication**
```php
// Install Laravel Sanctum
composer require laravel/sanctum
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
php artisan migrate

// In routes/api.php
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('sweeper')->group(function () {
        Route::post('/deposit-address', [TokenSweeperController::class, 'createDepositAddress']);
        Route::get('/user-addresses', [TokenSweeperController::class, 'getUserAddresses']);
        // ... other routes
    });
});

// Public routes (if any)
Route::get('sweeper/health', [TokenSweeperController::class, 'health']);
```

**Step 2: Implement Rate Limiting**
```php
// In app/Providers/RouteServiceProvider.php
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

protected function configureRateLimiting()
{
    RateLimiter::for('sweeper-api', function (Request $request) {
        return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
    });

    RateLimiter::for('sweeper-create', function (Request $request) {
        return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
    });
}

// In routes/api.php
Route::middleware(['auth:sanctum', 'throttle:sweeper-api'])->group(function () {
    Route::get('sweeper/user-addresses', [TokenSweeperController::class, 'getUserAddresses']);
});

Route::middleware(['auth:sanctum', 'throttle:sweeper-create'])->group(function () {
    Route::post('sweeper/deposit-address', [TokenSweeperController::class, 'createDepositAddress']);
});
```

**Step 3: Validate User Ownership**
```php
// In TokenSweeperController
public function createDepositAddress(Request $request)
{
    $validated = $request->validate([
        'user_id' => 'required|integer',
        'chain_id' => 'required|integer|exists:sweeper_chains,chain_id',
    ]);

    // Ensure user can only create addresses for themselves
    if ($request->user()->id !== $validated['user_id'] && !$request->user()->isAdmin()) {
        abort(403, 'Unauthorized');
    }

    // Create address...
}

public function getUserAddresses(Request $request)
{
    $userId = $request->input('user_id');

    // Users can only view their own addresses (unless admin)
    if ($request->user()->id !== $userId && !$request->user()->isAdmin()) {
        abort(403, 'Unauthorized');
    }

    // Return addresses...
}
```

**Step 4: Implement IP Whitelisting**
```php
// Create middleware
php artisan make:middleware WhitelistIp

// app/Http/Middleware/WhitelistIp.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class WhitelistIp
{
    public function handle(Request $request, Closure $next)
    {
        $whitelist = explode(',', env('ALLOWED_IPS', ''));

        if (!in_array($request->ip(), $whitelist)) {
            abort(403, 'Unauthorized IP');
        }

        return $next($request);
    }
}

// Register in app/Http/Kernel.php
protected $routeMiddleware = [
    // ...
    'whitelist.ip' => \App\Http\Middleware\WhitelistIp::class,
];

// Use in routes
Route::middleware(['whitelist.ip'])->group(function () {
    Route::post('sweeper/process-sweep', [TokenSweeperController::class, 'processSweep']);
});
```

**Step 5: Add API Key Authentication**
```php
// Create middleware
php artisan make:middleware ValidateApiKey

// app/Http/Middleware/ValidateApiKey.php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ValidateApiKey
{
    public function handle(Request $request, Closure $next)
    {
        $apiKey = $request->header('X-API-Key');

        if (!$apiKey || $apiKey !== config('sweeper.api_key')) {
            return response()->json(['error' => 'Invalid API key'], 401);
        }

        return $next($request);
    }
}

// config/token-sweeper.php
return [
    'api_key' => env('SWEEPER_API_KEY'),
];

// .env
SWEEPER_API_KEY=your_secret_api_key_here

// Use in routes
Route::middleware(['api.key'])->group(function () {
    Route::post('sweeper/webhook', [TokenSweeperController::class, 'webhook']);
});
```

**Step 6: Audit Logging**
```php
// Create audit log
php artisan make:model AuditLog -m

// Migration
Schema::create('audit_logs', function (Blueprint $table) {
    $table->id();
    $table->string('user_id')->nullable();
    $table->string('action');
    $table->json('details')->nullable();
    $table->string('ip_address');
    $table->string('user_agent')->nullable();
    $table->timestamps();
});

// In controller
use App\Models\AuditLog;

public function createDepositAddress(Request $request)
{
    // Log the action
    AuditLog::create([
        'user_id' => $request->user()->id,
        'action' => 'create_deposit_address',
        'details' => $request->all(),
        'ip_address' => $request->ip(),
        'user_agent' => $request->userAgent(),
    ]);

    // Create address...
}
```

**Prevention Tips:**
- Always require authentication for sensitive endpoints
- Implement rate limiting
- Validate user permissions
- Use IP whitelisting for admin operations
- Log all API access
- Use HTTPS only
- Implement CORS properly

---

### Issue 9.3: Insecure Logging

**Problem:**
Sensitive data being logged in plain text.

**Example:**
```php
// Bad - Logs private key!
Log::info('Signing transaction', [
    'private_key' => $privateKey,
    'transaction' => $transaction
]);
```

**Solutions:**

**Step 1: Review and Clean Logs**
```bash
# Find potential leaks in logs
grep -r "private.*key" storage/logs/
grep -r "0x[a-fA-F0-9]\{64\}" storage/logs/

# Clear sensitive logs
> storage/logs/laravel.log

# Rotate logs
php artisan log:clear
```

**Step 2: Implement Log Sanitization**
```php
// app/Helpers/LogHelper.php
namespace App\Helpers;

class LogHelper
{
    public static function sanitize(array $data): array
    {
        $sensitiveKeys = [
            'private_key',
            'privateKey',
            'master_private_key_encrypted',
            'password',
            'api_key',
            'secret',
        ];

        foreach ($data as $key => $value) {
            // Redact sensitive keys
            if (in_array($key, $sensitiveKeys)) {
                $data[$key] = '[REDACTED]';
            }

            // Recursively sanitize arrays
            if (is_array($value)) {
                $data[$key] = self::sanitize($value);
            }

            // Mask addresses (show only first 6 and last 4 characters)
            if (is_string($value) && preg_match('/^0x[a-fA-F0-9]{40}$/', $value)) {
                $data[$key] = substr($value, 0, 6) . '...' . substr($value, -4);
            }
        }

        return $data;
    }
}

// Usage
use App\Helpers\LogHelper;

Log::info('Processing sweep', LogHelper::sanitize([
    'deposit_address' => $depositAddress,
    'private_key' => $privateKey, // Will be redacted
    'amount' => $amount,
]));
```

**Step 3: Create Custom Log Channel**
```php
// config/logging.php
'channels' => [
    'sweeper' => [
        'driver' => 'daily',
        'path' => storage_path('logs/sweeper.log'),
        'level' => env('LOG_LEVEL', 'debug'),
        'days' => 14,
        'tap' => [App\Logging\SanitizerFormatter::class],
    ],
],

// app/Logging/SanitizerFormatter.php
namespace App\Logging;

use Monolog\Formatter\LineFormatter;

class SanitizerFormatter
{
    public function __invoke($logger)
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new class extends LineFormatter {
                public function format(array $record): string
                {
                    // Sanitize context
                    if (isset($record['context'])) {
                        $record['context'] = $this->sanitizeContext($record['context']);
                    }

                    return parent::format($record);
                }

                protected function sanitizeContext(array $context): array
                {
                    $sensitive = ['private_key', 'privateKey', 'password', 'secret', 'api_key'];

                    foreach ($context as $key => $value) {
                        if (in_array($key, $sensitive)) {
                            $context[$key] = '[REDACTED]';
                        } elseif (is_array($value)) {
                            $context[$key] = $this->sanitizeContext($value);
                        }
                    }

                    return $context;
                }
            });
        }
    }
}
```

**Step 4: Never Log Private Keys**
```php
// Good logging practices

// In SweeperService
public function processSweep(string $depositAddress, string $tokenAddress, int $chainId): bool
{
    Log::info("Starting sweep", [
        'deposit_address' => substr($depositAddress, 0, 10) . '...',
        'token_address' => substr($tokenAddress, 0, 10) . '...',
        'chain_id' => $chainId,
        // Never log: private keys, full addresses (optional)
    ]);
}

// In Web3Service
public function sendRawTransaction(string $signedTx): string
{
    Log::debug("Sending transaction", [
        'tx_length' => strlen($signedTx),
        'tx_prefix' => substr($signedTx, 0, 10) . '...',
        // Never log: full signed transaction (contains signature)
    ]);
}
```

**Step 5: Secure Log Files**
```bash
# Set proper permissions on log directory
chmod 750 storage/logs
chmod 640 storage/logs/*.log

# Ensure logs are not accessible via web
# Add to .htaccess or nginx config
location ~* ^/storage/logs/ {
    deny all;
}

# Rotate and compress logs
# Install logrotate
sudo apt-get install logrotate

# Create logrotate config
# /etc/logrotate.d/laravel
/var/www/storage/logs/*.log {
    daily
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

**Prevention Tips:**
- Never log private keys or sensitive credentials
- Implement automatic log sanitization
- Use separate log channels for different purposes
- Rotate and compress logs regularly
- Restrict log file permissions
- Review logs before sharing with support

---

## 10. Debugging Tips

### 10.1: Enable Debug Mode

```php
// .env
APP_DEBUG=true
LOG_LEVEL=debug

// config/token-sweeper.php
'debug' => env('SWEEPER_DEBUG', false),
```

Enable verbose logging in services:

```php
// In Web3Service
use Illuminate\Support\Facades\Log;

public function call(string $method, array $params = []): mixed
{
    if (config('token-sweeper.debug')) {
        Log::debug("RPC Call", [
            'method' => $method,
            'params' => $params,
            'rpc_url' => substr($this->rpcUrl, 0, 30) . '...',
        ]);
    }

    $result = // ... make call

    if (config('token-sweeper.debug')) {
        Log::debug("RPC Response", [
            'method' => $method,
            'result' => $result,
        ]);
    }

    return $result;
}
```

---

### 10.2: Test RPC Connectivity

```bash
# Test RPC endpoint
php artisan tinker
```

```php
use Multicoin\TokenSweeper\Services\Web3Service;

$web3 = new Web3Service('https://eth.nownodes.io/YOUR_KEY', 1);

// Test basic call
$blockNumber = $web3->getBlockNumber();
echo "Current block: " . hexdec($blockNumber) . "\n";

// Test gas price
$gasPrice = $web3->gasPrice();
echo "Gas price: " . hexdec($gasPrice) / 1e9 . " Gwei\n";

// Test balance check
$balance = $web3->getBalance('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb');
echo "Balance: " . hexdec($balance) / 1e18 . " ETH\n";
```

---

### 10.3: Debug Transaction Signing

```bash
php artisan tinker
```

```php
use Multicoin\TokenSweeper\Services\TransactionSignerService;

$signer = new TransactionSignerService();

$transaction = [
    'nonce' => 0,
    'gasPrice' => 20000000000, // 20 Gwei
    'gasLimit' => 21000,
    'to' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
    'value' => '1000000000000000000', // 1 ETH
    'data' => ''
];

$privateKey = '0xYourPrivateKeyForTesting'; // Use test key only!
$chainId = 1;

try {
    $signedTx = $signer->signTransaction($transaction, $privateKey, $chainId);
    echo "Signed TX: " . $signedTx . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

---

### 10.4: Monitor Queue in Real-Time

```bash
# Watch queue depth
watch -n 1 'redis-cli llen queues:default'

# Monitor queue processing
tail -f storage/logs/laravel.log | grep -i "queue"

# Use Tinkerwell or tinker
php artisan tinker
```

```php
// Check queue depth
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;

echo "Queue depth: " . Redis::llen('queues:default') . "\n";

// Check failed jobs
use Illuminate\Support\Facades\DB;
$failedCount = DB::table('failed_jobs')->count();
echo "Failed jobs: {$failedCount}\n";
```

---

### 10.5: Trace Sweep Lifecycle

```bash
php artisan tinker
```

```php
use Multicoin\TokenSweeper\Models\{PendingSweep, SweepLog};

$sweep = PendingSweep::latest()->first();

echo "Sweep ID: {$sweep->id}\n";
echo "Status: {$sweep->status}\n";
echo "Created: {$sweep->created_at}\n";
echo "Updated: {$sweep->updated_at}\n";
echo "Deposit Address: {$sweep->deposit_address}\n";
echo "Token: {$sweep->token_symbol}\n";
echo "Funding TX: {$sweep->funding_tx_hash}\n";
echo "Sweep TX: {$sweep->sweep_tx_hash}\n";
echo "Error: {$sweep->error_message}\n";
echo "Retries: {$sweep->retry_count}\n";

// Check log
$log = $sweep->log;
if ($log) {
    echo "\nLog Entry:\n";
    echo "Status: {$log->status}\n";
    echo "Funding TX: {$log->funding_tx_hash}\n";
    echo "Sweep TX: {$log->sweep_tx_hash}\n";
}
```

---

### 10.6: Useful Artisan Commands for Debugging

```bash
# Clear all caches
php artisan optimize:clear

# View configuration
php artisan config:show token-sweeper

# Check database connection
php artisan db:show

# List all routes
php artisan route:list --path=sweeper

# Run queue worker in verbose mode
php artisan queue:work --verbose --tries=1

# Monitor queue
php artisan queue:monitor redis:default --max=100

# Check event listeners
php artisan event:list
```

---

## 11. FAQ

### Q1: How do I test the package without real funds?

**Answer:**

Use testnets for testing:

```env
# .env for testnet (Goerli)
ETH_RPC_URL=https://eth-goerli.nownodes.io/${NOWNODES_API_KEY}
ETH_MASTER_WALLET=0xYourTestWallet
ETH_MASTER_KEY_ENCRYPTED="encrypted_test_key"
ETH_HOT_WALLET=0xYourTestHotWallet
```

```php
// Update chain configuration for testnet
use Multicoin\TokenSweeper\Models\Chain;

Chain::updateOrCreate(
    ['chain_id' => 5], // Goerli
    [
        'name' => 'Goerli Testnet',
        'rpc_url' => env('ETH_RPC_URL'),
        'master_wallet_address' => env('ETH_MASTER_WALLET'),
        'master_private_key_encrypted' => env('ETH_MASTER_KEY_ENCRYPTED'),
        'hot_wallet_address' => env('ETH_HOT_WALLET'),
        'native_symbol' => 'GoerliETH',
        'gas_amount_wei' => '2000000000000000',
        'gas_limit_token_transfer' => 100000,
    ]
);
```

Get testnet tokens:
- Goerli ETH: https://goerlifaucet.com/
- Sepolia ETH: https://sepoliafaucet.com/
- BSC Testnet: https://testnet.binance.org/faucet-smart

---

### Q2: Can I use my own RPC node instead of NOWNodes?

**Answer:**

Yes! Simply configure your RPC URL:

```env
# .env
ETH_RPC_URL=https://your-node.example.com:8545
BSC_RPC_URL=https://your-bsc-node.example.com:8545
```

Or update in database:

```php
use Multicoin\TokenSweeper\Models\Chain;

$chain = Chain::where('chain_id', 1)->first();
$chain->rpc_url = 'https://your-node.example.com:8545';
$chain->save();
```

---

### Q3: How do I add support for a new blockchain?

**Answer:**

Follow these steps:

**Step 1: Add Chain Configuration**
```php
use Multicoin\TokenSweeper\Models\Chain;
use Illuminate\Support\Facades\Crypt;

Chain::create([
    'chain_id' => 250, // Fantom
    'name' => 'Fantom',
    'rpc_url' => 'https://rpc.ftm.tools/',
    'master_wallet_address' => '0xYourMasterWallet',
    'master_private_key_encrypted' => Crypt::encryptString('0xPrivateKey'),
    'hot_wallet_address' => '0xYourHotWallet',
    'native_symbol' => 'FTM',
    'gas_amount_wei' => '1000000000000000000', // 1 FTM
    'gas_limit_token_transfer' => 100000,
]);
```

**Step 2: Add Tokens for New Chain**
```php
use Multicoin\TokenSweeper\Models\Token;

Token::create([
    'chain_id' => 250,
    'symbol' => 'USDC',
    'name' => 'USD Coin',
    'contract_address' => '0x04068DA6C83AFCFA0e13ba15A6696662335D5B75',
    'decimals' => 6,
]);
```

**Step 3: Update Configuration (Optional)**
```php
// config/token-sweeper.php
'default_chains' => [
    [
        'chain_id' => 250,
        'name' => 'Fantom',
        // ... rest of config
    ],
],
```

---

### Q4: What happens if a sweep partially fails?

**Answer:**

The package tracks sweep state:

- **Funding successful, sweep failed**: The deposit address will have gas but tokens won't be swept. The sweep record will be marked as `failed` with error message. You can:
  - Retry manually: `php artisan sweeper:sweep <address> <token> <chain_id>`
  - Wait for automatic retry (if configured)
  - Manually sweep remaining tokens

- **Both failed**: Record marked as `failed`, can be retried.

Check sweep status:
```bash
php artisan tinker
```

```php
use Multicoin\TokenSweeper\Models\PendingSweep;

$failed = PendingSweep::failed()->get();
foreach ($failed as $sweep) {
    echo "Sweep {$sweep->id}: {$sweep->deposit_address} - {$sweep->error_message}\n";
}
```

---

### Q5: How do I backup and restore the database?

**Answer:**

**Backup:**
```bash
# MySQL
mysqldump -u username -p database_name > backup.sql

# PostgreSQL
pg_dump -U username database_name > backup.sql

# Laravel command (if using backup package)
php artisan backup:run
```

**Restore:**
```bash
# MySQL
mysql -u username -p database_name < backup.sql

# PostgreSQL
psql -U username database_name < backup.sql
```

**Important Tables to Backup:**
- `sweeper_chains` - Chain configurations
- `sweeper_tokens` - Token configurations
- `sweeper_deposit_addresses` - User deposit addresses
- `sweeper_pending_sweeps` - Active and pending sweeps
- `sweeper_logs` - Historical sweep records

---

### Q6: Can I customize the gas amount for specific tokens?

**Answer:**

Currently, gas amount is set per chain. However, you can implement custom logic:

```php
// Extend SweeperService or create custom service
class CustomSweeperService extends SweeperService
{
    protected function getGasAmountForToken(string $tokenAddress, int $chainId): string
    {
        // Custom gas amounts for specific tokens
        $customGasAmounts = [
            '1' => [ // Ethereum
                '0xTokenAddress1' => '3000000000000000', // 0.003 ETH for complex token
                '0xTokenAddress2' => '1500000000000000', // 0.0015 ETH for simple token
            ],
        ];

        return $customGasAmounts[$chainId][$tokenAddress]
            ?? Chain::find($chainId)->gas_amount_wei;
    }
}
```

---

### Q7: How do I monitor master wallet balances?

**Answer:**

**Manual Check:**
```bash
php artisan sweeper:balance 0xMasterWalletAddress "" 1
```

**Automated Monitoring:**
```php
// app/Console/Commands/MonitorMasterWallets.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Services\Web3Service;

class MonitorMasterWallets extends Command
{
    protected $signature = 'sweeper:monitor-masters';
    protected $description = 'Monitor master wallet balances';

    public function handle(): void
    {
        $chains = Chain::active()->get();

        foreach ($chains as $chain) {
            $web3 = new Web3Service($chain->rpc_url, $chain->chain_id);
            $balance = hexdec($web3->getBalance($chain->master_wallet_address));
            $balanceFloat = $balance / 1e18;

            $this->info("{$chain->name}: {$balanceFloat} {$chain->native_symbol}");

            // Alert if low
            if ($balanceFloat < 0.05) {
                $this->error("LOW BALANCE ALERT: {$chain->name}");
            }
        }
    }
}
```

Schedule it:
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->command('sweeper:monitor-masters')->hourly();
}
```

---

### Q8: What if I need to pause all sweeps?

**Answer:**

**Option 1: Stop Services**
```bash
# Stop monitor
sudo supervisorctl stop token-sweeper-monitor

# Stop queue workers
sudo supervisorctl stop token-sweeper-queue:*
```

**Option 2: Disable Chains**
```php
use Multicoin\TokenSweeper\Models\Chain;

// Disable all chains
Chain::query()->update(['is_active' => false]);

// Re-enable when ready
Chain::query()->update(['is_active' => true]);
```

**Option 3: Emergency Stop Flag**
Add to config:
```php
// config/token-sweeper.php
'emergency_stop' => env('SWEEPER_EMERGENCY_STOP', false),
```

Check in monitor:
```php
// In MonitorService
public function monitor(): void
{
    if (config('token-sweeper.emergency_stop')) {
        Log::warning('Emergency stop enabled, skipping monitoring');
        return;
    }

    // Normal monitoring...
}
```

---

### Q9: How do I handle network congestion and high gas prices?

**Answer:**

**Option 1: Set Maximum Gas Price**
```php
// In SweeperService or Web3Service
public function getGasPrice(): int
{
    $gasPrice = hexdec($this->web3->gasPrice());
    $maxGasPrice = env('SWEEPER_MAX_GAS_PRICE', 100) * 1e9; // 100 Gwei default

    if ($gasPrice > $maxGasPrice) {
        throw new \Exception("Gas price too high: " . ($gasPrice / 1e9) . " Gwei");
    }

    return $gasPrice;
}
```

**Option 2: Queue Sweeps During High Gas**
```php
// Defer sweeps when gas is high
if ($currentGasPrice > $threshold) {
    Log::info("Gas price too high, deferring sweep", [
        'current_gwei' => $currentGasPrice / 1e9,
        'threshold_gwei' => $threshold / 1e9,
    ]);

    // Re-queue for later
    FundDepositAddress::dispatch($sweep)->delay(now()->addMinutes(30));
    return;
}
```

**Option 3: Use EIP-1559 (if supported)**
```php
// For chains supporting EIP-1559
$transaction = [
    'nonce' => $nonce,
    'maxFeePerGas' => $maxFeePerGas,
    'maxPriorityFeePerGas' => $maxPriorityFeePerGas,
    'gasLimit' => $gasLimit,
    'to' => $to,
    'value' => $value,
    'data' => $data,
    'chainId' => $chainId,
    'type' => 2, // EIP-1559 transaction type
];
```

---

### Q10: Can I run multiple instances of the monitor?

**Answer:**

**Not recommended** - Multiple monitors will cause duplicate sweeps.

If you need high availability:

**Option 1: Use Leader Election**
```php
use Illuminate\Support\Facades\Cache;

public function monitor(): void
{
    $lock = Cache::lock('sweeper_monitor_leader', 300);

    if (!$lock->get()) {
        Log::info('Another monitor instance is running');
        return;
    }

    try {
        // Run monitoring...
    } finally {
        $lock->release();
    }
}
```

**Option 2: Use Supervisor with `numprocs=1`**
```ini
[program:token-sweeper-monitor]
command=php /var/www/artisan sweeper:monitor
numprocs=1  ; Only one instance
```

---

## Additional Resources

### Official Documentation
- Laravel Documentation: https://laravel.com/docs
- Web3 JSON-RPC: https://ethereum.org/en/developers/docs/apis/json-rpc/

### Blockchain Explorers
- Ethereum: https://etherscan.io/
- BSC: https://bscscan.com/
- Polygon: https://polygonscan.com/

### RPC Providers
- NOWNodes: https://nownodes.io/
- Alchemy: https://www.alchemy.com/
- Infura: https://infura.io/
- QuickNode: https://www.quicknode.com/

### Support
- GitHub Issues: [Your Repository URL]
- Documentation: [Your Docs URL]
- Email: support@example.com

---

**Remember**: Always test changes in development/staging before applying to production!

For issues not covered in this guide, please:
1. Check application logs: `storage/logs/laravel.log`
2. Enable debug mode for detailed error messages
3. Review blockchain transaction on explorer
4. Contact support with detailed error messages and logs
