# Laravel Token Sweeper - Usage Guide

> Comprehensive guide for using the Laravel Token Sweeper package in production environments.

## Table of Contents

1. [Installation](#installation)
2. [Configuration](#configuration)
3. [Basic Usage](#basic-usage)
4. [Advanced Usage](#advanced-usage)
5. [Commands Reference](#commands-reference)
6. [Events and Listeners](#events-and-listeners)
7. [Queue Configuration](#queue-configuration)
8. [Monitoring and Logging](#monitoring-and-logging)
9. [Production Best Practices](#production-best-practices)
10. [Examples and Code Snippets](#examples-and-code-snippets)

---

## Installation

### Prerequisites

Before installing the Token Sweeper package, ensure your system meets these requirements:

- **PHP**: 8.2 or higher
- **Laravel**: 10.x or 11.x
- **Database**: PostgreSQL or MySQL
- **Queue Driver**: Redis (recommended) or Database
- **PHP Extensions**:
  - GMP (GNU Multiple Precision)
  - OpenSSL
  - BCMath

Check PHP extensions:

```bash
php -m | grep -E 'gmp|openssl|bcmath'
```

### Step 1: Install via Composer

```bash
composer require multicoin/token-sweeper
```

The package uses Laravel's auto-discovery feature and will automatically register the service provider and facade.

### Step 2: Run Installation Command

```bash
php artisan sweeper:install
```

This command will:
- Publish the configuration file to `config/token-sweeper.php`
- Run database migrations
- Create necessary tables:
  - `sweeper_chains` - Blockchain network configurations
  - `sweeper_tokens` - Token contracts and metadata
  - `sweeper_deposit_addresses` - User deposit addresses
  - `sweeper_pending_sweeps` - Sweep queue and status
  - `sweeper_sweep_logs` - Historical sweep records

### Step 3: Verify Installation

Check that the migrations were successful:

```bash
php artisan migrate:status
```

Verify the config file was published:

```bash
ls -l config/token-sweeper.php
```

---

## Configuration

### Environment Variables

Add the following to your `.env` file:

#### RPC Provider Configuration

Using NOWNodes (recommended for production):

```env
# NOWNodes API Key
NOWNODES_API_KEY=your_nownodes_api_key_here

# RPC URLs (automatically constructed with API key)
ETH_RPC_URL=https://eth.nownodes.io/${NOWNODES_API_KEY}
BSC_RPC_URL=https://bsc.nownodes.io/${NOWNODES_API_KEY}
POLYGON_RPC_URL=https://matic.nownodes.io/${NOWNODES_API_KEY}
ARB_RPC_URL=https://arb.nownodes.io/${NOWNODES_API_KEY}
OP_RPC_URL=https://op.nownodes.io/${NOWNODES_API_KEY}
```

Alternatively, use custom RPC providers:

```env
ETH_RPC_URL=https://mainnet.infura.io/v3/YOUR_PROJECT_ID
BSC_RPC_URL=https://bsc-dataseed1.binance.org
POLYGON_RPC_URL=https://polygon-rpc.com
```

#### Ethereum Configuration

```env
ETH_MASTER_WALLET=0x1234567890123456789012345678901234567890
ETH_MASTER_KEY_ENCRYPTED=encrypted_private_key_here
ETH_HOT_WALLET=0x0987654321098765432109876543210987654321
```

#### BSC Configuration

```env
BSC_MASTER_WALLET=0x1234567890123456789012345678901234567890
BSC_MASTER_KEY_ENCRYPTED=encrypted_private_key_here
BSC_HOT_WALLET=0x0987654321098765432109876543210987654321
```

#### Polygon Configuration

```env
POLYGON_MASTER_WALLET=0x1234567890123456789012345678901234567890
POLYGON_MASTER_KEY_ENCRYPTED=encrypted_private_key_here
POLYGON_HOT_WALLET=0x0987654321098765432109876543210987654321
```

#### Sweeper Behavior Configuration

```env
# Check interval for monitoring deposits (seconds)
SWEEPER_CHECK_INTERVAL=5

# Number of block confirmations required
SWEEPER_CONFIRMATIONS=1

# Maximum retry attempts for failed sweeps
SWEEPER_MAX_RETRIES=3

# Delay between retry attempts (seconds)
SWEEPER_RETRY_DELAY=60
```

### Encrypting Master Private Keys

**IMPORTANT**: Never store private keys in plain text. Always encrypt them using Laravel's encryption:

```bash
php artisan tinker
```

```php
use Illuminate\Support\Facades\Crypt;

// Encrypt Ethereum master key
$ethKey = Crypt::encryptString('0xYourEthereumPrivateKeyHere');
echo "ETH_MASTER_KEY_ENCRYPTED=" . $ethKey . "\n";

// Encrypt BSC master key
$bscKey = Crypt::encryptString('0xYourBscPrivateKeyHere');
echo "BSC_MASTER_KEY_ENCRYPTED=" . $bscKey . "\n";

// Encrypt Polygon master key
$polygonKey = Crypt::encryptString('0xYourPolygonPrivateKeyHere');
echo "POLYGON_MASTER_KEY_ENCRYPTED=" . $polygonKey . "\n";
```

Copy the output to your `.env` file.

### Configuration File

The configuration file at `config/token-sweeper.php` contains:

```php
return [
    // Monitoring settings
    'monitoring' => [
        'check_interval' => env('SWEEPER_CHECK_INTERVAL', 5),
        'block_confirmations' => env('SWEEPER_CONFIRMATIONS', 1),
        'max_retry_attempts' => env('SWEEPER_MAX_RETRIES', 3),
        'retry_delay' => env('SWEEPER_RETRY_DELAY', 60),
    ],

    // RPC URLs by chain ID
    'rpc_urls' => [
        1 => env('ETH_RPC_URL'),
        56 => env('BSC_RPC_URL'),
        137 => env('POLYGON_RPC_URL'),
        42161 => env('ARB_RPC_URL'),
        10 => env('OP_RPC_URL'),
    ],

    // Default chains for seeding
    'default_chains' => [
        // ... chain configurations
    ],

    // Default tokens for seeding
    'default_tokens' => [
        // ... token configurations
    ],
];
```

### Seeding Chains and Tokens

After configuring your environment, seed the database with default chains and tokens:

```bash
php artisan sweeper:seed
```

This will create:
- **Chains**: Ethereum, BSC, Polygon
- **Tokens**: USDT, USDC, DAI (on each chain)

Verify seeding:

```bash
php artisan tinker
```

```php
use Multicoin\TokenSweeper\Models\Chain;
use Multicoin\TokenSweeper\Models\Token;

Chain::all();
Token::with('chain')->get();
```

---

## Basic Usage

### 1. Generating Deposit Addresses

#### Using Artisan Command

Generate a deposit address for a specific user and chain:

```bash
php artisan sweeper:generate-address 123 1
```

- `123`: User ID
- `1`: Chain ID (1 = Ethereum)

Output:
```
Generating deposit address for user 123 on chain 1...
✅ Deposit address created: 0x1234567890123456789012345678901234567890
```

#### Using the Facade

```php
use Multicoin\TokenSweeper\Facades\TokenSweeper;

// Generate deposit address for user on Ethereum
$address = TokenSweeper::createDepositAddress(
    userId: 123,
    chainId: 1
);

echo "Deposit address: {$address}";
```

#### Using the Service Directly

```php
use Multicoin\TokenSweeper\Services\WalletService;

$walletService = app(WalletService::class);

$address = $walletService->createDepositAddress(
    userId: 123,
    chainId: 1
);
```

#### Multi-Chain Deposit Addresses

Generate addresses for a user on multiple chains:

```php
use Multicoin\TokenSweeper\Services\WalletService;
use Multicoin\TokenSweeper\Models\Chain;

$walletService = app(WalletService::class);
$userId = 123;

$addresses = [];

foreach (Chain::all() as $chain) {
    $addresses[$chain->name] = $walletService->createDepositAddress(
        $userId,
        $chain->chain_id
    );
}

// Result:
// [
//     'Ethereum' => '0x...',
//     'BSC' => '0x...',
//     'Polygon' => '0x...',
// ]
```

### 2. Monitoring Deposits

#### Start the Monitor

The monitor continuously checks for token deposits on all configured chains:

```bash
php artisan sweeper:monitor
```

This command:
- Polls blockchain for token deposits
- Detects when tokens arrive at deposit addresses
- Automatically triggers the sweep process
- Fires `DepositDetected` events

**For Production**: Run this as a background service using Supervisor (see Production section).

#### How Monitoring Works

1. Monitor checks all deposit addresses periodically
2. When tokens are detected, a `DepositDetected` event is fired
3. A `PendingSweep` record is created
4. The sweep process is queued:
   - Step 1: Fund deposit address with gas (via `FundDepositAddress` job)
   - Step 2: Sweep tokens to hot wallet (via `SweepTokens` job)

### 3. Manual Sweeps

Manually trigger a token sweep:

```bash
php artisan sweeper:sweep 0x1234... 0xdAC17F958D2ee523a2206206994597C13D831ec7 1
```

Arguments:
- `0x1234...`: Deposit address
- `0xdAC17F958D2ee523a2206206994597C13D831ec7`: Token contract address (USDT on Ethereum)
- `1`: Chain ID (Ethereum)

#### Programmatic Manual Sweep

```php
use Multicoin\TokenSweeper\Services\SweeperService;

$sweeper = app(SweeperService::class);

$result = $sweeper->processSweep(
    address: '0x1234567890123456789012345678901234567890',
    tokenAddress: '0xdAC17F958D2ee523a2206206994597C13D831ec7',
    chainId: 1
);

if ($result) {
    echo "Sweep successful!";
} else {
    echo "Sweep failed!";
}
```

### 4. Checking Balances

Check native and token balances:

```bash
# Check native balance (ETH)
php artisan sweeper:balance 0x1234... "" 1

# Check token balance (USDT)
php artisan sweeper:balance 0x1234... 0xdAC17F958D2ee523a2206206994597C13D831ec7 1
```

Output:
```
Chain: Ethereum
Address: 0x1234567890123456789012345678901234567890
Native Balance: 0.05 ETH
Token Balance: 1000000000 (raw)
```

### 5. Viewing Pending Sweeps

Check sweeps by status:

```bash
# View pending sweeps
php artisan sweeper:pending --status=pending

# View failed sweeps
php artisan sweeper:pending --status=failed

# View completed sweeps
php artisan sweeper:pending --status=completed
```

Output:
```
Pending Sweeps (Status: pending):
+----+-----------+---------------+--------+---------+----------------+
| ID | Chain     | Address       | Token  | Status  | Created        |
+----+-----------+---------------+--------+---------+----------------+
| 1  | Ethereum  | 0x1234...     | USDT   | pending | 2 minutes ago  |
| 2  | BSC       | 0x5678...     | USDC   | pending | 5 minutes ago  |
+----+-----------+---------------+--------+---------+----------------+
```

---

## Advanced Usage

### Custom Chains

#### Add a New Chain via Configuration

Edit `config/token-sweeper.php`:

```php
'default_chains' => [
    // Existing chains...

    // Add Arbitrum
    [
        'chain_id' => 42161,
        'name' => 'Arbitrum One',
        'rpc_url' => env('ARB_RPC_URL'),
        'master_wallet_address' => env('ARB_MASTER_WALLET'),
        'master_private_key_encrypted' => env('ARB_MASTER_KEY_ENCRYPTED'),
        'hot_wallet_address' => env('ARB_HOT_WALLET'),
        'native_symbol' => 'ETH',
        'gas_amount_wei' => '1000000000000000', // 0.001 ETH
        'gas_limit_token_transfer' => 200000,
    ],

    // Add Optimism
    [
        'chain_id' => 10,
        'name' => 'Optimism',
        'rpc_url' => env('OP_RPC_URL'),
        'master_wallet_address' => env('OP_MASTER_WALLET'),
        'master_private_key_encrypted' => env('OP_MASTER_KEY_ENCRYPTED'),
        'hot_wallet_address' => env('OP_HOT_WALLET'),
        'native_symbol' => 'ETH',
        'gas_amount_wei' => '1000000000000000', // 0.001 ETH
        'gas_limit_token_transfer' => 100000,
    ],
],
```

Then re-seed:

```bash
php artisan sweeper:seed
```

#### Add a Chain Programmatically

```php
use Multicoin\TokenSweeper\Models\Chain;
use Illuminate\Support\Facades\Crypt;

Chain::create([
    'chain_id' => 250,
    'name' => 'Fantom Opera',
    'rpc_url' => 'https://rpc.ftm.tools',
    'master_wallet_address' => '0xYourMasterWallet',
    'master_private_key_encrypted' => Crypt::encryptString('0xPrivateKey'),
    'hot_wallet_address' => '0xYourHotWallet',
    'native_symbol' => 'FTM',
    'gas_amount_wei' => '5000000000000000000', // 5 FTM
    'gas_limit_token_transfer' => 100000,
]);
```

### Multiple Tokens

#### Add Tokens via Configuration

```php
'default_tokens' => [
    // Add custom token
    [
        'chain_id' => 1,
        'symbol' => 'LINK',
        'name' => 'ChainLink Token',
        'contract_address' => '0x514910771AF9Ca656af840dff83E8264EcF986CA',
        'decimals' => 18,
    ],
    [
        'chain_id' => 56,
        'symbol' => 'CAKE',
        'name' => 'PancakeSwap Token',
        'contract_address' => '0x0E09FaBB73Bd3Ade0a17ECC321fD13a19e81cE82',
        'decimals' => 18,
    ],
],
```

#### Add Tokens Programmatically

```php
use Multicoin\TokenSweeper\Models\Token;

Token::create([
    'chain_id' => 137,
    'symbol' => 'WMATIC',
    'name' => 'Wrapped Matic',
    'contract_address' => '0x0d500B1d8E8eF31E21C99d1Db9A6444d3ADf1270',
    'decimals' => 18,
]);
```

#### Query Tokens

```php
use Multicoin\TokenSweeper\Models\Token;

// Get all active tokens
$tokens = Token::active()->get();

// Get tokens for specific chain
$ethTokens = Token::active()->forChain(1)->get();

// Get specific token
$usdt = Token::where('chain_id', 1)
    ->where('symbol', 'USDT')
    ->first();
```

### Webhooks Integration

Create a listener to send webhooks when deposits are detected:

#### Create Webhook Listener

```php
// app/Listeners/SendDepositWebhook.php

namespace App\Listeners;

use Multicoin\TokenSweeper\Events\DepositDetected;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendDepositWebhook
{
    public function handle(DepositDetected $event): void
    {
        $webhookUrl = config('services.webhook.deposit_url');

        $payload = [
            'event' => 'deposit_detected',
            'deposit_address' => $event->depositAddress,
            'token_address' => $event->tokenAddress,
            'chain_id' => $event->chainId,
            'amount' => $event->amount,
            'timestamp' => now()->toIso8601String(),
        ];

        try {
            $response = Http::timeout(10)
                ->post($webhookUrl, $payload);

            if ($response->successful()) {
                Log::info('Deposit webhook sent', ['payload' => $payload]);
            } else {
                Log::error('Webhook failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Webhook exception', [
                'error' => $e->getMessage(),
                'payload' => $payload
            ]);
        }
    }
}
```

#### Register Listener

```php
// app/Providers/EventServiceProvider.php

use Multicoin\TokenSweeper\Events\DepositDetected;
use App\Listeners\SendDepositWebhook;

protected $listen = [
    DepositDetected::class => [
        SendDepositWebhook::class,
    ],
];
```

### Database Queries and Scopes

#### Query Deposit Addresses

```php
use Multicoin\TokenSweeper\Models\DepositAddress;

// Get all addresses for a user
$addresses = DepositAddress::forUser(123)->get();

// Get addresses for a specific chain
$ethAddresses = DepositAddress::forChain(1)->get();

// Get address with chain relationship
$address = DepositAddress::with('chain')
    ->where('address', '0x...')
    ->first();

// Get addresses with pending sweeps
$activeAddresses = DepositAddress::has('pendingSweeps')->get();
```

#### Query Pending Sweeps

```php
use Multicoin\TokenSweeper\Models\PendingSweep;

// Get all pending sweeps
$pending = PendingSweep::pending()->get();

// Get failed sweeps
$failed = PendingSweep::failed()->get();

// Get completed sweeps
$completed = PendingSweep::completed()->get();

// Get sweeps for specific chain
$ethSweeps = PendingSweep::forChain(1)->get();

// Get sweeps with relationships
$sweeps = PendingSweep::with(['chain'])
    ->where('status', 'pending')
    ->orderBy('created_at', 'desc')
    ->get();
```

#### Query Sweep Logs

```php
use Multicoin\TokenSweeper\Models\SweepLog;

// Get recent sweep logs
$logs = SweepLog::orderBy('created_at', 'desc')
    ->limit(50)
    ->get();

// Get logs for specific chain
$ethLogs = SweepLog::where('chain_id', 1)
    ->orderBy('created_at', 'desc')
    ->get();

// Get successful sweeps
$successful = SweepLog::where('status', 'completed')->get();

// Get sweep by transaction hash
$sweep = SweepLog::where('sweep_tx_hash', '0x...')
    ->first();
```

---

## Commands Reference

### sweeper:install

Install and setup the Token Sweeper package.

```bash
php artisan sweeper:install
```

**What it does:**
- Publishes configuration file
- Runs database migrations
- Displays next steps

**Options:** None

---

### sweeper:seed

Seed default chains and tokens from configuration.

```bash
php artisan sweeper:seed
```

**What it does:**
- Creates/updates chains from `config/token-sweeper.php`
- Creates/updates tokens from `config/token-sweeper.php`
- Uses `updateOrCreate` to avoid duplicates

**Options:** None

**Example output:**
```
Seeding chains and tokens...
✅ Seeded chain: Ethereum
✅ Seeded chain: BSC
✅ Seeded chain: Polygon
✅ Seeded token: USDT on chain 1
✅ Seeded token: USDC on chain 1
✅ Seeding completed!
```

---

### sweeper:generate-address

Generate a deposit address for a user on a specific chain.

```bash
php artisan sweeper:generate-address {user_id} {chain_id}
```

**Arguments:**
- `user_id` - User ID (integer)
- `chain_id` - Blockchain chain ID (integer)

**Examples:**
```bash
# Generate Ethereum address for user 123
php artisan sweeper:generate-address 123 1

# Generate BSC address for user 456
php artisan sweeper:generate-address 456 56

# Generate Polygon address for user 789
php artisan sweeper:generate-address 789 137
```

**Output:**
```
Generating deposit address for user 123 on chain 1...
✅ Deposit address created: 0x1234567890123456789012345678901234567890
```

---

### sweeper:monitor

Start monitoring blockchain for token deposits.

```bash
php artisan sweeper:monitor
```

**What it does:**
- Continuously monitors all deposit addresses
- Checks for token balances at configured intervals
- Fires `DepositDetected` events
- Queues sweep jobs automatically

**Options:** None

**Usage in Production:**
- Run as a daemon using Supervisor
- Monitor logs for errors
- Ensure queue workers are running

---

### sweeper:sweep

Manually process a token sweep.

```bash
php artisan sweeper:sweep {address} {token} {chain_id}
```

**Arguments:**
- `address` - Deposit address to sweep from
- `token` - Token contract address
- `chain_id` - Blockchain chain ID

**Examples:**
```bash
# Sweep USDT from Ethereum address
php artisan sweeper:sweep \
  0x1234567890123456789012345678901234567890 \
  0xdAC17F958D2ee523a2206206994597C13D831ec7 \
  1

# Sweep USDC from BSC address
php artisan sweeper:sweep \
  0x0987654321098765432109876543210987654321 \
  0x8AC76a51cc950d9822D68b83fE1Ad97B32Cd580d \
  56
```

**Output:**
```
Processing sweep for 0x1234567890123456789012345678901234567890...
✅ Sweep completed successfully!
```

---

### sweeper:balance

Check native and token balances for an address.

```bash
php artisan sweeper:balance {address} {token?} {chain_id=1}
```

**Arguments:**
- `address` - Address to check
- `token` - (Optional) Token contract address
- `chain_id` - (Optional) Chain ID (default: 1)

**Examples:**
```bash
# Check native balance on Ethereum
php artisan sweeper:balance 0x1234... "" 1

# Check USDT balance on Ethereum
php artisan sweeper:balance 0x1234... 0xdAC17F958D2ee523a2206206994597C13D831ec7 1

# Check BNB balance on BSC
php artisan sweeper:balance 0x1234... "" 56
```

**Output:**
```
Chain: Ethereum
Address: 0x1234567890123456789012345678901234567890
Native Balance: 0.05 ETH
Token Balance: 1000000000 (raw)
```

---

### sweeper:pending

View pending, failed, or completed sweeps.

```bash
php artisan sweeper:pending {--status=pending}
```

**Options:**
- `--status` - Filter by status: `pending`, `failed`, `completed`, `funding`, `sweeping`

**Examples:**
```bash
# View pending sweeps
php artisan sweeper:pending

# View failed sweeps
php artisan sweeper:pending --status=failed

# View completed sweeps
php artisan sweeper:pending --status=completed
```

**Output:**
```
Pending Sweeps (Status: pending):
+----+-----------+---------------+--------+---------+----------------+
| ID | Chain     | Address       | Token  | Status  | Created        |
+----+-----------+---------------+--------+---------+----------------+
| 1  | Ethereum  | 0x1234...     | USDT   | pending | 2 minutes ago  |
| 2  | BSC       | 0x5678...     | USDC   | pending | 5 minutes ago  |
+----+-----------+---------------+--------+---------+----------------+
```

---

## Events and Listeners

### Available Events

The package fires three main events during the sweep lifecycle:

#### 1. DepositDetected

Fired when a token deposit is detected on a deposit address.

**Event Properties:**
```php
namespace Multicoin\TokenSweeper\Events;

class DepositDetected
{
    public string $depositAddress;
    public string $tokenAddress;
    public int $chainId;
    public string $amount;
}
```

**Use Cases:**
- Send user notifications
- Update user balance in database
- Trigger webhooks
- Log deposits
- Send emails/SMS

#### 2. SweepStarted

Fired when a sweep process begins.

**Event Properties:**
```php
namespace Multicoin\TokenSweeper\Events;

class SweepStarted
{
    public PendingSweep $sweep;
}
```

**Use Cases:**
- Track sweep progress
- Send status updates
- Log sweep initiation

#### 3. SweepCompleted

Fired when a sweep is successfully completed.

**Event Properties:**
```php
namespace Multicoin\TokenSweeper\Events;

class SweepCompleted
{
    public PendingSweep $sweep;
}
```

**Use Cases:**
- Update user balances
- Send completion notifications
- Trigger accounting systems
- Generate reports

### Creating Event Listeners

#### Example: User Notification Listener

```php
// app/Listeners/NotifyUserOfDeposit.php

namespace App\Listeners;

use Multicoin\TokenSweeper\Events\DepositDetected;
use App\Models\User;
use App\Notifications\TokenDepositReceived;
use Multicoin\TokenSweeper\Models\{Token, DepositAddress};

class NotifyUserOfDeposit
{
    public function handle(DepositDetected $event): void
    {
        // Find the user
        $depositAddress = DepositAddress::where('address', $event->depositAddress)
            ->where('chain_id', $event->chainId)
            ->first();

        if (!$depositAddress) {
            return;
        }

        $user = User::find($depositAddress->user_id);

        if (!$user) {
            return;
        }

        // Find token details
        $token = Token::where('contract_address', $event->tokenAddress)
            ->where('chain_id', $event->chainId)
            ->first();

        // Format amount
        $decimals = $token?->decimals ?? 18;
        $formattedAmount = bcdiv($event->amount, bcpow('10', $decimals), $decimals);

        // Send notification
        $user->notify(new TokenDepositReceived([
            'token' => $token?->symbol ?? 'Unknown',
            'amount' => $formattedAmount,
            'chain' => $event->chainId,
            'address' => $event->depositAddress,
        ]));
    }
}
```

#### Example: Balance Update Listener

```php
// app/Listeners/UpdateUserBalance.php

namespace App\Listeners;

use Multicoin\TokenSweeper\Events\SweepCompleted;
use App\Models\{User, UserBalance};
use Multicoin\TokenSweeper\Models\{Token, DepositAddress};

class UpdateUserBalance
{
    public function handle(SweepCompleted $event): void
    {
        $sweep = $event->sweep;

        // Find user
        $depositAddress = DepositAddress::where('address', $sweep->deposit_address)
            ->where('chain_id', $sweep->chain_id)
            ->first();

        if (!$depositAddress) {
            return;
        }

        // Find token
        $token = Token::where('contract_address', $sweep->token_address)
            ->where('chain_id', $sweep->chain_id)
            ->first();

        if (!$token) {
            return;
        }

        // Update user balance
        UserBalance::updateOrCreate(
            [
                'user_id' => $depositAddress->user_id,
                'token_id' => $token->id,
            ],
            [
                'balance' => \DB::raw("balance + {$sweep->amount}"),
            ]
        );
    }
}
```

#### Example: Webhook Listener

```php
// app/Listeners/TriggerSweepWebhook.php

namespace App\Listeners;

use Multicoin\TokenSweeper\Events\SweepCompleted;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TriggerSweepWebhook
{
    public function handle(SweepCompleted $event): void
    {
        $sweep = $event->sweep;

        $payload = [
            'event' => 'sweep_completed',
            'sweep_id' => $sweep->id,
            'deposit_address' => $sweep->deposit_address,
            'token_address' => $sweep->token_address,
            'chain_id' => $sweep->chain_id,
            'amount' => $sweep->amount,
            'funding_tx_hash' => $sweep->funding_tx_hash,
            'sweep_tx_hash' => $sweep->sweep_tx_hash,
            'timestamp' => $sweep->updated_at->toIso8601String(),
        ];

        try {
            Http::timeout(10)
                ->post(config('services.webhook.url'), $payload);

            Log::info('Sweep webhook sent', ['sweep_id' => $sweep->id]);
        } catch (\Exception $e) {
            Log::error('Webhook failed', [
                'sweep_id' => $sweep->id,
                'error' => $e->getMessage()
            ]);
        }
    }
}
```

### Registering Listeners

Register your listeners in `app/Providers/EventServiceProvider.php`:

```php
namespace App\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Multicoin\TokenSweeper\Events\{DepositDetected, SweepStarted, SweepCompleted};
use App\Listeners\{
    NotifyUserOfDeposit,
    UpdateUserBalance,
    TriggerSweepWebhook
};

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        DepositDetected::class => [
            NotifyUserOfDeposit::class,
        ],
        SweepCompleted::class => [
            UpdateUserBalance::class,
            TriggerSweepWebhook::class,
        ],
    ];

    public function boot(): void
    {
        //
    }
}
```

---

## Queue Configuration

The Token Sweeper package uses Laravel's queue system for asynchronous processing. Proper queue configuration is essential for production.

### Queue Drivers

#### Redis (Recommended)

Update `.env`:

```env
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
```

Install Redis client:

```bash
composer require predis/predis
```

Or use PhpRedis extension (faster):

```bash
pecl install redis
```

#### Database Queue

Alternative option for smaller deployments:

```env
QUEUE_CONNECTION=database
```

Run migration:

```bash
php artisan queue:table
php artisan migrate
```

### Queue Workers

#### Development

Start a queue worker:

```bash
php artisan queue:work --queue=default --tries=3
```

Options:
- `--queue=default` - Queue name
- `--tries=3` - Retry attempts
- `--timeout=300` - Job timeout (seconds)
- `--sleep=3` - Sleep between jobs
- `--max-jobs=1000` - Restart after N jobs
- `--max-time=3600` - Restart after N seconds

#### Production with Supervisor

Create supervisor configuration:

```bash
sudo nano /etc/supervisor/conf.d/token-sweeper-queue.conf
```

```ini
[program:token-sweeper-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/html/artisan queue:work redis --queue=default --tries=3 --max-time=3600 --timeout=300
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=3
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/queue-worker.log
stopwaitsecs=3600
```

Reload supervisor:

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start token-sweeper-queue:*
```

Check status:

```bash
sudo supervisorctl status token-sweeper-queue:*
```

#### Production with Laravel Horizon

Install Horizon:

```bash
composer require laravel/horizon
php artisan horizon:install
```

Configure `config/horizon.php`:

```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default'],
            'balance' => 'auto',
            'minProcesses' => 3,
            'maxProcesses' => 10,
            'balanceMaxShift' => 1,
            'balanceCooldown' => 3,
            'tries' => 3,
            'timeout' => 300,
        ],
    ],
],
```

Supervisor config for Horizon:

```ini
[program:token-sweeper-horizon]
process_name=%(program_name)s
command=php /var/www/html/artisan horizon
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/html/storage/logs/horizon.log
stopwaitsecs=3600
```

### Queue Jobs

The package includes these queue jobs:

#### 1. FundDepositAddress

Sends native gas tokens from master wallet to deposit address.

**Properties:**
- Queue: `default`
- Tries: 3
- Timeout: 300 seconds

**Process:**
1. Check if address has sufficient gas
2. If not, send gas from master wallet
3. Wait for confirmation
4. Dispatch `SweepTokens` job

#### 2. SweepTokens

Sweeps tokens from deposit address to hot wallet.

**Properties:**
- Queue: `default`
- Tries: 3
- Timeout: 300 seconds

**Process:**
1. Get token balance
2. Build transfer transaction
3. Sign and broadcast
4. Wait for confirmation
5. Create sweep log
6. Fire `SweepCompleted` event

#### 3. CheckPendingSweeps

Retries failed sweeps.

**Properties:**
- Queue: `default`
- Schedule: Every 5 minutes (via scheduler)

**Process:**
1. Find failed sweeps within retry limits
2. Re-dispatch sweep for each

### Scheduling Queue Maintenance

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule): void
{
    // Retry failed sweeps every 5 minutes
    $schedule->job(new \Multicoin\TokenSweeper\Jobs\CheckPendingSweeps)
        ->everyFiveMinutes()
        ->withoutOverlapping();

    // Clean old completed sweeps
    $schedule->command('sweeper:cleanup')
        ->daily()
        ->at('02:00');
}
```

### Queue Monitoring

#### View Failed Jobs

```bash
php artisan queue:failed
```

#### Retry Failed Jobs

```bash
# Retry all failed jobs
php artisan queue:retry all

# Retry specific job
php artisan queue:retry {id}
```

#### Flush Failed Jobs

```bash
php artisan queue:flush
```

#### Clear Queue

```bash
php artisan queue:clear
```

---

## Monitoring and Logging

### Log Channels

Configure logging in `config/logging.php`:

```php
'channels' => [
    'sweeper' => [
        'driver' => 'daily',
        'path' => storage_path('logs/sweeper.log'),
        'level' => env('LOG_LEVEL', 'info'),
        'days' => 14,
    ],
],
```

Use in your code:

```php
use Illuminate\Support\Facades\Log;

Log::channel('sweeper')->info('Deposit detected', [
    'address' => $address,
    'token' => $token,
    'amount' => $amount,
]);
```

### Application Monitoring

#### Monitor Service Status

Create a health check endpoint:

```php
// routes/api.php
Route::get('/sweeper/health', function () {
    return [
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
        'chains' => \Multicoin\TokenSweeper\Models\Chain::count(),
        'tokens' => \Multicoin\TokenSweeper\Models\Token::count(),
        'pending_sweeps' => \Multicoin\TokenSweeper\Models\PendingSweep::pending()->count(),
    ];
});
```

#### Supervisor Monitoring

Check if monitor is running:

```bash
sudo supervisorctl status token-sweeper-monitor
```

View logs:

```bash
tail -f /var/log/token-sweeper/monitor.log
```

#### Queue Monitoring

Check queue size:

```bash
php artisan queue:monitor redis:default --max=100
```

With Laravel Horizon:

```bash
php artisan horizon:list
```

View Horizon dashboard:

```
https://yourapp.com/horizon
```

### Performance Metrics

#### Database Queries

Track sweep performance:

```php
use Multicoin\TokenSweeper\Models\SweepLog;

// Average sweep time
$avgTime = SweepLog::selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as avg_time')
    ->where('status', 'completed')
    ->value('avg_time');

// Sweep success rate
$total = SweepLog::count();
$successful = SweepLog::where('status', 'completed')->count();
$successRate = ($successful / $total) * 100;

// Sweeps by chain
$byChain = SweepLog::selectRaw('chain_id, COUNT(*) as count')
    ->groupBy('chain_id')
    ->get();
```

### Error Tracking

#### Sentry Integration

Install Sentry:

```bash
composer require sentry/sentry-laravel
php artisan sentry:publish --dsn=your-dsn-here
```

Configure in `config/logging.php`:

```php
'channels' => [
    'sentry' => [
        'driver' => 'sentry',
        'level' => 'error',
    ],
],
```

Track sweep errors:

```php
try {
    $sweeper->processSweep($address, $token, $chainId);
} catch (\Exception $e) {
    app('sentry')->captureException($e);
    Log::error('Sweep failed', [
        'error' => $e->getMessage(),
        'address' => $address,
    ]);
}
```

### Alerts and Notifications

#### Slack Notifications

Configure Slack webhook:

```env
LOG_SLACK_WEBHOOK_URL=https://hooks.slack.com/services/YOUR/WEBHOOK/URL
```

Add to `config/logging.php`:

```php
'channels' => [
    'slack' => [
        'driver' => 'slack',
        'url' => env('LOG_SLACK_WEBHOOK_URL'),
        'username' => 'Token Sweeper',
        'emoji' => ':robot_face:',
        'level' => 'error',
    ],
],
```

Send alerts:

```php
Log::channel('slack')->error('Sweep failed', [
    'address' => $address,
    'error' => $error,
]);
```

---

## Production Best Practices

### Security

#### 1. Private Key Management

- **Never commit private keys to version control**
- **Always encrypt keys using Laravel's encryption**
- **Consider Hardware Security Modules (HSM) for production**
- **Rotate master wallet keys periodically**
- **Use separate wallets for different environments**

```php
// Good: Encrypted storage
$encrypted = Crypt::encryptString($privateKey);

// Bad: Plain text
$privateKey = '0x1234...'; // NEVER DO THIS
```

#### 2. Environment Variables

```env
# Use strong encryption key
APP_KEY=base64:your-32-character-key-here

# Separate environments
APP_ENV=production
APP_DEBUG=false

# Secure database
DB_PASSWORD=strong-password-here
```

#### 3. API Authentication

Add authentication to API routes:

```php
// routes/api.php
Route::prefix('api/sweeper')
    ->middleware(['api', 'auth:sanctum'])
    ->group(function () {
        // Protected routes
    });
```

#### 4. Rate Limiting

```php
// app/Providers/RouteServiceProvider.php
RateLimiter::for('sweeper', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

// Apply to routes
Route::middleware(['throttle:sweeper'])->group(function () {
    // Rate-limited routes
});
```

### Performance Optimization

#### 1. Database Indexing

Ensure proper indexes:

```php
// database/migrations/add_indexes_to_sweeper_tables.php
Schema::table('sweeper_deposit_addresses', function (Blueprint $table) {
    $table->index(['user_id', 'chain_id']);
    $table->index('address');
});

Schema::table('sweeper_pending_sweeps', function (Blueprint $table) {
    $table->index(['status', 'created_at']);
    $table->index('deposit_address');
});
```

#### 2. Query Optimization

Use eager loading:

```php
// Good
$sweeps = PendingSweep::with(['chain'])->pending()->get();

// Bad (N+1 queries)
$sweeps = PendingSweep::pending()->get();
foreach ($sweeps as $sweep) {
    echo $sweep->chain->name; // Extra query for each
}
```

#### 3. Caching

Cache chain and token data:

```php
use Illuminate\Support\Facades\Cache;

$chains = Cache::remember('sweeper.chains', 3600, function () {
    return Chain::all();
});

$tokens = Cache::remember('sweeper.tokens', 3600, function () {
    return Token::all();
});
```

#### 4. Queue Optimization

Increase worker processes based on load:

```ini
# Supervisor config
numprocs=5  ; Increase for higher throughput
```

### Monitoring Best Practices

#### 1. System Monitoring

```bash
# Monitor CPU and memory
top -p $(pgrep -f "queue:work")

# Monitor disk space
df -h

# Monitor network
netstat -an | grep ESTABLISHED
```

#### 2. Application Metrics

Track key metrics:

- Sweeps per hour
- Success rate
- Average sweep time
- Failed sweeps
- Queue depth

#### 3. Log Management

```bash
# Rotate logs
sudo nano /etc/logrotate.d/token-sweeper

/var/www/html/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 0640 www-data www-data
    sharedscripts
}
```

### Backup and Recovery

#### 1. Database Backups

```bash
# Daily backup script
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u user -p database > backup_$DATE.sql
```

#### 2. Configuration Backups

```bash
# Backup .env and config
tar -czf config_backup_$(date +%Y%m%d).tar.gz .env config/
```

#### 3. Disaster Recovery Plan

- Document RPC provider fallbacks
- Maintain backup master wallets
- Test recovery procedures regularly
- Keep encrypted backup of private keys offline

### Deployment Checklist

Before deploying to production:

- [ ] Environment variables configured
- [ ] Private keys encrypted
- [ ] Database migrations run
- [ ] Chains and tokens seeded
- [ ] Queue workers configured
- [ ] Supervisor/Horizon installed
- [ ] Monitor service running
- [ ] Logs configured and rotating
- [ ] Backups scheduled
- [ ] Monitoring/alerts configured
- [ ] Rate limiting enabled
- [ ] API authentication enabled
- [ ] SSL/HTTPS configured
- [ ] Firewall rules set
- [ ] Testing completed

---

## Examples and Code Snippets

### Complete User Flow Example

```php
use Multicoin\TokenSweeper\Services\WalletService;
use Multicoin\TokenSweeper\Models\{Chain, Token, DepositAddress};
use App\Models\User;

class DepositService
{
    public function __construct(
        private WalletService $walletService
    ) {}

    /**
     * Complete flow: Create user deposit addresses
     */
    public function setupUserDeposits(User $user): array
    {
        $addresses = [];

        // Get all active chains
        $chains = Chain::all();

        foreach ($chains as $chain) {
            // Generate deposit address
            $address = $this->walletService->createDepositAddress(
                $user->id,
                $chain->chain_id
            );

            $addresses[$chain->name] = [
                'address' => $address,
                'chain_id' => $chain->chain_id,
                'native_symbol' => $chain->native_symbol,
            ];
        }

        return $addresses;
    }

    /**
     * Get user's deposit information
     */
    public function getUserDepositInfo(User $user): array
    {
        $addresses = DepositAddress::with(['chain'])
            ->forUser($user->id)
            ->get();

        return $addresses->map(function ($address) {
            return [
                'chain' => $address->chain->name,
                'chain_id' => $address->chain_id,
                'address' => $address->address,
                'native_symbol' => $address->chain->native_symbol,
                'qr_code' => $this->generateQrCode($address->address),
            ];
        })->toArray();
    }

    private function generateQrCode(string $address): string
    {
        // Use a QR code library
        return "data:image/png;base64,..." . base64_encode($address);
    }
}
```

### Advanced Monitoring Example

```php
use Multicoin\TokenSweeper\Services\{MonitorService, Web3Service};
use Multicoin\TokenSweeper\Models\{Chain, Token, DepositAddress};
use Illuminate\Support\Facades\Log;

class CustomMonitorService
{
    /**
     * Monitor with custom logic
     */
    public function monitorWithThreshold(string $minAmount): void
    {
        $depositAddresses = DepositAddress::with(['chain'])->get();
        $tokens = Token::active()->get();

        foreach ($depositAddresses as $deposit) {
            $chain = $deposit->chain;
            $web3 = new Web3Service($chain->rpc_url, $chain->chain_id);

            foreach ($tokens->where('chain_id', $chain->chain_id) as $token) {
                $balance = $this->getTokenBalance(
                    $web3,
                    $deposit->address,
                    $token->contract_address
                );

                // Only trigger sweep if above threshold
                if (bccomp($balance, $minAmount) > 0) {
                    Log::info("Balance above threshold", [
                        'address' => $deposit->address,
                        'token' => $token->symbol,
                        'balance' => $balance,
                        'threshold' => $minAmount,
                    ]);

                    // Trigger sweep
                    event(new \Multicoin\TokenSweeper\Events\DepositDetected(
                        $deposit->address,
                        $token->contract_address,
                        $chain->chain_id,
                        $balance
                    ));
                }
            }
        }
    }

    private function getTokenBalance(
        Web3Service $web3,
        string $address,
        string $tokenAddress
    ): string {
        $data = '0x70a08231' . str_pad(
            str_replace('0x', '', $address),
            64,
            '0',
            STR_PAD_LEFT
        );

        $balanceHex = $web3->callContract($tokenAddress, $data);
        return (string) hexdec($balanceHex);
    }
}
```

### Batch Address Generation

```php
use Multicoin\TokenSweeper\Services\WalletService;
use Illuminate\Support\Facades\DB;

class BatchAddressService
{
    public function __construct(
        private WalletService $walletService
    ) {}

    /**
     * Generate deposit addresses for multiple users
     */
    public function generateBatchAddresses(array $userIds, int $chainId): array
    {
        $results = [];

        DB::beginTransaction();

        try {
            foreach ($userIds as $userId) {
                $address = $this->walletService->createDepositAddress(
                    $userId,
                    $chainId
                );

                $results[$userId] = $address;
            }

            DB::commit();

            return $results;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }
}
```

### Custom Webhook Handler

```php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Multicoin\TokenSweeper\Events\DepositDetected;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle external webhook for deposit notifications
     */
    public function handleDeposit(Request $request)
    {
        $validated = $request->validate([
            'address' => 'required|string',
            'token' => 'required|string',
            'chain_id' => 'required|integer',
            'amount' => 'required|string',
            'signature' => 'required|string',
        ]);

        // Verify webhook signature
        if (!$this->verifySignature($request)) {
            return response()->json(['error' => 'Invalid signature'], 403);
        }

        // Fire deposit event
        event(new DepositDetected(
            $validated['address'],
            $validated['token'],
            $validated['chain_id'],
            $validated['amount']
        ));

        Log::info('Webhook deposit processed', $validated);

        return response()->json(['status' => 'success']);
    }

    private function verifySignature(Request $request): bool
    {
        $payload = $request->except('signature');
        $signature = $request->input('signature');
        $secret = config('services.webhook.secret');

        $expectedSignature = hash_hmac('sha256', json_encode($payload), $secret);

        return hash_equals($expectedSignature, $signature);
    }
}
```

### Custom Sweep Strategy

```php
use Multicoin\TokenSweeper\Services\SweeperService;
use Multicoin\TokenSweeper\Models\{DepositAddress, Token};

class CustomSweeperStrategy
{
    public function __construct(
        private SweeperService $sweeper
    ) {}

    /**
     * Sweep only high-value deposits
     */
    public function sweepHighValueOnly(string $minUsdValue): void
    {
        $deposits = DepositAddress::all();

        foreach ($deposits as $deposit) {
            $tokens = Token::forChain($deposit->chain_id)->get();

            foreach ($tokens as $token) {
                $balance = $this->getTokenBalance($deposit, $token);
                $usdValue = $this->convertToUsd($balance, $token);

                if (bccomp($usdValue, $minUsdValue) >= 0) {
                    $this->sweeper->processSweep(
                        $deposit->address,
                        $token->contract_address,
                        $deposit->chain_id
                    );
                }
            }
        }
    }

    /**
     * Batch sweep for gas efficiency
     */
    public function batchSweepSameToken(string $tokenAddress, int $chainId): void
    {
        $deposits = DepositAddress::forChain($chainId)
            ->whereHas('pendingSweeps', function ($query) use ($tokenAddress) {
                $query->where('token_address', $tokenAddress)
                    ->where('status', 'pending');
            })
            ->get();

        foreach ($deposits as $deposit) {
            $this->sweeper->processSweep(
                $deposit->address,
                $tokenAddress,
                $chainId
            );
        }
    }
}
```

### Reporting and Analytics

```php
use Multicoin\TokenSweeper\Models\{SweepLog, PendingSweep, Chain};
use Illuminate\Support\Facades\DB;

class SweeperAnalytics
{
    /**
     * Generate daily sweep report
     */
    public function dailyReport(): array
    {
        $today = now()->startOfDay();

        return [
            'total_sweeps' => SweepLog::whereDate('created_at', $today)->count(),
            'successful' => SweepLog::whereDate('created_at', $today)
                ->where('status', 'completed')
                ->count(),
            'failed' => SweepLog::whereDate('created_at', $today)
                ->where('status', 'failed')
                ->count(),
            'by_chain' => SweepLog::whereDate('created_at', $today)
                ->select('chain_id', DB::raw('COUNT(*) as count'))
                ->groupBy('chain_id')
                ->with('chain:chain_id,name')
                ->get(),
            'total_pending' => PendingSweep::pending()->count(),
        ];
    }

    /**
     * Calculate success rate
     */
    public function successRate(int $days = 7): float
    {
        $since = now()->subDays($days);

        $total = SweepLog::where('created_at', '>=', $since)->count();
        $successful = SweepLog::where('created_at', '>=', $since)
            ->where('status', 'completed')
            ->count();

        return $total > 0 ? ($successful / $total) * 100 : 0;
    }

    /**
     * Get average sweep time per chain
     */
    public function avgSweepTimeByChain(): array
    {
        return SweepLog::select('chain_id')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as avg_seconds')
            ->where('status', 'completed')
            ->groupBy('chain_id')
            ->with('chain:chain_id,name')
            ->get()
            ->map(function ($log) {
                return [
                    'chain' => $log->chain->name,
                    'avg_time' => round($log->avg_seconds, 2) . ' seconds',
                ];
            })
            ->toArray();
    }
}
```

---

## API Reference

### REST API Endpoints

All endpoints are prefixed with `/api/sweeper`.

#### GET /health

Health check endpoint.

**Response:**
```json
{
  "status": "ok",
  "timestamp": "2024-01-01T12:00:00Z"
}
```

#### GET /chains

Get all configured chains.

**Response:**
```json
[
  {
    "chain_id": 1,
    "name": "Ethereum",
    "native_symbol": "ETH",
    "rpc_url": "https://eth.nownodes.io/..."
  }
]
```

#### GET /tokens

Get all configured tokens.

**Query Parameters:**
- `chain_id` (optional): Filter by chain

**Response:**
```json
[
  {
    "id": 1,
    "chain_id": 1,
    "symbol": "USDT",
    "name": "Tether USD",
    "contract_address": "0xdAC17F958D2ee523a2206206994597C13D831ec7",
    "decimals": 6
  }
]
```

#### POST /deposit-address

Create a new deposit address.

**Request:**
```json
{
  "user_id": 123,
  "chain_id": 1
}
```

**Response:**
```json
{
  "address": "0x1234567890123456789012345678901234567890",
  "chain_id": 1,
  "user_id": 123
}
```

#### GET /user-addresses

Get deposit addresses for a user.

**Query Parameters:**
- `user_id` (required): User ID

**Response:**
```json
[
  {
    "address": "0x...",
    "chain_id": 1,
    "chain": {
      "name": "Ethereum",
      "native_symbol": "ETH"
    }
  }
]
```

#### GET /sweep-status

Get sweep status for an address.

**Query Parameters:**
- `address` (required): Deposit address
- `chain_id` (required): Chain ID

**Response:**
```json
{
  "address": "0x...",
  "chain_id": 1,
  "pending_sweeps": 2,
  "completed_sweeps": 5
}
```

#### GET /pending-sweeps

Get pending sweeps.

**Query Parameters:**
- `status` (optional): Filter by status

**Response:**
```json
[
  {
    "id": 1,
    "deposit_address": "0x...",
    "token_address": "0x...",
    "chain_id": 1,
    "status": "pending",
    "created_at": "2024-01-01T12:00:00Z"
  }
]
```

#### GET /sweep-logs

Get sweep logs.

**Query Parameters:**
- `chain_id` (optional): Filter by chain
- `limit` (optional): Number of records (default: 50)

**Response:**
```json
[
  {
    "id": 1,
    "deposit_address": "0x...",
    "token_address": "0x...",
    "amount": "1000000000",
    "funding_tx_hash": "0x...",
    "sweep_tx_hash": "0x...",
    "status": "completed",
    "created_at": "2024-01-01T12:00:00Z"
  }
]
```

---

## Troubleshooting

### Common Issues

#### Issue: Monitor not detecting deposits

**Solution:**
1. Check RPC connection:
```bash
php artisan sweeper:balance 0xYourAddress "" 1
```

2. Verify chain configuration:
```bash
php artisan tinker
\Multicoin\TokenSweeper\Models\Chain::all()
```

3. Check monitor logs:
```bash
tail -f storage/logs/laravel.log
```

#### Issue: Sweep fails with "insufficient funds"

**Solution:**
1. Check master wallet balance:
```bash
php artisan sweeper:balance {MASTER_WALLET} "" {CHAIN_ID}
```

2. Fund master wallet with native tokens

3. Verify gas amount in config

#### Issue: Queue not processing

**Solution:**
1. Check if workers are running:
```bash
ps aux | grep "queue:work"
```

2. Start queue worker:
```bash
php artisan queue:work
```

3. Check failed jobs:
```bash
php artisan queue:failed
```

4. Retry failed jobs:
```bash
php artisan queue:retry all
```

---

## Support and Contributing

For issues, questions, or contributions:

- **GitHub Issues**: Report bugs and request features
- **Documentation**: Check the README and this guide
- **Community**: Join discussions

---

**License**: MIT

**Package**: multicoin/token-sweeper

**Version**: 1.0.0
