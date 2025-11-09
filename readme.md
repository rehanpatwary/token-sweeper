# Multicoin Token Sweeper - Monorepo

**Version:** 1.0.0
**License:** MIT
**Maintainer:** Multicoin Team

Multi-chain token sweeping system with both Laravel (PHP) and Node.js implementations in a single repository.

---

## 🏗️ Monorepo Structure

```
multicoin-token-sweeper/
├── composer.json              # PHP/Laravel package manager
├── package.json               # Node.js package manager
├── phpunit.xml               # PHPUnit configuration
│
├── php/                      # 🐘 Laravel/PHP Implementation
│   ├── src/                  # Source code
│   │   ├── Commands/         # Artisan commands (7)
│   │   ├── Controllers/      # API controllers
│   │   ├── Events/          # Laravel events (3)
│   │   ├── Facades/         # Laravel facades
│   │   ├── Jobs/            # Queue jobs (3)
│   │   ├── Models/          # Eloquent models (5)
│   │   └── Services/        # Business logic services (5)
│   ├── database/
│   │   ├── migrations/      # Database migrations (5)
│   │   └── schema.sql       # Complete schema
│   ├── tests/               # Test suite (248 tests)
│   │   ├── Unit/            # Unit tests
│   │   └── Feature/         # Feature tests
│   ├── config/              # Configuration files
│   ├── routes/              # API routes
│   └── docs/                # PHP-specific documentation
│
├── nodejs/                   # 🟢 Node.js Implementation
│   ├── src/                 # Source code
│   │   ├── services/        # Business logic
│   │   ├── models/          # Database models
│   │   └── utils/           # Utilities
│   ├── config/              # Configuration
│   └── examples/            # Usage examples
│
├── shared/                   # 📦 Shared Resources
│   ├── contracts/           # Smart contract ABIs
│   └── docs/                # Shared documentation
│
├── docs/                     # 📚 Project Documentation
│   ├── COMPLETE-PACKAGE-REPORT.md
│   ├── TEST-REPORT.md
│   └── ARCHIVE-AND-NAMESPACE-UPDATE-REPORT.md
│
└── archive/                  # 🗃️ Archived Files
    └── original-files/
```

---

## 🚀 Quick Start

### Prerequisites

- **PHP** >= 8.2
- **Composer** >= 2.0
- **Node.js** >= 18.0
- **npm** or **yarn**
- **MySQL** or **PostgreSQL**

### Installation

```bash
# Clone the repository
git clone https://github.com/multicoin/token-sweeper.git
cd token-sweeper

# Install both PHP and Node.js dependencies
composer install
npm install

# Or use the combined command
npm run install:all
```

---

## 🐘 PHP/Laravel Usage

### Setup

```bash
# Publish configuration
php artisan vendor:publish --tag=token-sweeper-config

# Run migrations
php artisan migrate

# Seed default chains and tokens
php artisan sweeper:seed
```

### Basic Usage

```bash
# Generate deposit address
php artisan sweeper:generate-address {user_id} {chain_id}

# Start monitoring deposits
php artisan sweeper:monitor

# Check pending sweeps
php artisan sweeper:pending

# Manual sweep
php artisan sweeper:sweep {address} {token} {chain_id}
```

### Running PHP Tests

```bash
# Run all tests
composer test

# Run only unit tests
composer test:unit

# Run only feature tests
composer test:feature

# Or use PHPUnit directly
./vendor/bin/phpunit
```

**Test Coverage:** 217/248 tests passing (87.5%)

---

## 🟢 Node.js Usage

### Setup

```bash
# Copy environment file
cp nodejs/.env.example nodejs/.env

# Edit configuration
nano nodejs/.env
```

### Running the Application

```bash
# Start the application
npm start

# Development mode with auto-reload
npm run dev
```

### Running Node.js Tests

```bash
# Run tests
npm run test:node

# Watch mode
npm run test:watch
```

---

## 🔧 Configuration

### PHP Configuration

Edit `config/token-sweeper.php`:

```php
return [
    'chains' => [
        'ethereum' => [
            'rpc_url' => env('ETH_RPC_URL'),
            'chain_id' => 1,
        ],
        // ... more chains
    ],
    'monitoring' => [
        'check_interval' => 10,
        'block_confirmations' => 12,
    ],
];
```

### Node.js Configuration

Edit `nodejs/.env`:

