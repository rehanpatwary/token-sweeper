# Token Sweeper Architecture

## System Overview

```mermaid
graph TB
    subgraph "Client Applications"
        WebApp[Web Application]
        MobileApp[Mobile App]
        API_Client[API Clients]
    end

    subgraph "API Layer"
        Laravel_API[Laravel API<br/>PHP Implementation]
        Node_API[Node.js API<br/>Express Server]
    end

    subgraph "Core Services"
        WalletService[Wallet Service<br/>HD Wallet Generation]
        MonitorService[Monitor Service<br/>Deposit Detection]
        SweeperService[Sweeper Service<br/>Token Transfer]
        Web3Service[Web3 Service<br/>RPC Communication]
    end

    subgraph "Background Processing"
        Queue[Laravel Queue<br/>Jobs]
        Monitor[Token Monitor<br/>Polling Service]
    end

    subgraph "Data Layer"
        PostgreSQL[(PostgreSQL<br/>Primary Database)]
        Redis[(Redis<br/>Cache & Queue)]
    end

    subgraph "Blockchain Networks"
        Ethereum[Ethereum<br/>ETH & ERC-20]
        BSC[Binance Smart Chain<br/>BNB & BEP-20]
        Polygon[Polygon<br/>MATIC & ERC-20]
        Arbitrum[Arbitrum<br/>ETH & ERC-20]
        Optimism[Optimism<br/>ETH & ERC-20]
    end

    WebApp --> Laravel_API
    MobileApp --> Node_API
    API_Client --> Laravel_API

    Laravel_API --> WalletService
    Laravel_API --> SweeperService
    Node_API --> WalletService
    Node_API --> SweeperService

    WalletService --> PostgreSQL
    SweeperService --> Queue
    MonitorService --> Queue
    MonitorService --> Web3Service

    Queue --> Redis
    Monitor --> Web3Service

    Web3Service --> Ethereum
    Web3Service --> BSC
    Web3Service --> Polygon
    Web3Service --> Arbitrum
    Web3Service --> Optimism

    WalletService --> PostgreSQL
    SweeperService --> PostgreSQL
    MonitorService --> PostgreSQL
```

## Sweep Workflow

```mermaid
sequenceDiagram
    participant User
    participant API
    participant Monitor
    participant Sweeper
    participant Blockchain
    participant HotWallet

    User->>API: Request Deposit Address
    API->>API: Generate HD Wallet Address
    API->>User: Return Address

    User->>Blockchain: Send Tokens to Deposit Address

    Monitor->>Blockchain: Poll for Token Balance
    Blockchain-->>Monitor: Balance: 100 USDT

    Monitor->>Monitor: Check Gas Balance
    Monitor->>Blockchain: Get ETH Balance
    Blockchain-->>Monitor: Balance: 0 ETH

    Monitor->>Sweeper: Trigger Funding Job
    Sweeper->>Blockchain: Send 0.002 ETH (Gas)
    Blockchain-->>Sweeper: Funding TX Hash

    Monitor->>Blockchain: Wait for Confirmation
    Blockchain-->>Monitor: Confirmed (1 block)

    Monitor->>Sweeper: Trigger Sweep Job
    Sweeper->>Sweeper: Sign Token Transfer TX
    Sweeper->>Blockchain: Transfer 100 USDT to Hot Wallet
    Blockchain-->>Sweeper: Sweep TX Hash

    Monitor->>Blockchain: Wait for Confirmation
    Blockchain-->>Monitor: Confirmed

    Sweeper->>Sweeper: Log Sweep Completion
    Sweeper->>HotWallet: Tokens Received
```

## Component Architecture

