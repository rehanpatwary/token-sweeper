# Laravel Token Sweeper - API Reference

Complete API documentation for the Laravel Token Sweeper package.

---

## Table of Contents

1. [Services API](#services-api)
   - [Web3Service](#web3service)
   - [WalletService](#walletservice)
   - [TransactionSignerService](#transactionsignerservice)
   - [SweeperService](#sweeperservice)
   - [MonitorService](#monitorservice)
2. [Models API](#models-api)
   - [Chain](#chain-model)
   - [Token](#token-model)
   - [DepositAddress](#depositaddress-model)
   - [PendingSweep](#pendingsweep-model)
   - [SweepLog](#sweeplog-model)
3. [Jobs API](#jobs-api)
   - [FundDepositAddress](#funddepositaddress-job)
   - [SweepTokens](#sweeptokens-job)
   - [CheckPendingSweeps](#checkpendingsweeps-job)
4. [Events API](#events-api)
   - [DepositDetected](#depositdetected-event)
   - [SweepStarted](#sweepstarted-event)
   - [SweepCompleted](#sweepcompleted-event)
5. [Commands API](#commands-api)
   - [sweeper:install](#sweeperinstall)
   - [sweeper:seed](#sweeperseed)
   - [sweeper:generate-address](#sweepergenerate-address)
   - [sweeper:monitor](#sweepermonitor)
   - [sweeper:sweep](#sweepersweep)
   - [sweeper:balance](#sweeperbalance)
   - [sweeper:pending](#sweeperpending)
6. [Facades API](#facades-api)
   - [TokenSweeper](#tokensweeper-facade)
7. [HTTP API](#http-api)
   - [REST Endpoints](#rest-endpoints)
8. [Configuration Reference](#configuration-reference)

---

## Services API

### Web3Service

**Namespace:** `Multicoin\TokenSweeper\Services\Web3Service`

Provides low-level Web3 RPC functionality for interacting with EVM-compatible blockchains.

#### Constructor

```php
public function __construct(string $rpcUrl, int $chainId)
```

**Parameters:**
- `$rpcUrl` (string) - The blockchain RPC endpoint URL
- `$chainId` (int) - The chain ID (e.g., 1 for Ethereum, 56 for BSC)

**Example:**
```php
$web3 = new Web3Service('https://eth.llamarpc.com', 1);
```

---

#### call()

Makes a generic JSON-RPC call to the blockchain node.

```php
public function call(string $method, array $params = []): mixed
```

**Parameters:**
- `$method` (string) - The RPC method name (e.g., 'eth_getBalance')
- `$params` (array) - Method parameters

**Returns:** `mixed` - The result from the RPC call

**Throws:** `\Exception` - When RPC returns an error

**Example:**
```php
$result = $web3->call('eth_blockNumber', []);
```

---

#### getBalance()

Gets the native token balance of an address.

```php
public function getBalance(string $address): string
```

**Parameters:**
- `$address` (string) - The wallet address (0x prefixed)

**Returns:** `string` - Balance in wei (hexadecimal format)

**Example:**
```php
$balance = $web3->getBalance('0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb');
// Returns: "0x1bc16d674ec80000" (2 ETH in hex)

$balanceInWei = hexdec($balance);
$balanceInEth = $balanceInWei / 1e18;
```

---

#### getTransactionCount()

Gets the transaction count (nonce) for an address.

```php
public function getTransactionCount(string $address): string
```

**Parameters:**
- `$address` (string) - The wallet address

**Returns:** `string` - Transaction count in hexadecimal format

**Example:**
```php
$nonce = hexdec($web3->getTransactionCount('0x742d35Cc...'));
```

---

#### sendRawTransaction()

Broadcasts a signed transaction to the network.

```php
public function sendRawTransaction(string $signedTx): string
```

**Parameters:**
- `$signedTx` (string) - RLP-encoded signed transaction (0x prefixed)

**Returns:** `string` - Transaction hash

**Throws:** `\Exception` - If transaction is rejected

**Example:**
```php
$txHash = $web3->sendRawTransaction('0xf86c...');
// Returns: "0x1234567890abcdef..."
```

---

#### getTransactionReceipt()

Gets the receipt of a transaction.

```php
public function getTransactionReceipt(string $txHash): ?array
```

**Parameters:**
- `$txHash` (string) - Transaction hash

**Returns:** `array|null` - Transaction receipt or null if not found

**Example:**
```php
$receipt = $web3->getTransactionReceipt('0x1234...');
if ($receipt && $receipt['status'] === '0x1') {
    echo "Transaction successful!";
}
```

---

#### gasPrice()

Gets the current gas price (cached for 10 seconds).

```php
public function gasPrice(): string
```

**Returns:** `string` - Gas price in wei (hexadecimal)

**Example:**
```php
$gasPrice = hexdec($web3->gasPrice());
$gasPriceInGwei = $gasPrice / 1e9;
```

---

#### estimateGas()

Estimates gas required for a transaction.

```php
public function estimateGas(array $transaction): string
```

**Parameters:**
- `$transaction` (array) - Transaction object with keys: from, to, data, value

**Returns:** `string` - Estimated gas in hexadecimal

**Example:**
```php
$estimatedGas = $web3->estimateGas([
    'from' => '0x123...',
    'to' => '0x456...',
    'value' => '0x0',
    'data' => '0xa9059cbb...'
]);
```

---

#### callContract()

Makes a read-only contract call.

```php
public function callContract(string $to, string $data): string
```

**Parameters:**
- `$to` (string) - Contract address
- `$data` (string) - ABI-encoded function call data

**Returns:** `string` - ABI-encoded return value

**Example:**
```php
// Call balanceOf(address)
$data = '0x70a08231' . str_pad(str_replace('0x', '', $address), 64, '0', STR_PAD_LEFT);
$balance = $web3->callContract($tokenAddress, $data);
```

---

#### getBlockNumber()

Gets the latest block number.

```php
public function getBlockNumber(): string
```

**Returns:** `string` - Block number in hexadecimal

**Example:**
```php
$blockNumber = hexdec($web3->getBlockNumber());
```

---

#### getLogs()

Retrieves event logs from the blockchain.

```php
public function getLogs(array $params): array
```

**Parameters:**
- `$params` (array) - Filter parameters (fromBlock, toBlock, address, topics)

**Returns:** `array` - Array of log entries

**Example:**
```php
$logs = $web3->getLogs([
    'fromBlock' => '0x1000000',
    'toBlock' => '0x1000010',
    'address' => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
    'topics' => ['0xddf252ad1be2c89b69c2b068fc378daa952ba7f163c4a11628f55a4df523b3ef']
]);
```

---

#### waitForConfirmation()

Waits for a transaction to be confirmed.

```php
public function waitForConfirmation(string $txHash, int $maxAttempts = 60): bool
```

**Parameters:**
- `$txHash` (string) - Transaction hash
- `$maxAttempts` (int) - Maximum polling attempts (default: 60)

**Returns:** `bool` - True if successful, false otherwise

**Throws:** `\Exception` - On timeout or transaction failure

**Example:**
```php
try {
    $success = $web3->waitForConfirmation($txHash);
    echo "Transaction confirmed!";
} catch (\Exception $e) {
    echo "Transaction failed or timed out";
}
```

---

### WalletService

**Namespace:** `Multicoin\TokenSweeper\Services\WalletService`

Manages wallet generation and deposit addresses.

#### Constructor

```php
public function __construct()
```

No parameters required. Initializes secp256k1 elliptic curve cryptography.

---

#### generateWallet()

Generates a new Ethereum wallet (address and private key).

```php
public function generateWallet(): array
```

**Returns:** `array` - Array with keys:
- `address` (string) - The Ethereum address (0x prefixed)
- `privateKey` (string) - The private key (0x prefixed, 64 hex characters)

**Example:**
```php
$wallet = $walletService->generateWallet();
// [
//     'address' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
//     'privateKey' => '0x1234567890abcdef...'
// ]
```

---

#### createDepositAddress()

Creates a deposit address for a user on a specific chain.

```php
public function createDepositAddress(int $userId, int $chainId): string
```

**Parameters:**
- `$userId` (int) - The user ID
- `$chainId` (int) - The chain ID

**Returns:** `string` - The deposit address

**Note:** If a deposit address already exists for the user/chain combination, returns the existing address.

**Example:**
```php
$address = $walletService->createDepositAddress(123, 1);
// Returns: "0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb"
```

---

#### getPrivateKey()

Retrieves the decrypted private key for a deposit address.

```php
public function getPrivateKey(string $address, int $chainId): string
```

**Parameters:**
- `$address` (string) - The deposit address
- `$chainId` (int) - The chain ID

**Returns:** `string` - Decrypted private key (0x prefixed)

**Throws:** `Illuminate\Database\Eloquent\ModelNotFoundException` - If address not found

**Example:**
```php
$privateKey = $walletService->getPrivateKey('0x742d35Cc...', 1);
```

---

#### getMasterWallet()

Gets the master wallet credentials for a chain.

```php
public function getMasterWallet(int $chainId): array
```

**Parameters:**
- `$chainId` (int) - The chain ID

**Returns:** `array` - Array with keys:
- `address` (string) - Master wallet address
- `privateKey` (string) - Decrypted master private key

**Throws:** `Illuminate\Database\Eloquent\ModelNotFoundException` - If chain not found

**Example:**
```php
$masterWallet = $walletService->getMasterWallet(1);
// [
//     'address' => '0x123...',
//     'privateKey' => '0xabc...'
// ]
```

---

#### getUserDepositAddresses()

Gets all deposit addresses for a user across all chains.

```php
public function getUserDepositAddresses(int $userId): \Illuminate\Database\Eloquent\Collection
```

**Parameters:**
- `$userId` (int) - The user ID

**Returns:** `Collection` - Collection of DepositAddress models with chain relationship loaded

**Example:**
```php
$addresses = $walletService->getUserDepositAddresses(123);
foreach ($addresses as $depositAddress) {
    echo $depositAddress->chain->name . ': ' . $depositAddress->address;
}
```

---

#### getAllDepositAddresses()

Gets all deposit addresses, optionally filtered by chain.

```php
public function getAllDepositAddresses(?int $chainId = null): \Illuminate\Database\Eloquent\Collection
```

**Parameters:**
- `$chainId` (int|null) - Optional chain ID filter

**Returns:** `Collection` - Collection of DepositAddress models

**Example:**
```php
// Get all addresses
$allAddresses = $walletService->getAllDepositAddresses();

// Get addresses for specific chain
$ethAddresses = $walletService->getAllDepositAddresses(1);
```

---

### TransactionSignerService

**Namespace:** `Multicoin\TokenSweeper\Services\TransactionSignerService`

Handles transaction signing using secp256k1 ECDSA.

#### Constructor

```php
public function __construct()
```

Initializes the elliptic curve cryptography context.

---

#### signTransaction()

Signs an Ethereum transaction with EIP-155 replay protection.

```php
public function signTransaction(array $transaction, string $privateKey, int $chainId): string
```

**Parameters:**
- `$transaction` (array) - Transaction object with keys:
  - `nonce` (int|string) - Transaction nonce
  - `gasPrice` (int|string) - Gas price in wei
  - `gasLimit` (int|string) - Gas limit
  - `to` (string) - Recipient address (empty string for contract creation)
  - `value` (int|string) - Value in wei
  - `data` (string) - Transaction data (optional, default: '')
- `$privateKey` (string) - Private key (with or without 0x prefix)
- `$chainId` (int) - Chain ID for EIP-155 replay protection

**Returns:** `string` - RLP-encoded signed transaction (0x prefixed)

**Example:**
```php
$transaction = [
    'nonce' => 5,
    'gasPrice' => 20000000000, // 20 gwei
    'gasLimit' => 21000,
    'to' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
    'value' => 1000000000000000000, // 1 ETH
    'data' => ''
];

$signedTx = $signer->signTransaction(
    $transaction,
    '0x1234567890abcdef...',
    1 // Ethereum mainnet
);

// Returns: "0xf86c05..."
```

---

### SweeperService

**Namespace:** `Multicoin\TokenSweeper\Sweeper\SweeperService`

Main service for processing token sweeps.

#### Constructor

```php
public function __construct(
    protected WalletService $walletService,
    protected TransactionSignerService $signer
)
```

**Parameters:**
- `$walletService` - WalletService instance (auto-injected)
- `$signer` - TransactionSignerService instance (auto-injected)

---

#### processSweep()

Processes a complete token sweep from a deposit address.

```php
public function processSweep(
    string $depositAddress,
    string $tokenAddress,
    int $chainId
): bool
```

**Parameters:**
- `$depositAddress` (string) - The deposit address to sweep from
- `$tokenAddress` (string) - The ERC-20 token contract address
- `$chainId` (int) - The chain ID

**Returns:** `bool` - True on success, false on failure

**Process:**
1. Creates a PendingSweep record
2. Funds the deposit address with gas (if needed)
3. Waits for funding confirmation
4. Executes token transfer to hot wallet
5. Creates SweepLog record
6. Optionally sweeps remaining gas

**Events Dispatched:**
- `SweepStarted` - When sweep begins
- `SweepCompleted` - When sweep succeeds

**Example:**
```php
$success = $sweeperService->processSweep(
    '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
    '0xdAC17F958D2ee523a2206206994597C13D831ec7', // USDT
    1 // Ethereum
);

if ($success) {
    echo "Sweep completed successfully!";
}
```

---

### MonitorService

**Namespace:** `Multicoin\TokenSweeper\Sweeper\MonitorService`

Monitors blockchains for incoming token deposits and triggers sweeps.

#### Constructor

```php
public function __construct(
    protected SweeperService $sweeper,
    protected WalletService $walletService
)
```

**Parameters:**
- `$sweeper` - SweeperService instance (auto-injected)
- `$walletService` - WalletService instance (auto-injected)

---

#### startMonitoring()

Starts the continuous monitoring loop for all active chains.

```php
public function startMonitoring(): void
```

**Returns:** `void` - This method runs indefinitely

**Note:** This is a blocking operation that continuously monitors blockchains. Should be run in a dedicated process or supervisor.

**Example:**
```php
$monitorService->startMonitoring();
// Runs forever, checking for deposits every N seconds (configured)
```

---

#### monitorChain()

Monitors a single chain for new blocks and deposits.

```php
public function monitorChain(Chain $chain): void
```

**Parameters:**
- `$chain` (Chain) - The chain model to monitor

**Returns:** `void`

**Example:**
```php
$chain = Chain::where('chain_id', 1)->first();
$monitorService->monitorChain($chain);
```

---

#### checkExistingBalances()

Checks all deposit addresses for existing token balances and sweeps them.

```php
public function checkExistingBalances(): void
```

**Returns:** `void`

**Use Case:** Run once on startup to sweep any existing balances before monitoring begins.

**Example:**
```php
$monitorService->checkExistingBalances();
```

---

## Models API

### Chain Model

**Namespace:** `Multicoin\TokenSweeper\Models\Chain`

**Table:** `sweeper_chains`

Represents a blockchain network configuration.

#### Fillable Attributes

| Attribute | Type | Description |
|-----------|------|-------------|
| `chain_id` | integer | Blockchain chain ID (e.g., 1, 56, 137) |
| `name` | string | Chain name (e.g., "Ethereum", "BSC") |
| `rpc_url` | string | RPC endpoint URL |
| `master_wallet_address` | string | Master wallet for funding gas |
| `master_private_key_encrypted` | string | Encrypted master private key |
| `hot_wallet_address` | string | Hot wallet for receiving swept tokens |
| `native_symbol` | string | Native token symbol (e.g., "ETH", "BNB") |
| `gas_amount_wei` | string | Gas amount to send (in wei) |
| `gas_limit_token_transfer` | integer | Gas limit for token transfers |
| `is_active` | boolean | Whether chain is active |

#### Casts

```php
protected $casts = [
    'chain_id' => 'integer',
    'gas_limit_token_transfer' => 'integer',
    'is_active' => 'boolean',
];
```

#### Relationships

##### tokens()

```php
public function tokens(): HasMany
```

Returns all tokens configured for this chain.

**Example:**
```php
$chain = Chain::find(1);
$tokens = $chain->tokens;
```

##### depositAddresses()

```php
public function depositAddresses(): HasMany
```

Returns all deposit addresses on this chain.

##### pendingSweeps()

```php
public function pendingSweeps(): HasMany
```

Returns all pending sweeps on this chain.

#### Query Scopes

##### active()

```php
public function scopeActive($query)
```

Filters to only active chains.

**Example:**
```php
$activeChains = Chain::active()->get();
```

#### Usage Example

```php
use Multicoin\TokenSweeper\Models\Chain;

// Create a new chain
$chain = Chain::create([
    'chain_id' => 1,
    'name' => 'Ethereum',
    'rpc_url' => 'https://eth.llamarpc.com',
    'master_wallet_address' => '0x123...',
    'master_private_key_encrypted' => Crypt::encryptString('0xabc...'),
    'hot_wallet_address' => '0x456...',
    'native_symbol' => 'ETH',
    'gas_amount_wei' => '2000000000000000',
    'gas_limit_token_transfer' => 100000,
    'is_active' => true,
]);

// Query with relationships
$chain = Chain::with('tokens', 'depositAddresses')
    ->where('chain_id', 1)
    ->first();
```

---

### Token Model

**Namespace:** `Multicoin\TokenSweeper\Models\Token`

**Table:** `sweeper_tokens`

Represents an ERC-20 token configuration.

#### Fillable Attributes

| Attribute | Type | Description |
|-----------|------|-------------|
| `chain_id` | integer | Chain ID where token exists |
| `symbol` | string | Token symbol (e.g., "USDT") |
| `name` | string | Full token name |
| `contract_address` | string | Token contract address |
| `decimals` | integer | Token decimals (6, 8, 18, etc.) |
| `is_active` | boolean | Whether token monitoring is active |

#### Casts

```php
protected $casts = [
    'chain_id' => 'integer',
    'decimals' => 'integer',
    'is_active' => 'boolean',
];
```

#### Relationships

##### chain()

```php
public function chain(): BelongsTo
```

Returns the chain this token belongs to.

**Example:**
```php
$token = Token::find(1);
echo $token->chain->name; // "Ethereum"
```

#### Query Scopes

##### active()

```php
public function scopeActive($query)
```

Filters to only active tokens.

##### forChain()

```php
public function scopeForChain($query, int $chainId)
```

Filters tokens for a specific chain.

**Example:**
```php
// Get all active USDT tokens
$usdtTokens = Token::active()->where('symbol', 'USDT')->get();

// Get all tokens on Ethereum
$ethTokens = Token::forChain(1)->get();

// Combine scopes
$activeEthTokens = Token::active()->forChain(1)->get();
```

#### Usage Example

```php
use Multicoin\TokenSweeper\Models\Token;

// Create a new token
$token = Token::create([
    'chain_id' => 1,
    'symbol' => 'USDT',
    'name' => 'Tether USD',
    'contract_address' => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
    'decimals' => 6,
    'is_active' => true,
]);

// Query with chain
$token = Token::with('chain')
    ->where('contract_address', '0xdAC17F958...')
    ->first();

echo $token->symbol; // USDT
echo $token->chain->name; // Ethereum
```

---

### DepositAddress Model

**Namespace:** `Multicoin\TokenSweeper\Models\DepositAddress`

**Table:** `sweeper_deposit_addresses`

Represents a user's deposit address on a specific chain.

#### Fillable Attributes

| Attribute | Type | Description |
|-----------|------|-------------|
| `user_id` | integer | User ID |
| `chain_id` | integer | Chain ID |
| `address` | string | Ethereum address |
| `private_key_encrypted` | string | Encrypted private key |
| `last_sweep_at` | datetime | Last sweep timestamp |

#### Casts

```php
protected $casts = [
    'user_id' => 'integer',
    'chain_id' => 'integer',
    'last_sweep_at' => 'datetime',
];
```

#### Hidden Attributes

```php
protected $hidden = [
    'private_key_encrypted',
];
```

The private key is automatically hidden from JSON/array serialization.

#### Relationships

##### chain()

```php
public function chain(): BelongsTo
```

Returns the chain for this address.

##### pendingSweeps()

```php
public function pendingSweeps(): HasMany
```

Returns pending sweeps for this address.

#### Query Scopes

##### forUser()

```php
public function scopeForUser($query, int $userId)
```

Filters addresses for a specific user.

##### forChain()

```php
public function scopeForChain($query, int $chainId)
```

Filters addresses for a specific chain.

**Example:**
```php
// Get all addresses for user
$userAddresses = DepositAddress::forUser(123)->get();

// Get user's Ethereum address
$ethAddress = DepositAddress::forUser(123)->forChain(1)->first();

// Get all addresses on BSC
$bscAddresses = DepositAddress::forChain(56)->get();
```

#### Usage Example

```php
use Multicoin\TokenSweeper\Models\DepositAddress;

// Create deposit address
$depositAddress = DepositAddress::create([
    'user_id' => 123,
    'chain_id' => 1,
    'address' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
    'private_key_encrypted' => Crypt::encryptString('0x1234...'),
]);

// Query with relationships
$address = DepositAddress::with('chain', 'pendingSweeps')
    ->forUser(123)
    ->forChain(1)
    ->first();

echo $address->address;
echo $address->chain->name;
```

---

### PendingSweep Model

**Namespace:** `Multicoin\TokenSweeper\Models\PendingSweep`

**Table:** `sweeper_pending_sweeps`

Tracks the status of sweep operations.

#### Fillable Attributes

| Attribute | Type | Description |
|-----------|------|-------------|
| `deposit_address` | string | Address being swept |
| `chain_id` | integer | Chain ID |
| `token_address` | string | Token contract address |
| `token_symbol` | string | Token symbol |
| `amount` | string | Amount being swept |
| `status` | string | Status (pending, funding, sweeping, completed, failed) |
| `funding_tx_hash` | string | Funding transaction hash |
| `sweep_tx_hash` | string | Sweep transaction hash |
| `error_message` | string | Error message if failed |
| `retry_count` | integer | Number of retry attempts |

#### Casts

```php
protected $casts = [
    'chain_id' => 'integer',
    'retry_count' => 'integer',
];
```

#### Relationships

##### chain()

```php
public function chain(): BelongsTo
```

Returns the chain for this sweep.

##### depositAddressModel()

```php
public function depositAddressModel(): BelongsTo
```

Returns the DepositAddress model.

##### log()

```php
public function log(): HasOne
```

Returns the associated SweepLog.

#### Query Scopes

##### pending()

```php
public function scopePending($query)
```

Filters to pending sweeps.

##### failed()

```php
public function scopeFailed($query)
```

Filters to failed sweeps.

##### completed()

```php
public function scopeCompleted($query)
```

Filters to completed sweeps.

##### forChain()

```php
public function scopeForChain($query, int $chainId)
```

Filters sweeps for a specific chain.

**Example:**
```php
// Get all pending sweeps
$pending = PendingSweep::pending()->get();

// Get failed sweeps on Ethereum
$failed = PendingSweep::failed()->forChain(1)->get();

// Get recent completed sweeps
$completed = PendingSweep::completed()
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();
```

#### Methods

##### markAsCompleted()

```php
public function markAsCompleted(string $sweepTxHash): void
```

Marks the sweep as completed.

**Example:**
```php
$sweep->markAsCompleted('0x1234567890abcdef...');
```

##### markAsFailed()

```php
public function markAsFailed(string $errorMessage): void
```

Marks the sweep as failed with an error message.

**Example:**
```php
$sweep->markAsFailed('Insufficient gas');
```

##### incrementRetry()

```php
public function incrementRetry(): void
```

Increments the retry counter.

**Example:**
```php
$sweep->incrementRetry();
```

#### Usage Example

```php
use Multicoin\TokenSweeper\Models\PendingSweep;

// Create pending sweep
$sweep = PendingSweep::create([
    'deposit_address' => '0x742d35Cc...',
    'chain_id' => 1,
    'token_address' => '0xdAC17F958...',
    'token_symbol' => 'USDT',
    'amount' => '1000000',
    'status' => 'pending',
]);

// Update status
$sweep->update(['status' => 'funding']);
$sweep->update(['funding_tx_hash' => '0xabc...']);

// Mark completed
$sweep->markAsCompleted('0xdef...');

// Query with relationships
$sweep = PendingSweep::with('chain', 'log')
    ->find($id);
```

---

### SweepLog Model

**Namespace:** `Multicoin\TokenSweeper\Models\SweepLog`

**Table:** `sweeper_logs`

Permanent log of completed sweeps.

#### Fillable Attributes

| Attribute | Type | Description |
|-----------|------|-------------|
| `sweep_id` | integer | Reference to PendingSweep |
| `chain_id` | integer | Chain ID |
| `deposit_address` | string | Address swept from |
| `token_address` | string | Token contract address |
| `amount` | string | Amount swept |
| `funding_tx_hash` | string | Funding transaction hash |
| `sweep_tx_hash` | string | Sweep transaction hash |
| `gas_used` | string | Gas used |
| `status` | string | Final status |

#### Casts

```php
protected $casts = [
    'sweep_id' => 'integer',
    'chain_id' => 'integer',
];
```

#### Relationships

##### sweep()

```php
public function sweep(): BelongsTo
```

Returns the associated PendingSweep.

##### chain()

```php
public function chain(): BelongsTo
```

Returns the chain.

#### Query Scopes

##### forChain()

```php
public function scopeForChain($query, int $chainId)
```

Filters logs for a specific chain.

##### successful()

```php
public function scopeSuccessful($query)
```

Filters to successful sweeps only.

**Example:**
```php
// Get all successful sweeps on Ethereum
$logs = SweepLog::successful()->forChain(1)->get();

// Get recent sweep logs
$recent = SweepLog::with('chain')
    ->orderBy('created_at', 'desc')
    ->limit(50)
    ->get();
```

#### Usage Example

```php
use Multicoin\TokenSweeper\Models\SweepLog;

// Create log
$log = SweepLog::create([
    'sweep_id' => $sweep->id,
    'chain_id' => 1,
    'deposit_address' => '0x742d35Cc...',
    'token_address' => '0xdAC17F958...',
    'amount' => '1000000',
    'funding_tx_hash' => '0xabc...',
    'sweep_tx_hash' => '0xdef...',
    'status' => 'completed',
]);

// Query logs
$ethLogs = SweepLog::forChain(1)
    ->where('created_at', '>', now()->subDays(7))
    ->get();
```

---

## Jobs API

### FundDepositAddress Job

**Namespace:** `Multicoin\TokenSweeper\Jobs\FundDepositAddress`

**Queue:** Default queue

**Implements:** `ShouldQueue`

Funds a deposit address with native tokens for gas.

#### Properties

```php
public $tries = 3;       // Maximum retry attempts
public $timeout = 300;   // Timeout in seconds (5 minutes)
```

#### Constructor

```php
public function __construct(public PendingSweep $sweep)
```

**Parameters:**
- `$sweep` (PendingSweep) - The pending sweep model

#### Handle Method

```php
public function handle(
    WalletService $walletService,
    TransactionSignerService $signer
): void
```

**Process:**
1. Checks if address already has sufficient gas
2. If yes, dispatches SweepTokens job immediately
3. If no, sends native tokens from master wallet
4. Waits for funding confirmation
5. Dispatches SweepTokens job

**Throws:** Rethrows exceptions to trigger job retry

**Example:**
```php
use Multicoin\TokenSweeper\Jobs\FundDepositAddress;

$sweep = PendingSweep::find($id);
FundDepositAddress::dispatch($sweep);

// With delay
FundDepositAddress::dispatch($sweep)->delay(now()->addSeconds(30));

// On specific queue
FundDepositAddress::dispatch($sweep)->onQueue('high-priority');
```

---

### SweepTokens Job

**Namespace:** `Multicoin\TokenSweeper\Jobs\SweepTokens`

**Queue:** Default queue

**Implements:** `ShouldQueue`

Sweeps tokens from a deposit address to the hot wallet.

#### Properties

```php
public $tries = 3;       // Maximum retry attempts
public $timeout = 300;   // Timeout in seconds
```

#### Constructor

```php
public function __construct(public PendingSweep $sweep)
```

**Parameters:**
- `$sweep` (PendingSweep) - The pending sweep model

#### Handle Method

```php
public function handle(
    WalletService $walletService,
    TransactionSignerService $signer
): void
```

**Process:**
1. Updates sweep status to 'sweeping'
2. Gets private key for deposit address
3. Checks token balance
4. Builds ERC-20 transfer transaction
5. Signs and broadcasts transaction
6. Waits for confirmation
7. Marks sweep as completed
8. Creates SweepLog record
9. Dispatches SweepCompleted event

**Throws:** Rethrows exceptions to trigger job retry

**Example:**
```php
use Multicoin\TokenSweeper\Jobs\SweepTokens;

$sweep = PendingSweep::find($id);
SweepTokens::dispatch($sweep);

// Chain jobs
FundDepositAddress::dispatch($sweep)
    ->chain([
        new SweepTokens($sweep)
    ]);
```

---

### CheckPendingSweeps Job

**Namespace:** `Multicoin\TokenSweeper\Jobs\CheckPendingSweeps`

**Queue:** Default queue

**Implements:** `ShouldQueue`

Retries failed sweeps that haven't exceeded max retry attempts.

#### Constructor

```php
public function __construct()
```

No parameters required.

#### Handle Method

```php
public function handle(SweeperService $sweeper): void
```

**Process:**
1. Queries failed sweeps with retry_count < max_retries
2. Filters to sweeps updated more than N minutes ago (retry delay)
3. Retries each sweep using SweeperService

**Configuration:**
- `token-sweeper.monitoring.max_retry_attempts` - Max retries (default: 3)
- `token-sweeper.monitoring.retry_delay` - Minutes to wait between retries (default: 60)

**Example:**
```php
use Multicoin\TokenSweeper\Jobs\CheckPendingSweeps;

// Dispatch manually
CheckPendingSweeps::dispatch();

// Schedule in app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->job(new CheckPendingSweeps())->hourly();
}
```

**Scheduled Usage:**

Add to `app/Console/Kernel.php`:

```php
protected function schedule(Schedule $schedule)
{
    $schedule->job(new \Multicoin\TokenSweeper\Jobs\CheckPendingSweeps())
        ->everyFifteenMinutes()
        ->onOneServer();
}
```

---

## Events API

### DepositDetected Event

**Namespace:** `Multicoin\TokenSweeper\Events\DepositDetected`

**Traits:** `Dispatchable`, `SerializesModels`

Fired when a token deposit is detected on a deposit address.

#### Constructor

```php
public function __construct(
    public string $depositAddress,
    public string $tokenAddress,
    public int $chainId,
    public string $amount
)
```

**Properties:**
- `$depositAddress` - The deposit address that received tokens
- `$tokenAddress` - The token contract address
- `$chainId` - The chain ID
- `$amount` - The amount deposited (in smallest unit)

#### Usage Example

**Listening to the event:**

```php
// In EventServiceProvider
protected $listen = [
    \Multicoin\TokenSweeper\Events\DepositDetected::class => [
        \App\Listeners\NotifyUserOfDeposit::class,
        \App\Listeners\LogDeposit::class,
    ],
];
```

**Listener example:**

```php
namespace App\Listeners;

use Multicoin\TokenSweeper\Events\DepositDetected;

class NotifyUserOfDeposit
{
    public function handle(DepositDetected $event)
    {
        // Get user from deposit address
        $depositAddress = \Multicoin\TokenSweeper\Models\DepositAddress::where(
            'address',
            $event->depositAddress
        )->first();

        // Send notification
        $user = User::find($depositAddress->user_id);
        $user->notify(new DepositReceived(
            $event->tokenAddress,
            $event->amount,
            $event->chainId
        ));
    }
}
```

**Dispatching manually:**

```php
event(new DepositDetected(
    '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
    '0xdAC17F958D2ee523a2206206994597C13D831ec7',
    1,
    '1000000'
));
```

---

### SweepStarted Event

**Namespace:** `Multicoin\TokenSweeper\Events\SweepStarted`

**Traits:** `Dispatchable`, `SerializesModels`

Fired when a sweep operation begins.

#### Constructor

```php
public function __construct(public PendingSweep $sweep)
```

**Properties:**
- `$sweep` - The PendingSweep model instance

#### Usage Example

**Listener:**

```php
namespace App\Listeners;

use Multicoin\TokenSweeper\Events\SweepStarted;
use Illuminate\Support\Facades\Log;

class LogSweepStarted
{
    public function handle(SweepStarted $event)
    {
        Log::info('Sweep started', [
            'sweep_id' => $event->sweep->id,
            'address' => $event->sweep->deposit_address,
            'token' => $event->sweep->token_symbol,
            'chain_id' => $event->sweep->chain_id,
        ]);

        // Send real-time notification
        broadcast(new \App\Events\SweepProgressUpdated($event->sweep));
    }
}
```

---

### SweepCompleted Event

**Namespace:** `Multicoin\TokenSweeper\Events\SweepCompleted`

**Traits:** `Dispatchable`, `SerializesModels`

Fired when a sweep operation completes successfully.

#### Constructor

```php
public function __construct(public PendingSweep $sweep)
```

**Properties:**
- `$sweep` - The completed PendingSweep model instance

#### Usage Example

**Listener:**

```php
namespace App\Listeners;

use Multicoin\TokenSweeper\Events\SweepCompleted;

class CreditUserBalance
{
    public function handle(SweepCompleted $event)
    {
        $sweep = $event->sweep;

        // Get user from deposit address
        $depositAddress = \Multicoin\TokenSweeper\Models\DepositAddress::where(
            'address',
            $sweep->deposit_address
        )->first();

        // Credit user's balance
        $user = User::find($depositAddress->user_id);
        $user->creditBalance(
            $sweep->token_symbol,
            $sweep->amount,
            $sweep->sweep_tx_hash
        );

        // Send notification
        $user->notify(new TokensReceived(
            $sweep->token_symbol,
            $sweep->amount
        ));
    }
}
```

**Register in EventServiceProvider:**

```php
protected $listen = [
    \Multicoin\TokenSweeper\Events\SweepCompleted::class => [
        \App\Listeners\CreditUserBalance::class,
        \App\Listeners\SendSweepCompletedNotification::class,
    ],
];
```

---

## Commands API

### sweeper:install

Installs the Token Sweeper package.

**Signature:**
```bash
php artisan sweeper:install
```

**Description:** Install Token Sweeper package

**Options:** None

**Process:**
1. Publishes configuration file to `config/token-sweeper.php`
2. Runs database migrations
3. Publishes seeders (optional)

**Example:**
```bash
php artisan sweeper:install
```

**Output:**
```
Installing Token Sweeper...
Publishing configuration...
Running migrations...
✅ Token Sweeper installed successfully!

Next steps:
1. Configure your chains and tokens in config/token-sweeper.php
2. Run: php artisan sweeper:seed
3. Start monitoring: php artisan sweeper:monitor
```

---

### sweeper:seed

Seeds default chains and tokens from configuration.

**Signature:**
```bash
php artisan sweeper:seed
```

**Description:** Seed default chains and tokens

**Options:** None

**Process:**
1. Reads `token-sweeper.default_chains` configuration
2. Creates/updates chains using `updateOrCreate`
3. Reads `token-sweeper.default_tokens` configuration
4. Creates/updates tokens using `updateOrCreate`

**Example:**
```bash
php artisan sweeper:seed
```

**Output:**
```
Seeding chains and tokens...
✅ Seeded chain: Ethereum
✅ Seeded chain: BSC
✅ Seeded chain: Polygon
✅ Seeded token: USDT on chain 1
✅ Seeded token: USDC on chain 1
✅ Seeded token: USDT on chain 56

✅ Seeding completed!
```

**Note:** Safe to run multiple times - uses `updateOrCreate` to prevent duplicates.

---

### sweeper:generate-address

Generates a deposit address for a user on a specific chain.

**Signature:**
```bash
php artisan sweeper:generate-address {user_id} {chain_id}
```

**Arguments:**
- `user_id` (required) - The user ID
- `chain_id` (required) - The chain ID (1=Ethereum, 56=BSC, etc.)

**Options:** None

**Example:**
```bash
php artisan sweeper:generate-address 123 1
```

**Output:**
```
Generating deposit address for user 123 on chain 1...
✅ Deposit address created: 0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb
```

**Notes:**
- If address already exists for user/chain, returns existing address
- Private key is encrypted and stored in database

---

### sweeper:monitor

Starts the blockchain monitoring service.

**Signature:**
```bash
php artisan sweeper:monitor
```

**Description:** Monitor blockchain for token deposits

**Options:** None

**Process:**
1. Loads all active chains
2. Checks existing balances (one-time sweep)
3. Starts infinite monitoring loop
4. Polls each chain for new blocks
5. Detects Transfer events to deposit addresses
6. Automatically triggers sweeps

**Example:**
```bash
php artisan sweeper:monitor
```

**Output:**
```
🚀 Starting multi-chain token monitor...
Monitoring Ethereum (Chain ID: 1)
Monitoring BSC (Chain ID: 56)
Monitoring Polygon (Chain ID: 137)
Checking existing balances...
Found existing balance: 0x742d35Cc... has 100 USDT on Ethereum
Deposit detected on Ethereum
  Token: USDT
  To: 0x742d35Cc...
  Amount: 100
  TxHash: 0x1234...
Starting sweep...
```

**Production Usage:**

Use a process manager like Supervisor:

```ini
[program:token-sweeper-monitor]
command=php /path/to/artisan sweeper:monitor
directory=/path/to/project
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/path/to/logs/sweeper-monitor.log
```

---

### sweeper:sweep

Manually processes a token sweep.

**Signature:**
```bash
php artisan sweeper:sweep {address} {token} {chain_id}
```

**Arguments:**
- `address` (required) - The deposit address to sweep from
- `token` (required) - The token contract address
- `chain_id` (required) - The chain ID

**Options:** None

**Example:**
```bash
php artisan sweeper:sweep \
  0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb \
  0xdAC17F958D2ee523a2206206994597C13D831ec7 \
  1
```

**Output (Success):**
```
Processing sweep for 0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb...
✅ Sweep completed successfully!
```

**Output (Failure):**
```
Processing sweep for 0x742d35Cc...
❌ Sweep failed!
```

**Use Cases:**
- Manual intervention for stuck sweeps
- Testing sweep functionality
- Emergency sweeps

---

### sweeper:balance

Checks the balance of an address.

**Signature:**
```bash
php artisan sweeper:balance {address} {token?} {chain_id=1}
```

**Arguments:**
- `address` (required) - The wallet address to check
- `token` (optional) - Token contract address (if checking token balance)
- `chain_id` (optional, default: 1) - The chain ID

**Options:** None

**Examples:**

**Check native balance:**
```bash
php artisan sweeper:balance 0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb
```

**Output:**
```
Chain: Ethereum
Address: 0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb
Native Balance: 0.5 ETH
```

**Check token balance:**
```bash
php artisan sweeper:balance \
  0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb \
  0xdAC17F958D2ee523a2206206994597C13D831ec7 \
  1
```

**Output:**
```
Chain: Ethereum
Address: 0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb
Native Balance: 0.5 ETH
Token Balance: 1000000 (raw)
```

**Check balance on BSC:**
```bash
php artisan sweeper:balance 0x742d35Cc... "" 56
```

---

### sweeper:pending

Lists pending sweeps.

**Signature:**
```bash
php artisan sweeper:pending {--status=pending}
```

**Arguments:** None

**Options:**
- `--status` (optional, default: "pending") - Status filter (pending, failed, completed, funding, sweeping)

**Examples:**

**Check pending sweeps:**
```bash
php artisan sweeper:pending
```

**Check failed sweeps:**
```bash
php artisan sweeper:pending --status=failed
```

**Check completed sweeps:**
```bash
php artisan sweeper:pending --status=completed
```

**Output:**
```
Pending Sweeps (Status: pending):
+----+-----------+----------------+-------+---------+------------------+
| ID | Chain     | Address        | Token | Status  | Created          |
+----+-----------+----------------+-------+---------+------------------+
| 15 | Ethereum  | 0x742d35Cc...  | USDT  | pending | 5 minutes ago    |
| 14 | BSC       | 0x8f3Cf7ad...  | USDC  | pending | 12 minutes ago   |
+----+-----------+----------------+-------+---------+------------------+
```

**Use Cases:**
- Monitoring sweep queue
- Debugging stuck sweeps
- Checking failed sweeps for manual intervention

---

## Facades API

### TokenSweeper Facade

**Namespace:** `Multicoin\TokenSweeper\Facades\TokenSweeper`

**Facade For:** WalletService and SweeperService

Provides convenient static access to wallet and sweeper functionality.

#### Available Methods

```php
/**
 * Create a deposit address for a user
 * @param int $userId
 * @param int $chainId
 * @return string
 */
TokenSweeper::createDepositAddress(int $userId, int $chainId): string

/**
 * Get all deposit addresses for a user
 * @param int $userId
 * @return \Illuminate\Database\Eloquent\Collection
 */
TokenSweeper::getUserDepositAddresses(int $userId): Collection

/**
 * Process a token sweep
 * @param string $depositAddress
 * @param string $tokenAddress
 * @param int $chainId
 * @return bool
 */
TokenSweeper::processSweep(string $depositAddress, string $tokenAddress, int $chainId): bool

/**
 * Generate a new wallet
 * @return array
 */
TokenSweeper::generateWallet(): array

/**
 * Get private key for an address
 * @param string $address
 * @param int $chainId
 * @return string
 */
TokenSweeper::getPrivateKey(string $address, int $chainId): string
```

#### Usage Examples

```php
use Multicoin\TokenSweeper\Facades\TokenSweeper;

// Create deposit address
$address = TokenSweeper::createDepositAddress(123, 1);
// Returns: "0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb"

// Get user's addresses
$addresses = TokenSweeper::getUserDepositAddresses(123);
foreach ($addresses as $addr) {
    echo "{$addr->chain->name}: {$addr->address}\n";
}

// Process sweep
$success = TokenSweeper::processSweep(
    '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
    '0xdAC17F958D2ee523a2206206994597C13D831ec7',
    1
);

// Generate wallet
$wallet = TokenSweeper::generateWallet();
// [
//     'address' => '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
//     'privateKey' => '0x1234567890abcdef...'
// ]
```

**In Controllers:**

```php
namespace App\Http\Controllers;

use Multicoin\TokenSweeper\Facades\TokenSweeper;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function createDepositAddress(Request $request)
    {
        $address = TokenSweeper::createDepositAddress(
            auth()->id(),
            $request->chain_id
        );

        return response()->json([
            'success' => true,
            'address' => $address
        ]);
    }

    public function myAddresses()
    {
        $addresses = TokenSweeper::getUserDepositAddresses(auth()->id());

        return response()->json([
            'success' => true,
            'data' => $addresses
        ]);
    }
}
```

---

## HTTP API

### REST Endpoints

The package provides RESTful API endpoints through `TokenSweeperController`.

**Base URL:** `/api/token-sweeper`

**Routes File:** `src/routes/api.php`

---

#### GET /health

Health check endpoint.

**Response:**
```json
{
  "status": "ok",
  "timestamp": "2025-01-09T12:00:00Z"
}
```

**Example:**
```bash
curl http://localhost/api/token-sweeper/health
```

---

#### GET /chains

List all chains.

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "chain_id": 1,
      "name": "Ethereum",
      "native_symbol": "ETH",
      "is_active": true
    }
  ]
}
```

**Example:**
```bash
curl http://localhost/api/token-sweeper/chains
```

---

#### GET /tokens

List all active tokens.

**Query Parameters:**
- `chain_id` (optional) - Filter by chain ID

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "chain_id": 1,
      "symbol": "USDT",
      "name": "Tether USD",
      "contract_address": "0xdAC17F958D2ee523a2206206994597C13D831ec7",
      "decimals": 6,
      "is_active": true,
      "chain": {
        "chain_id": 1,
        "name": "Ethereum"
      }
    }
  ]
}
```

**Examples:**
```bash
# Get all tokens
curl http://localhost/api/token-sweeper/tokens

# Get Ethereum tokens only
curl http://localhost/api/token-sweeper/tokens?chain_id=1
```

---

#### POST /deposit-addresses

Create a deposit address for a user.

**Request Body:**
```json
{
  "user_id": 123,
  "chain_id": 1
}
```

**Validation:**
- `user_id` - required, integer
- `chain_id` - required, integer, must exist in sweeper_chains

**Response (201):**
```json
{
  "success": true,
  "data": {
    "user_id": 123,
    "chain_id": 1,
    "address": "0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb"
  }
}
```

**Example:**
```bash
curl -X POST http://localhost/api/token-sweeper/deposit-addresses \
  -H "Content-Type: application/json" \
  -d '{
    "user_id": 123,
    "chain_id": 1
  }'
```

---

#### GET /deposit-addresses/user

Get all deposit addresses for a user.

**Query Parameters:**
- `user_id` (required) - The user ID

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 123,
      "chain_id": 1,
      "address": "0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb",
      "last_sweep_at": null,
      "created_at": "2025-01-09T12:00:00Z",
      "chain": {
        "chain_id": 1,
        "name": "Ethereum",
        "native_symbol": "ETH"
      }
    }
  ]
}
```

**Example:**
```bash
curl "http://localhost/api/token-sweeper/deposit-addresses/user?user_id=123"
```

---

#### GET /sweeps/status

Get sweep status for a specific address.

**Query Parameters:**
- `address` (required) - The deposit address
- `chain_id` (required) - The chain ID

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 15,
      "deposit_address": "0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb",
      "chain_id": 1,
      "token_address": "0xdAC17F958D2ee523a2206206994597C13D831ec7",
      "token_symbol": "USDT",
      "amount": "1000000",
      "status": "completed",
      "funding_tx_hash": "0xabc...",
      "sweep_tx_hash": "0xdef...",
      "error_message": null,
      "retry_count": 0,
      "created_at": "2025-01-09T12:00:00Z",
      "chain": {
        "chain_id": 1,
        "name": "Ethereum"
      }
    }
  ]
}
```

**Example:**
```bash
curl "http://localhost/api/token-sweeper/sweeps/status?address=0x742d35Cc...&chain_id=1"
```

---

#### GET /sweeps/pending

List pending sweeps.

**Query Parameters:**
- `status` (optional, default: "pending") - Status filter
- `chain_id` (optional) - Chain filter

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 15,
      "deposit_address": "0x742d35Cc...",
      "chain_id": 1,
      "token_symbol": "USDT",
      "status": "pending",
      "created_at": "2025-01-09T12:00:00Z",
      "chain": {
        "chain_id": 1,
        "name": "Ethereum"
      }
    }
  ]
}
```

**Examples:**
```bash
# Get pending sweeps
curl "http://localhost/api/token-sweeper/sweeps/pending"

# Get failed sweeps on Ethereum
curl "http://localhost/api/token-sweeper/sweeps/pending?status=failed&chain_id=1"
```

---

#### GET /sweeps/logs

Get sweep logs.

**Query Parameters:**
- `limit` (optional, default: 50, max: 100) - Number of logs
- `chain_id` (optional) - Chain filter

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "sweep_id": 15,
      "chain_id": 1,
      "deposit_address": "0x742d35Cc...",
      "token_address": "0xdAC17F958...",
      "amount": "1000000",
      "funding_tx_hash": "0xabc...",
      "sweep_tx_hash": "0xdef...",
      "gas_used": null,
      "status": "completed",
      "created_at": "2025-01-09T12:00:00Z",
      "chain": {
        "chain_id": 1,
        "name": "Ethereum"
      }
    }
  ]
}
```

**Examples:**
```bash
# Get last 50 logs
curl "http://localhost/api/token-sweeper/sweeps/logs"

# Get last 10 logs for Ethereum
curl "http://localhost/api/token-sweeper/sweeps/logs?limit=10&chain_id=1"
```

---

#### POST /sweeps/process

Manually trigger a sweep.

**Request Body:**
```json
{
  "deposit_address": "0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb",
  "token_address": "0xdAC17F958D2ee523a2206206994597C13D831ec7",
  "chain_id": 1
}
```

**Validation:**
- `deposit_address` - required, string
- `token_address` - required, string
- `chain_id` - required, integer

**Response (200):**
```json
{
  "success": true,
  "message": "Sweep initiated successfully"
}
```

**Response (500):**
```json
{
  "success": false,
  "message": "Sweep failed"
}
```

**Example:**
```bash
curl -X POST http://localhost/api/token-sweeper/sweeps/process \
  -H "Content-Type: application/json" \
  -d '{
    "deposit_address": "0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb",
    "token_address": "0xdAC17F958D2ee523a2206206994597C13D831ec7",
    "chain_id": 1
  }'
```

---

## Configuration Reference

**File:** `config/token-sweeper.php`

### Monitoring Configuration

```php
'monitoring' => [
    'check_interval' => env('SWEEPER_CHECK_INTERVAL', 5),
    'block_confirmations' => env('SWEEPER_CONFIRMATIONS', 1),
    'max_retry_attempts' => env('SWEEPER_MAX_RETRIES', 3),
    'retry_delay' => env('SWEEPER_RETRY_DELAY', 60),
],
```

**Options:**

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `check_interval` | int | 5 | Seconds between blockchain polling |
| `block_confirmations` | int | 1 | Required confirmations (not currently used) |
| `max_retry_attempts` | int | 3 | Max retry attempts for failed sweeps |
| `retry_delay` | int | 60 | Seconds to wait before retrying failed sweeps |

**Environment Variables:**
```env
SWEEPER_CHECK_INTERVAL=5
SWEEPER_CONFIRMATIONS=1
SWEEPER_MAX_RETRIES=3
SWEEPER_RETRY_DELAY=60
```

---

### RPC URLs Configuration

```php
'rpc_urls' => [
    1 => env('ETH_RPC_URL', 'https://eth.nownodes.io/' . env('NOWNODES_API_KEY')),
    56 => env('BSC_RPC_URL', 'https://bsc.nownodes.io/' . env('NOWNODES_API_KEY')),
    137 => env('POLYGON_RPC_URL', 'https://matic.nownodes.io/' . env('NOWNODES_API_KEY')),
    42161 => env('ARB_RPC_URL', 'https://arb.nownodes.io/' . env('NOWNODES_API_KEY')),
    10 => env('OP_RPC_URL', 'https://op.nownodes.io/' . env('NOWNODES_API_KEY')),
],
```

**Chain IDs:**
- `1` - Ethereum Mainnet
- `56` - BSC (Binance Smart Chain)
- `137` - Polygon
- `42161` - Arbitrum
- `10` - Optimism

**Environment Variables:**
```env
ETH_RPC_URL=https://eth.llamarpc.com
BSC_RPC_URL=https://bsc-dataseed1.binance.org
POLYGON_RPC_URL=https://polygon-rpc.com
ARB_RPC_URL=https://arb1.arbitrum.io/rpc
OP_RPC_URL=https://mainnet.optimism.io
```

---

### Default Chains Configuration

```php
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
    // ... more chains
],
```

**Chain Configuration Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `chain_id` | int | Blockchain chain ID |
| `name` | string | Chain name for display |
| `rpc_url` | string | RPC endpoint URL |
| `master_wallet_address` | string | Address to fund gas from |
| `master_private_key_encrypted` | string | Encrypted private key |
| `hot_wallet_address` | string | Address to sweep tokens to |
| `native_symbol` | string | Native token symbol |
| `gas_amount_wei` | string | Gas amount to send (wei) |
| `gas_limit_token_transfer` | int | Gas limit for ERC-20 transfers |

**Environment Variables:**
```env
# Ethereum
ETH_MASTER_WALLET=0x123...
ETH_MASTER_KEY_ENCRYPTED=encrypted_key_here
ETH_HOT_WALLET=0x456...

# BSC
BSC_MASTER_WALLET=0x789...
BSC_MASTER_KEY_ENCRYPTED=encrypted_key_here
BSC_HOT_WALLET=0xabc...
```

---

### Default Tokens Configuration

```php
'default_tokens' => [
    // Ethereum
    [
        'chain_id' => 1,
        'symbol' => 'USDT',
        'name' => 'Tether USD',
        'contract_address' => '0xdAC17F958D2ee523a2206206994597C13D831ec7',
        'decimals' => 6
    ],
    // ... more tokens
],
```

**Token Configuration Fields:**

| Field | Type | Description |
|-------|------|-------------|
| `chain_id` | int | Chain where token exists |
| `symbol` | string | Token symbol (e.g., USDT) |
| `name` | string | Full token name |
| `contract_address` | string | Token contract address |
| `decimals` | int | Token decimals (6, 8, 18, etc.) |

**Pre-configured Tokens:**

**Ethereum (1):**
- USDT: `0xdAC17F958D2ee523a2206206994597C13D831ec7` (6 decimals)
- USDC: `0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48` (6 decimals)
- DAI: `0x6B175474E89094C44Da98b954EedeAC495271d0F` (18 decimals)

**BSC (56):**
- USDT: `0x55d398326f99059fF775485246999027B3197955` (18 decimals)
- USDC: `0x8AC76a51cc950d9822D68b83fE1Ad97B32Cd580d` (18 decimals)

**Polygon (137):**
- USDT: `0xc2132D05D31c914a87C6611C10748AEb04B58e8F` (6 decimals)
- USDC: `0x2791Bca1f2de4661ED88A30C99A7a9449Aa84174` (6 decimals)

---

## Complete Example Usage

### Setup Flow

```php
// 1. Install package
php artisan sweeper:install

// 2. Configure .env
ETH_RPC_URL=https://eth.llamarpc.com
ETH_MASTER_WALLET=0x123...
ETH_MASTER_KEY_ENCRYPTED=...
ETH_HOT_WALLET=0x456...

// 3. Seed chains and tokens
php artisan sweeper:seed

// 4. Generate deposit address for user
$address = TokenSweeper::createDepositAddress(123, 1);

// 5. Start monitoring (in separate process)
php artisan sweeper:monitor
```

### Integration Example

```php
namespace App\Http\Controllers;

use Multicoin\TokenSweeper\Facades\TokenSweeper;
use Multicoin\TokenSweeper\Models\{Chain, Token};
use Illuminate\Http\Request;

class CryptoWalletController extends Controller
{
    public function getDepositAddress(Request $request)
    {
        $validated = $request->validate([
            'chain_id' => 'required|integer|exists:sweeper_chains,chain_id'
        ]);

        $address = TokenSweeper::createDepositAddress(
            auth()->id(),
            $validated['chain_id']
        );

        $chain = Chain::where('chain_id', $validated['chain_id'])->first();
        $tokens = Token::active()->forChain($validated['chain_id'])->get();

        return response()->json([
            'success' => true,
            'data' => [
                'address' => $address,
                'chain' => $chain->name,
                'supported_tokens' => $tokens->pluck('symbol')
            ]
        ]);
    }

    public function getTransactionHistory(Request $request)
    {
        $addresses = TokenSweeper::getUserDepositAddresses(auth()->id());

        $sweeps = \Multicoin\TokenSweeper\Models\PendingSweep::query()
            ->whereIn('deposit_address', $addresses->pluck('address'))
            ->with('chain')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $sweeps
        ]);
    }
}
```

### Event Listener Example

```php
namespace App\Listeners;

use Multicoin\TokenSweeper\Events\SweepCompleted;
use App\Models\User;
use App\Models\Transaction;

class ProcessCompletedSweep
{
    public function handle(SweepCompleted $event)
    {
        $sweep = $event->sweep;

        // Get user from deposit address
        $depositAddress = \Multicoin\TokenSweeper\Models\DepositAddress::where(
            'address',
            $sweep->deposit_address
        )->first();

        if (!$depositAddress) {
            return;
        }

        // Create transaction record
        Transaction::create([
            'user_id' => $depositAddress->user_id,
            'type' => 'deposit',
            'token_symbol' => $sweep->token_symbol,
            'amount' => $sweep->amount,
            'chain_id' => $sweep->chain_id,
            'tx_hash' => $sweep->sweep_tx_hash,
            'status' => 'completed'
        ]);

        // Credit user balance
        $user = User::find($depositAddress->user_id);
        $user->creditBalance($sweep->token_symbol, $sweep->amount);

        // Send notification
        $user->notify(new \App\Notifications\DepositReceived($sweep));
    }
}
```

---

## Error Handling

### Common Exceptions

**RPC Errors:**
```php
try {
    $balance = $web3->getBalance($address);
} catch (\Exception $e) {
    // Handle RPC errors
    Log::error('RPC Error: ' . $e->getMessage());
}
```

**Model Not Found:**
```php
try {
    $privateKey = $walletService->getPrivateKey($address, $chainId);
} catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
    // Address not found in database
    return response()->json(['error' => 'Address not found'], 404);
}
```

**Sweep Failures:**
```php
$success = $sweeperService->processSweep($address, $token, $chainId);
if (!$success) {
    // Check PendingSweep for error details
    $sweep = PendingSweep::where('deposit_address', $address)
        ->latest()
        ->first();

    Log::error('Sweep failed', [
        'error' => $sweep->error_message,
        'retry_count' => $sweep->retry_count
    ]);
}
```

---

## Best Practices

1. **Use Queues:** Always use queued jobs in production for sweep operations
2. **Monitor Logs:** Set up log monitoring for sweep failures
3. **Secure Keys:** Never expose private keys or encrypted keys in logs/responses
4. **Use Events:** Listen to events for business logic (crediting balances, notifications)
5. **Test on Testnets:** Always test on testnets (Sepolia, BSC Testnet) before mainnet
6. **Set Up Supervisor:** Use process managers to keep monitor running
7. **Rate Limiting:** Implement rate limiting on API endpoints
8. **Backup Keys:** Securely backup master wallet private keys

---

**Package Version:** 1.0.0
**Last Updated:** January 2025
**License:** MIT
