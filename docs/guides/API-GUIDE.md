# Token Sweeper API Usage Guide

Complete guide with code examples for integrating the Token Sweeper API into your application.

## Table of Contents

1. [Quick Start](#quick-start)
2. [Authentication](#authentication)
3. [API Endpoints](#api-endpoints)
4. [Code Examples](#code-examples)
5. [Error Handling](#error-handling)
6. [Best Practices](#best-practices)

## Quick Start

### Laravel Integration

```php
<?php

use Multicoin\TokenSweeper\Facades\TokenSweeper;
use Multicoin\TokenSweeper\Models\Chain;

// Generate a deposit address for a user
$address = TokenSweeper::generateDepositAddress(
    userId: 12345,
    chainId: 1 // Ethereum mainnet
);

echo "Deposit Address: {$address}\n";
```

### Node.js Integration

```javascript
const axios = require('axios');

const API_BASE_URL = 'http://localhost:3000/api';

async function generateDepositAddress(userId) {
    const response = await axios.post(`${API_BASE_URL}/deposit-address`, {
        userId: userId
    });

    return response.data.data.address;
}

// Usage
const address = await generateDepositAddress(12345);
console.log(`Deposit Address: ${address}`);
```

## Authentication

### Laravel (Built-in)

The Laravel API uses Laravel's authentication system. Include the authentication token in requests:

```php
// Using Guzzle HTTP client
$client = new \GuzzleHttp\Client();

$response = $client->get('https://api.example.com/api/sweeper/chains', [
    'headers' => [
        'Authorization' => 'Bearer YOUR_API_TOKEN',
        'Accept' => 'application/json',
    ]
]);

$chains = json_decode($response->getBody(), true);
```

### Node.js

```javascript
const axios = require('axios');

// Configure axios with authentication
const apiClient = axios.create({
    baseURL: 'https://api.example.com/api/sweeper',
    headers: {
        'Authorization': 'Bearer YOUR_API_TOKEN',
        'Content-Type': 'application/json'
    }
});

// Use the authenticated client
const response = await apiClient.get('/chains');
console.log(response.data);
```

### cURL

```bash
curl -X GET https://api.example.com/api/sweeper/chains \
  -H "Authorization: Bearer YOUR_API_TOKEN" \
  -H "Accept: application/json"
```

## API Endpoints

### Health Check

Check if the API service is running.

**Endpoint:** `GET /health`

**Example:**

```bash
curl -X GET http://localhost:8000/api/sweeper/health
```

**Response:**

```json
{
    "status": "ok",
    "timestamp": "2025-01-09T10:30:00Z"
}
```

### List Supported Chains

Get all supported blockchain networks.

**Endpoint:** `GET /chains`

**PHP Example:**

```php
use Multicoin\TokenSweeper\Models\Chain;

$chains = Chain::where('is_active', true)->get();

foreach ($chains as $chain) {
    echo "{$chain->name} (Chain ID: {$chain->chain_id})\n";
}
```

**JavaScript Example:**

```javascript
const response = await apiClient.get('/chains');

response.data.data.forEach(chain => {
    console.log(`${chain.name} (Chain ID: ${chain.chain_id})`);
});
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "chain_id": 1,
            "name": "Ethereum Mainnet",
            "native_symbol": "ETH",
            "is_active": true
        },
        {
            "id": 2,
            "chain_id": 56,
            "name": "Binance Smart Chain",
            "native_symbol": "BNB",
            "is_active": true
        }
    ]
}
```

### List Supported Tokens

Get all supported ERC-20 tokens, optionally filtered by chain.

**Endpoint:** `GET /tokens?chain_id={chainId}`

**PHP Example:**

```php
use Multicoin\TokenSweeper\Models\Token;

// Get all tokens
$allTokens = Token::where('is_active', true)->get();

// Get tokens for Ethereum only
$ethTokens = Token::forChain(1)->get();

foreach ($ethTokens as $token) {
    echo "{$token->symbol} - {$token->name}\n";
    echo "Contract: {$token->contract_address}\n\n";
}
```

**JavaScript Example:**

```javascript
// Get all tokens
const allTokens = await apiClient.get('/tokens');

// Get Ethereum tokens only
const ethTokens = await apiClient.get('/tokens?chain_id=1');

ethTokens.data.data.forEach(token => {
    console.log(`${token.symbol} - ${token.name}`);
    console.log(`Contract: ${token.contract_address}\n`);
});
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "chain_id": 1,
            "contract_address": "0xdAC17F958D2ee523a2206206994597C13D831ec7",
            "symbol": "USDT",
            "name": "Tether USD",
            "decimals": 6,
            "is_active": true,
            "chain": {
                "chain_id": 1,
                "name": "Ethereum Mainnet"
            }
        }
    ]
}
```

### Generate Deposit Address

Create a new deposit address for a user on a specific blockchain.

**Endpoint:** `POST /deposit-address`

**Request Body:**

```json
{
    "user_id": 12345,
    "chain_id": 1
}
```

**PHP Example:**

```php
use Multicoin\TokenSweeper\Services\WalletService;

$walletService = app(WalletService::class);

$address = $walletService->createDepositAddress(
    userId: 12345,
    chainId: 1
);

echo "Deposit ETH/ERC-20 tokens to: {$address}\n";
```

**JavaScript Example:**

```javascript
async function createDepositAddress(userId, chainId) {
    const response = await apiClient.post('/deposit-address', {
        user_id: userId,
        chain_id: chainId
    });

    return response.data.data;
}

const depositInfo = await createDepositAddress(12345, 1);
console.log(`Deposit address: ${depositInfo.address}`);
```

**Python Example:**

```python
import requests

def create_deposit_address(user_id, chain_id):
    url = "https://api.example.com/api/sweeper/deposit-address"
    headers = {
        "Authorization": "Bearer YOUR_API_TOKEN",
        "Content-Type": "application/json"
    }
    payload = {
        "user_id": user_id,
        "chain_id": chain_id
    }

    response = requests.post(url, json=payload, headers=headers)
    return response.json()

deposit_info = create_deposit_address(12345, 1)
print(f"Deposit address: {deposit_info['data']['address']}")
```

**Response:**

```json
{
    "success": true,
    "data": {
        "user_id": 12345,
        "chain_id": 1,
        "address": "0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb"
    }
}
```

### Get User Deposit Addresses

Retrieve all deposit addresses for a specific user.

**Endpoint:** `GET /user-addresses?user_id={userId}`

**PHP Example:**

```php
use Multicoin\TokenSweeper\Services\WalletService;

$walletService = app(WalletService::class);
$addresses = $walletService->getUserDepositAddresses(12345);

foreach ($addresses as $address) {
    echo "Chain: {$address['chain_name']}\n";
    echo "Address: {$address['address']}\n\n";
}
```

**JavaScript Example:**

```javascript
async function getUserAddresses(userId) {
    const response = await apiClient.get('/user-addresses', {
        params: { user_id: userId }
    });

    return response.data.data;
}

const addresses = await getUserAddresses(12345);

addresses.forEach(addr => {
    console.log(`Chain: ${addr.chain_name}`);
    console.log(`Address: ${addr.address}\n`);
});
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "user_id": 12345,
            "chain_id": 1,
            "address": "0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb",
            "derivation_index": 42,
            "created_at": "2025-01-09T10:30:00Z"
        },
        {
            "id": 2,
            "user_id": 12345,
            "chain_id": 56,
            "address": "0x8f3Cf7ad23Cd3CaDbD9735AFf958023239c6A063",
            "derivation_index": 43,
            "created_at": "2025-01-09T11:15:00Z"
        }
    ]
}
```

### Check Sweep Status

Get the sweep status for a specific deposit address.

**Endpoint:** `GET /sweep-status?address={address}&chain_id={chainId}`

**PHP Example:**

```php
use Multicoin\TokenSweeper\Models\PendingSweep;

$sweeps = PendingSweep::where('deposit_address', '0x742d35Cc...')
    ->where('chain_id', 1)
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

foreach ($sweeps as $sweep) {
    echo "Status: {$sweep->status}\n";
    echo "Amount: {$sweep->amount}\n";
    echo "TX Hash: {$sweep->sweep_tx_hash}\n\n";
}
```

**JavaScript Example:**

```javascript
async function getSweepStatus(address, chainId) {
    const response = await apiClient.get('/sweep-status', {
        params: {
            address: address,
            chain_id: chainId
        }
    });

    return response.data.data;
}

const sweeps = await getSweepStatus('0x742d35Cc...', 1);

sweeps.forEach(sweep => {
    console.log(`Status: ${sweep.status}`);
    console.log(`Amount: ${sweep.amount}`);
    console.log(`TX Hash: ${sweep.sweep_tx_hash}\n`);
});
```

**Response:**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "deposit_address": "0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb",
            "token_address": "0xdAC17F958D2ee523a2206206994597C13D831ec7",
            "chain_id": 1,
            "amount": "1000000",
            "status": "completed",
            "funding_tx_hash": "0x1234567890abcdef...",
            "sweep_tx_hash": "0xabcdef1234567890...",
            "retry_count": 0,
            "error_message": null,
            "created_at": "2025-01-09T10:30:00Z",
            "updated_at": "2025-01-09T10:35:00Z"
        }
    ]
}
```

### Get Pending Sweeps

List all pending sweeps with optional filtering.

**Endpoint:** `GET /pending-sweeps?status={status}&chain_id={chainId}`

**Status Values:**
- `pending` - Waiting to be processed
- `funding` - Funding address with gas
- `sweeping` - Executing token transfer
- `completed` - Successfully completed
- `failed` - Failed (check error_message)

**PHP Example:**

```php
use Multicoin\TokenSweeper\Models\PendingSweep;

// Get all pending sweeps
$pending = PendingSweep::where('status', 'pending')
    ->orderBy('created_at', 'desc')
    ->limit(50)
    ->get();

// Get failed sweeps for Ethereum
$failed = PendingSweep::where('status', 'failed')
    ->forChain(1)
    ->get();

foreach ($failed as $sweep) {
    echo "Address: {$sweep->deposit_address}\n";
    echo "Error: {$sweep->error_message}\n\n";
}
```

**JavaScript Example:**

```javascript
async function getPendingSweeps(status = 'pending', chainId = null) {
    const params = { status };
    if (chainId) params.chain_id = chainId;

    const response = await apiClient.get('/pending-sweeps', { params });
    return response.data.data;
}

// Get all pending sweeps
const pending = await getPendingSweeps('pending');

// Get failed sweeps on Ethereum
const failed = await getPendingSweeps('failed', 1);

failed.forEach(sweep => {
    console.log(`Address: ${sweep.deposit_address}`);
    console.log(`Error: ${sweep.error_message}\n`);
});
```

### Get Sweep History

Retrieve historical sweep logs.

**Endpoint:** `GET /sweep-logs?limit={limit}&chain_id={chainId}`

**PHP Example:**

```php
use Multicoin\TokenSweeper\Models\SweepLog;

$logs = SweepLog::orderBy('created_at', 'desc')
    ->limit(50)
    ->get();

foreach ($logs as $log) {
    echo "Status: {$log->status}\n";
    echo "Amount: {$log->amount}\n";
    echo "Gas Used: {$log->gas_used}\n";
    echo "Date: {$log->created_at}\n\n";
}
```

**JavaScript Example:**

```javascript
async function getSweepLogs(limit = 50, chainId = null) {
    const params = { limit };
    if (chainId) params.chain_id = chainId;

    const response = await apiClient.get('/sweep-logs', { params });
    return response.data.data;
}

const logs = await getSweepLogs(50);

logs.forEach(log => {
    console.log(`Status: ${log.status}`);
    console.log(`Amount: ${log.amount}`);
    console.log(`Gas Used: ${log.gas_used}`);
    console.log(`Date: ${log.created_at}\n`);
});
```

### Manually Trigger Sweep

Manually initiate a token sweep (normally automatic).

**Endpoint:** `POST /process-sweep`

**Request Body:**

```json
{
    "deposit_address": "0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb",
    "token_address": "0xdAC17F958D2ee523a2206206994597C13D831ec7",
    "chain_id": 1
}
```

**PHP Example:**

```php
use Multicoin\TokenSweeper\Services\SweeperService;

$sweeperService = app(SweeperService::class);

$result = $sweeperService->processSweep(
    depositAddress: '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
    tokenAddress: '0xdAC17F958D2ee523a2206206994597C13D831ec7',
    chainId: 1
);

if ($result) {
    echo "Sweep initiated successfully\n";
} else {
    echo "Sweep failed\n";
}
```

**JavaScript Example:**

```javascript
async function processSweep(depositAddress, tokenAddress, chainId) {
    const response = await apiClient.post('/process-sweep', {
        deposit_address: depositAddress,
        token_address: tokenAddress,
        chain_id: chainId
    });

    return response.data;
}

const result = await processSweep(
    '0x742d35Cc6634C0532925a3b844Bc9e7595f0bEb',
    '0xdAC17F958D2ee523a2206206994597C13D831ec7',
    1
);

console.log(result.message);
```

## Code Examples

### Complete Integration Example (PHP Laravel)

```php
<?php

namespace App\Services;

use Multicoin\TokenSweeper\Facades\TokenSweeper;
use Multicoin\TokenSweeper\Models\{Chain, Token, PendingSweep};
use Multicoin\TokenSweeper\Services\WalletService;

class UserDepositService
{
    public function __construct(
        private WalletService $walletService
    ) {}

    /**
     * Create deposit addresses for a new user on all active chains
     */
    public function setupUserDeposits(int $userId): array
    {
        $addresses = [];
        $chains = Chain::where('is_active', true)->get();

        foreach ($chains as $chain) {
            $address = $this->walletService->createDepositAddress(
                $userId,
                $chain->chain_id
            );

            $addresses[$chain->name] = $address;
        }

        return $addresses;
    }

    /**
     * Get user's pending deposits across all chains
     */
    public function getPendingDeposits(int $userId): array
    {
        $userAddresses = $this->walletService->getUserDepositAddresses($userId);
        $deposits = [];

        foreach ($userAddresses as $addressInfo) {
            $sweeps = PendingSweep::where('deposit_address', $addressInfo['address'])
                ->where('status', 'pending')
                ->get();

            if ($sweeps->isNotEmpty()) {
                $deposits[] = [
                    'chain' => $addressInfo['chain_name'],
                    'address' => $addressInfo['address'],
                    'sweeps' => $sweeps->toArray()
                ];
            }
        }

        return $deposits;
    }

    /**
     * Get user's deposit history
     */
    public function getDepositHistory(int $userId, int $days = 30): array
    {
        $userAddresses = $this->walletService->getUserDepositAddresses($userId);
        $addressList = array_column($userAddresses, 'address');

        return \Multicoin\TokenSweeper\Models\SweepLog::whereIn('deposit_address', $addressList)
            ->where('created_at', '>=', now()->subDays($days))
            ->orderBy('created_at', 'desc')
            ->get()
            ->toArray();
    }
}
```

### Complete Integration Example (Node.js)

```javascript
const axios = require('axios');

class TokenSweeperClient {
    constructor(apiUrl, apiToken) {
        this.client = axios.create({
            baseURL: apiUrl,
            headers: {
                'Authorization': `Bearer ${apiToken}`,
                'Content-Type': 'application/json'
            }
        });
    }

    /**
     * Setup deposit addresses for a new user on all chains
     */
    async setupUserDeposits(userId) {
        // Get all active chains
        const chainsResponse = await this.client.get('/chains');
        const chains = chainsResponse.data.data;

        const addresses = {};

        for (const chain of chains) {
            if (chain.is_active) {
                const response = await this.client.post('/deposit-address', {
                    user_id: userId,
                    chain_id: chain.chain_id
                });

                addresses[chain.name] = response.data.data.address;
            }
        }

        return addresses;
    }

    /**
     * Get user's pending deposits
     */
    async getPendingDeposits(userId) {
        const addressesResponse = await this.client.get('/user-addresses', {
            params: { user_id: userId }
        });

        const userAddresses = addressesResponse.data.data;
        const deposits = [];

        for (const addressInfo of userAddresses) {
            const sweepsResponse = await this.client.get('/sweep-status', {
                params: {
                    address: addressInfo.address,
                    chain_id: addressInfo.chain_id
                }
            });

            const sweeps = sweepsResponse.data.data.filter(s => s.status === 'pending');

            if (sweeps.length > 0) {
                deposits.push({
                    chain: addressInfo.chain_name,
                    address: addressInfo.address,
                    sweeps: sweeps
                });
            }
        }

        return deposits;
    }

    /**
     * Get user's deposit history
     */
    async getDepositHistory(userId, limit = 50) {
        const addressesResponse = await this.client.get('/user-addresses', {
            params: { user_id: userId }
        });

        const userAddresses = addressesResponse.data.data;
        const history = [];

        for (const addressInfo of userAddresses) {
            const logsResponse = await this.client.get('/sweep-logs', {
                params: {
                    chain_id: addressInfo.chain_id,
                    limit: limit
                }
            });

            const logs = logsResponse.data.data.filter(
                log => log.deposit_address === addressInfo.address
            );

            history.push(...logs);
        }

        // Sort by date descending
        return history.sort((a, b) =>
            new Date(b.created_at) - new Date(a.created_at)
        );
    }
}

// Usage
const client = new TokenSweeperClient(
    'https://api.example.com/api/sweeper',
    'YOUR_API_TOKEN'
);

// Setup deposits for new user
const addresses = await client.setupUserDeposits(12345);
console.log('Deposit addresses:', addresses);

// Check pending deposits
const pending = await client.getPendingDeposits(12345);
console.log('Pending deposits:', pending);

// Get history
const history = await client.getDepositHistory(12345);
console.log('Deposit history:', history);
```

## Error Handling

### Common Error Responses

**400 Bad Request:**

```json
{
    "success": false,
    "error": "Invalid request parameters"
}
```

**422 Validation Error:**

```json
{
    "success": false,
    "message": "The given data was invalid.",
    "errors": {
        "user_id": ["The user id field is required."],
        "chain_id": ["The selected chain id is invalid."]
    }
}
```

**500 Internal Server Error:**

```json
{
    "success": false,
    "message": "Sweep failed",
    "error": "Insufficient gas balance"
}
```

### Error Handling Example (PHP)

```php
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;

try {
    $response = $client->post('/deposit-address', [
        'json' => [
            'user_id' => 12345,
            'chain_id' => 1
        ]
    ]);

    $data = json_decode($response->getBody(), true);

} catch (ClientException $e) {
    // 4xx errors (validation, bad request)
    $response = $e->getResponse();
    $error = json_decode($response->getBody(), true);

    if ($response->getStatusCode() === 422) {
        // Validation errors
        foreach ($error['errors'] as $field => $messages) {
            echo "{$field}: " . implode(', ', $messages) . "\n";
        }
    } else {
        echo "Error: {$error['error']}\n";
    }

} catch (ServerException $e) {
    // 5xx errors
    echo "Server error occurred\n";
}
```

### Error Handling Example (JavaScript)

```javascript
try {
    const response = await apiClient.post('/deposit-address', {
        user_id: 12345,
        chain_id: 1
    });

    console.log('Success:', response.data);

} catch (error) {
    if (error.response) {
        // Server responded with error status
        const { status, data } = error.response;

        if (status === 422) {
            // Validation errors
            Object.entries(data.errors).forEach(([field, messages]) => {
                console.error(`${field}: ${messages.join(', ')}`);
            });
        } else if (status >= 500) {
            console.error('Server error:', data.message);
        } else {
            console.error('Error:', data.error);
        }
    } else if (error.request) {
        // Request made but no response
        console.error('No response from server');
    } else {
        console.error('Error:', error.message);
    }
}
```

## Best Practices

### 1. Idempotency

Multiple calls with the same parameters should be safe. The system prevents duplicate addresses for the same user/chain combination.

```php
// Safe to call multiple times
$address1 = $walletService->createDepositAddress(12345, 1);
$address2 = $walletService->createDepositAddress(12345, 1);

// Returns the same address
assert($address1 === $address2);
```

### 2. Polling for Status Updates

When checking sweep status, implement exponential backoff:

```javascript
async function waitForSweepCompletion(address, chainId, maxAttempts = 30) {
    for (let i = 0; i < maxAttempts; i++) {
        const sweeps = await apiClient.get('/sweep-status', {
            params: { address, chain_id: chainId }
        });

        const latest = sweeps.data.data[0];

        if (latest.status === 'completed') {
            return latest;
        }

        if (latest.status === 'failed') {
            throw new Error(latest.error_message);
        }

        // Exponential backoff: 5s, 10s, 20s, 40s, ...
        const delay = Math.min(5000 * Math.pow(2, i), 60000);
        await new Promise(resolve => setTimeout(resolve, delay));
    }

    throw new Error('Sweep timeout');
}
```

### 3. Batch Operations

When working with multiple addresses, batch requests when possible:

```php
use Illuminate\Support\Facades\DB;

// Instead of N queries
foreach ($userIds as $userId) {
    $addresses[] = $walletService->getUserDepositAddresses($userId);
}

// Better: single query
$addresses = DB::table('sweeper_deposit_addresses')
    ->whereIn('user_id', $userIds)
    ->get()
    ->groupBy('user_id');
```

### 4. Webhook Integration

Instead of polling, set up event listeners (Laravel):

```php
use Multicoin\TokenSweeper\Events\{DepositDetected, SweepCompleted};

// In your EventServiceProvider
protected $listen = [
    DepositDetected::class => [
        NotifyUserOfDeposit::class,
    ],
    SweepCompleted::class => [
        CreditUserAccount::class,
        SendSweepNotification::class,
    ],
];
```

### 5. Rate Limiting

Implement rate limiting for API calls:

```javascript
const Bottleneck = require('bottleneck');

// Max 10 requests per second
const limiter = new Bottleneck({
    maxConcurrent: 5,
    minTime: 100
});

const rateLimitedClient = {
    get: (url, config) => limiter.schedule(() => apiClient.get(url, config)),
    post: (url, data, config) => limiter.schedule(() => apiClient.post(url, data, config))
};
```

### 6. Logging and Monitoring

Always log API interactions for debugging:

```php
use Illuminate\Support\Facades\Log;

try {
    $address = $walletService->createDepositAddress($userId, $chainId);

    Log::info('Deposit address created', [
        'user_id' => $userId,
        'chain_id' => $chainId,
        'address' => $address
    ]);

} catch (\Exception $e) {
    Log::error('Failed to create deposit address', [
        'user_id' => $userId,
        'chain_id' => $chainId,
        'error' => $e->getMessage()
    ]);

    throw $e;
}
```

## Interactive API Documentation

For interactive API exploration, use the Swagger UI documentation:

```bash
# Serve the OpenAPI spec with Swagger UI
npx http-server docs/api -p 8080
```

Then visit: `http://localhost:8080/swagger-ui.html`

Or use Redoc for a cleaner reading experience:

```bash
npx @redocly/cli preview-docs docs/openapi.yaml
```