```mermaid
graph TB
    subgraph "PHP Laravel Package"
        direction TB
        Commands[Artisan Commands]
        Controllers[API Controllers]
        Services_PHP[Services Layer]
        Models[Eloquent Models]
        Jobs[Queue Jobs]
        Events[Laravel Events]

        Commands --> Services_PHP
        Controllers --> Services_PHP
        Services_PHP --> Models
        Services_PHP --> Jobs
        Jobs --> Events
    end

    subgraph "Node.js Application"
        direction TB
        Routes[Express Routes]
        Controllers_Node[Controllers]
        Services_Node[Services Layer]
        DB_Models[Database Models]

        Routes --> Controllers_Node
        Controllers_Node --> Services_Node
        Services_Node --> DB_Models
    end

    subgraph "Shared Database Schema"
        direction LR
        Tables[chains<br/>tokens<br/>deposit_addresses<br/>pending_sweeps<br/>sweep_logs]
    end

    Models --> Tables
    DB_Models --> Tables
```

## Data Flow - Token Sweep

```mermaid
flowchart TD
    Start([Start: Monitor Detects Deposit])
    CheckGas{Has Sufficient Gas?}
    FundAddress[Fund Address with Native Token]
    WaitFunding[Wait for Funding Confirmation]
    SignTx[Sign Token Transfer Transaction]
    BroadcastTx[Broadcast to Blockchain]
    WaitConfirm[Wait for Confirmations]
    LogSuccess[Log Sweep Success]
    LogFail[Log Sweep Failure]
    UpdatePending[Update Pending Sweep Status]
    End([End])

    Start --> CheckGas
    CheckGas -->|Yes| SignTx
    CheckGas -->|No| FundAddress
    FundAddress --> WaitFunding
    WaitFunding --> UpdatePending
    UpdatePending --> SignTx
    SignTx --> BroadcastTx
    BroadcastTx --> WaitConfirm
    WaitConfirm -->|Success| LogSuccess
    WaitConfirm -->|Failed| LogFail
    LogSuccess --> End
    LogFail --> End
```

## Database Schema

```mermaid
erDiagram
    chains ||--o{ tokens : "has many"
    chains ||--o{ deposit_addresses : "has many"
    chains ||--o{ pending_sweeps : "has many"
    chains ||--o{ sweep_logs : "has many"

    chains {
        bigint id PK
        integer chain_id UK
        string name
        string native_symbol
        string rpc_url
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    tokens {
        bigint id PK
        integer chain_id FK
        string contract_address
        string symbol
        string name
        integer decimals
        boolean is_active
        timestamp created_at
        timestamp updated_at
    }

    deposit_addresses {
        bigint id PK
        bigint user_id
        integer chain_id FK
        string address UK
        integer derivation_index
        text private_key_encrypted
        timestamp created_at
        timestamp updated_at
    }

    pending_sweeps {
        bigint id PK
        string deposit_address
        string token_address
        integer chain_id FK
        string amount
        string status
        string funding_tx_hash
        string sweep_tx_hash
        integer retry_count
        text error_message
        timestamp created_at
        timestamp updated_at
    }

    sweep_logs {
        bigint id PK
        string deposit_address
        string token_address
        integer chain_id FK
        string amount
        string status
        string funding_tx_hash
        string sweep_tx_hash
        string gas_used
        text error_message
        timestamp created_at
    }
```

## Service Layer Architecture

### PHP Services

```mermaid
classDiagram
    class SweeperService {
        +processSweep(address, token, chainId)
        +checkPendingSweeps()
        -fundDepositAddress()
        -executeSweep()
        -signTransaction()
    }

    class MonitorService {
        +startMonitoring()
        +checkDeposit(address, chainId)
        -detectTokenBalance()
        -triggerSweep()
    }

    class WalletService {
        +createDepositAddress(userId, chainId)
        +getUserDepositAddresses(userId)
        +derivePrivateKey(index)
        -generateHDWallet()
    }

    class Web3Service {
        +getBalance(address, chainId)
        +getTokenBalance(address, token, chainId)
        +sendTransaction(signedTx, chainId)
        +waitForConfirmation(txHash, chainId)
        -callRPC(method, params, chainId)
    }

    class TransactionSignerService {
        +signTransaction(tx, privateKey)
        -createRawTransaction()
        -signWithECDSA()
    }

    SweeperService --> Web3Service
    SweeperService --> TransactionSignerService
    SweeperService --> WalletService
    MonitorService --> Web3Service
    WalletService --> TransactionSignerService
```