```env
# RPC Endpoints
ETH_RPC_URL=https://eth.llamarpc.com
BSC_RPC_URL=https://bsc-dataseed.binance.org

# Database
DB_HOST=localhost
DB_PORT=5432
DB_NAME=token_sweeper
DB_USER=your_user
DB_PASSWORD=your_password

# Wallets
MASTER_WALLET_ADDRESS=0x...
MASTER_WALLET_PRIVATE_KEY=0x...
HOT_WALLET_ADDRESS=0x...
```

---

## 📦 Supported Blockchains

| Chain | Chain ID | Native Symbol | Status |
|-------|----------|---------------|--------|
| **Ethereum** | 1 | ETH | ✅ Supported |
| **BSC** | 56 | BNB | ✅ Supported |
| **Polygon** | 137 | MATIC | ✅ Supported |
| **Arbitrum** | 42161 | ETH | ✅ Supported |
| **Optimism** | 10 | ETH | ✅ Supported |

---

## 🧪 Testing

### Run All Tests (PHP + Node.js)

```bash
npm test
```

### PHP Tests Only

```bash
npm run test:php
# or
composer test
```

### Node.js Tests Only

```bash
npm run test:node
```

---

## 📖 Documentation

### PHP/Laravel Documentation

- **[Usage Guide](php/docs/USAGE.md)** - Comprehensive usage instructions
- **[API Reference](php/docs/API-REFERENCE.md)** - Complete API documentation
- **[Troubleshooting](php/docs/TROUBLESHOOTING.md)** - Common issues and solutions

### Project Documentation

- **[Complete Package Report](docs/COMPLETE-PACKAGE-REPORT.md)** - Full project overview
- **[Test Report](docs/TEST-REPORT.md)** - Test suite analysis
- **[Archive Report](docs/ARCHIVE-AND-NAMESPACE-UPDATE-REPORT.md)** - Migration history

---

## 🏃 Development Workflow

### Working with PHP Code

```bash
# Run tests in watch mode (if using phpunit-watcher)
composer test:unit -- --filter MyTest

# Check code style
composer format

# Run static analysis
composer analyse
```

### Working with Node.js Code

```bash
# Development mode
npm run dev

# Run specific test
npm test -- --testNamePattern="MyTest"

# Lint code
npm run lint
```

---

## 🔒 Security

### Private Key Storage

- **PHP:** Uses Laravel's `Crypt` facade with AES-256-GCM encryption
- **Node.js:** Uses environment variables with proper `.env` handling

### Best Practices

1. Never commit `.env` files
2. Use separate wallets for master (gas funding) and hot (token collection)
3. Rotate private keys regularly
4. Monitor sweep logs for suspicious activity
5. Use rate limiting on API endpoints

---

## 🚢 Deployment

### PHP/Laravel Deployment

```bash
# Production install
composer install --no-dev --optimize-autoloader

# Cache configuration
php artisan config:cache
php artisan route:cache

# Run migrations
php artisan migrate --force

# Start queue workers
php artisan queue:work --daemon
```

### Node.js Deployment

```bash
# Install production dependencies
npm install --production

# Start with PM2
pm2 start nodejs/src/index.js --name token-sweeper

# Or use Docker
docker-compose up -d
```

---

## 📊 Architecture

### PHP Architecture

- **Models:** 5 Eloquent models with relationships
- **Services:** 5 business logic services
- **Jobs:** 3 queue jobs for async processing
- **Events:** 3 Laravel events for workflow
- **Commands:** 7 Artisan commands

### Node.js Architecture

- **Services:** Modular service-based architecture
- **Database:** PostgreSQL with connection pooling
- **API:** RESTful API with Express.js

---

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

### Development Guidelines

- Follow PSR-12 for PHP code
- Use ESLint for Node.js code
- Write tests for new features
- Update documentation

---

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

---

## 📧 Support

- **Email:** dev@multicoin.com
- **Documentation:** See `docs/` directory
- **Issues:** [GitHub Issues](https://github.com/multicoin/token-sweeper/issues)

---

## 🎯 Roadmap

- [x] Multi-chain support (5 chains)
- [x] Laravel package implementation
- [x] Node.js implementation
- [x] Comprehensive test suite
- [x] Documentation
- [ ] GraphQL API
- [ ] Real-time WebSocket updates
- [ ] Dashboard UI
- [ ] Additional chain support (Avalanche, Fantom)
- [ ] Multi-tenant support

---

## ⭐ Acknowledgments

- Laravel Framework
- Ethers.js
- Web3.php
- Orchestra Testbench

---

**Built with ❤️ by the Multicoin Team**