### Node.js Services

```mermaid
classDiagram
    class TokenMonitor {
        +monitorTokenDeposits()
        -pollDeposits()
        -checkBalance()
    }

    class TokenSweeper {
        +sweepTokens(address, amount)
        -fundAddress()
        -executeTransfer()
    }

    class DepositAddressGenerator {
        +generateDepositAddress(userId)
        -deriveAddress()
        -storeInDatabase()
    }

    TokenMonitor --> TokenSweeper
    DepositAddressGenerator --> TokenSweeper
```

## Event-Driven Architecture (Laravel)

```mermaid
sequenceDiagram
    participant Monitor as Monitor Service
    participant Event as Event System
    participant Listener as Event Listeners
    participant Queue as Queue Jobs
    participant DB as Database

    Monitor->>Event: Dispatch DepositDetected
    Event->>Listener: Handle Event
    Listener->>Queue: Dispatch FundDepositAddress Job
    Queue->>DB: Update pending_sweeps

    Queue->>Event: Dispatch SweepStarted
    Event->>DB: Log Event

    Queue->>Queue: Execute FundDepositAddress
    Queue->>Queue: Wait & Dispatch SweepTokens Job

    Queue->>Queue: Execute SweepTokens
    Queue->>Event: Dispatch SweepCompleted
    Event->>DB: Update sweep_logs
```

## Multi-Chain Configuration

```mermaid
graph LR
    subgraph "Chain Configuration"
        Config[config/token-sweeper.php]
        Config --> Chains[Supported Chains]
        Config --> Tokens[Default Tokens]
        Config --> RPC[RPC URLs]
    end

    subgraph "Chain 1: Ethereum"
        ETH_RPC[ETH RPC URL]
        ETH_Master[Master Wallet]
        ETH_Hot[Hot Wallet]
    end

    subgraph "Chain 56: BSC"
        BSC_RPC[BSC RPC URL]
        BSC_Master[Master Wallet]
        BSC_Hot[Hot Wallet]
    end

    subgraph "Chain 137: Polygon"
        POL_RPC[Polygon RPC URL]
        POL_Master[Master Wallet]
        POL_Hot[Hot Wallet]
    end

    Chains --> ETH_RPC
    Chains --> BSC_RPC
    Chains --> POL_RPC
```

## Security Architecture

```mermaid
graph TB
    subgraph "Key Management"
        MasterSeed[Master Seed<br/>Environment Variable]
        EncryptedKeys[Encrypted Private Keys<br/>AES-256-GCM]
        Laravel_Crypt[Laravel Crypt Facade]
    end

    subgraph "Wallet Separation"
        MasterWallet[Master Wallet<br/>Funds Gas]
        HotWallet[Hot Wallet<br/>Collects Tokens]
        DepositWallets[Deposit Wallets<br/>Ephemeral]
    end

    subgraph "Transaction Security"
        Signing[Transaction Signing<br/>secp256k1 ECDSA]
        Nonce[Nonce Management<br/>Sequential]
        GasLimit[Gas Limit Protection<br/>Max 100k]
    end

    MasterSeed --> Laravel_Crypt
    Laravel_Crypt --> EncryptedKeys
    EncryptedKeys --> DepositWallets

    MasterWallet --> DepositWallets
    DepositWallets --> HotWallet

    DepositWallets --> Signing
    Signing --> Nonce
    Nonce --> GasLimit
```

## Deployment Architecture

```mermaid
graph TB
    subgraph "Production Environment"
        LB[Load Balancer]

        subgraph "Application Servers"
            Laravel1[Laravel App 1]
            Laravel2[Laravel App 2]
            Node1[Node.js App 1]
        end

        subgraph "Background Workers"
            Queue1[Queue Worker 1]
            Queue2[Queue Worker 2]
            Monitor1[Monitor Service]
        end

        subgraph "Data Layer"
            PG_Primary[(PostgreSQL Primary)]
            PG_Replica[(PostgreSQL Replica)]
            Redis_Cache[(Redis Cache)]
            Redis_Queue[(Redis Queue)]
        end

        subgraph "External Services"
            RPC_Eth[Ethereum RPC]
            RPC_BSC[BSC RPC]
            RPC_Poly[Polygon RPC]
        end
    end

    LB --> Laravel1
    LB --> Laravel2
    LB --> Node1

    Laravel1 --> PG_Primary
    Laravel2 --> PG_Primary
    Node1 --> PG_Primary

    Queue1 --> Redis_Queue
    Queue2 --> Redis_Queue
    Monitor1 --> Redis_Queue

    Queue1 --> PG_Primary
    Queue2 --> PG_Primary
    Monitor1 --> PG_Primary

    Laravel1 --> Redis_Cache
    Laravel2 --> Redis_Cache

    Queue1 --> RPC_Eth
    Queue1 --> RPC_BSC
    Queue1 --> RPC_Poly
    Monitor1 --> RPC_Eth
    Monitor1 --> RPC_BSC
    Monitor1 --> RPC_Poly
```

## Technology Stack

### Backend Frameworks
- **Laravel 10/11** - PHP framework for web API and background processing
- **Express.js 4.18** - Node.js framework for lightweight API server

### Blockchain Libraries
- **simplito/elliptic-php** - Elliptic curve cryptography for PHP
- **ethers.js v6** - Ethereum library for Node.js
- **kornrunner/keccak** - Keccak-256 hashing for Ethereum addresses

### Database & Cache
- **PostgreSQL 12+** - Primary relational database
- **Redis 6+** - Cache and queue backend

### Testing
- **PHPUnit 11** - PHP unit and feature testing
- **Orchestra Testbench** - Laravel package testing framework
- **Jest 29** - JavaScript testing framework

### DevOps & Tooling
- **Composer** - PHP dependency management
- **npm** - Node.js package management
- **Laravel Queue** - Background job processing
- **Artisan CLI** - Laravel command-line tools

## Key Design Patterns

### 1. Service Layer Pattern
Encapsulates business logic in dedicated service classes, separating concerns from controllers and models.

### 2. Repository Pattern
Models act as repositories, providing data access abstraction with Eloquent ORM.

### 3. Event-Driven Architecture
Uses Laravel events and listeners for decoupled component communication.

### 4. Queue-Based Processing
Asynchronous processing of long-running sweep operations using Laravel queues.

### 5. HD Wallet Derivation
BIP-44 compliant hierarchical deterministic wallet generation for secure address creation.

### 6. Strategy Pattern
Configurable chain-specific settings (RPC URLs, gas amounts, confirmation blocks).

## Scalability Considerations

### Horizontal Scaling
- **API Servers**: Multiple Laravel/Node.js instances behind load balancer
- **Queue Workers**: Multiple workers processing jobs concurrently
- **Monitor Services**: Distributed monitoring across chains

### Database Optimization
- **Indexed Columns**: chain_id, user_id, deposit_address, status
- **Read Replicas**: Separate read/write database instances
- **Connection Pooling**: Efficient database connection management

### Caching Strategy
- **Chain Metadata**: Cache chain and token configurations
- **RPC Responses**: Cache blockchain data with TTL
- **User Addresses**: Cache frequently accessed deposit addresses

### Rate Limiting
- **RPC Requests**: Implement backoff and retry logic
- **API Endpoints**: Rate limit user requests
- **Queue Jobs**: Throttle concurrent sweep operations

## Monitoring & Observability

### Metrics
- **Sweep Success Rate**: Percentage of successful sweeps
- **Average Sweep Time**: Time from detection to completion
- **Gas Costs**: Track gas spending across chains
- **Queue Depth**: Monitor pending job backlog

### Logging
- **Application Logs**: Laravel logs for errors and events
- **Sweep Logs**: Database table tracking all sweep operations
- **Transaction Logs**: On-chain transaction hashes and confirmations

### Alerts
- **Failed Sweeps**: Alert on repeated failures
- **Low Gas Balance**: Alert when master wallet balance is low
- **RPC Failures**: Alert on blockchain connectivity issues
- **Queue Backlog**: Alert when queue depth exceeds threshold
