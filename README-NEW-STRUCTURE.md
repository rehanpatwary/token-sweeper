# ETH Token Sweeper - Reorganized Structure

This repository has been reorganized into a professional, maintainable structure with separate implementations for Node.js and Laravel.

## 📁 New Directory Structure

```
eth-sweeper-for-token/
├── nodejs-app/              # Node.js/Express implementation
├── laravel-package/         # Laravel package implementation
├── shared/                  # Shared resources (contracts, docs)
├── examples/                # Example implementations
└── [legacy files]           # Original files (can be archived)
```

## 🚀 Quick Start

### Node.js Implementation

```bash
cd nodejs-app
npm install
cp .env.example .env
# Configure your .env file
npm start
```

### Laravel Package

```bash
cd laravel-package
composer install
# See laravel-package/README.md for integration instructions
```

## 📦 What's New?

### ✅ Properly Organized Structure
- **Separation of Concerns**: Node.js and Laravel implementations are now separate
- **PSR-4 Compliance**: One class per file following Laravel standards
- **Clear Namespacing**: Each component in its logical location
- **Testable Architecture**: Dedicated test directories

### ✅ Node.js Application (`nodejs-app/`)
```
nodejs-app/
├── src/
│   ├── index.js                 # Main entry point
│   ├── services/                # Business logic
│   │   ├── TokenSweeper.js
│   │   ├── TokenMonitor.js
│   │   └── DepositAddressGenerator.js
│   ├── controllers/             # API controllers
│   ├── models/                  # Data models
│   ├── routes/                  # Route definitions
│   ├── config/                  # Configuration
│   └── utils/                   # Utilities
├── database/
│   ├── migrations/              # SQL migrations
│   └── schema.sql              # Database schema
├── tests/                       # Test suites
├── docs/                        # Documentation
├── package.json                 # Dependencies
└── .env.example                 # Environment template
```

### ✅ Laravel Package (`laravel-package/`)
```
laravel-package/
├── src/
│   ├── Services/                # 4 service classes
│   │   ├── Web3Service.php
│   │   ├── WalletService.php
│   │   ├── TransactionSignerService.php
│   │   ├── SweeperService.php
│   │   └── MonitorService.php
│   ├── Models/                  # 5 Eloquent models
│   │   ├── Chain.php
│   │   ├── Token.php
│   │   ├── DepositAddress.php
│   │   ├── PendingSweep.php
│   │   └── SweepLog.php
│   ├── Commands/                # 7 Artisan commands
│   │   ├── GenerateDepositAddressCommand.php
│   │   ├── MonitorDepositsCommand.php
│   │   ├── ProcessSweepCommand.php
│   │   ├── CheckBalanceCommand.php
│   │   ├── CheckPendingSweepsCommand.php
│   │   ├── InstallCommand.php
│   │   └── SeedChainsCommand.php
│   ├── Jobs/                    # 3 queue jobs
│   │   ├── FundDepositAddress.php
│   │   ├── SweepTokens.php
│   │   └── CheckPendingSweeps.php
│   ├── Events/                  # 3 events
│   │   ├── DepositDetected.php
│   │   ├── SweepStarted.php
│   │   └── SweepCompleted.php
│   ├── Controllers/             # API controllers
│   ├── Facades/                 # Laravel facades
│   ├── Config/                  # Configuration
│   └── TokenSweeperServiceProvider.php
├── database/
│   ├── migrations/              # 5 migration files
│   └── schema.sql              # Full schema
├── tests/                       # PHPUnit tests
├── routes/                      # API routes
├── composer.json               # Package definition
└── README.md                   # Package documentation
```

## 🔄 Migration from Old Structure

### Files Moved

| Old Location | New Location |
|-------------|--------------|
| `main_app.js` | `nodejs-app/src/index.js` |
| `token_sweeper.js` | `nodejs-app/src/services/TokenSweeper.js` |
| `token_monitor.js` | `nodejs-app/src/services/TokenMonitor.js` |
| `deposit_address_generator.js` | `nodejs-app/src/services/DepositAddressGenerator.js` |
| `db.sql` | `nodejs-app/database/schema.sql` |
| `php-package/*.php` | `laravel-package/src/*/*.php` (split) |
| `php_database_schema.sql` | `laravel-package/database/schema.sql` |
| `php_config.php` | `laravel-package/src/Config/token-sweeper.php` |

### PHP Files Split

The following monolithic PHP files have been split into individual classes:

1. **`laravel_events_commands.php`** → 7 Commands + 3 Events
2. **`laravel_core_services.php`** → 4 Service classes
3. **`laravel_sweeper_monitor.php`** → 2 Service classes
4. **`laravel_models.php`** → 5 Model classes
5. **`laravel_jobs_facade.php`** → 3 Jobs + 1 Facade
6. **`laravel_controller_provider.php`** → 1 Controller + 1 ServiceProvider
7. **`laravel_migrations.php`** → 5 Migration files

## 📚 Documentation

- **Node.js**: See `nodejs-app/docs/`
- **Laravel**: See `laravel-package/README.md` and `laravel-package/QUICKSTART.md`
- **Architecture**: See `shared/docs/`

## 🧪 Testing

### Node.js
```bash
cd nodejs-app
npm test
```

### Laravel
```bash
cd laravel-package
composer test
```

## 🛠️ Development

### Node.js Development
```bash
cd nodejs-app
npm run dev  # Runs with nodemon for auto-reload
```

### Laravel Development
```bash
# Integrate into your Laravel app (see laravel-package/README.md)
php artisan sweeper:monitor  # Start monitoring
php artisan sweeper:pending  # Check pending sweeps
```

## 📝 Available Commands

### Node.js API Endpoints
- `POST /api/deposit-address` - Generate deposit address
- `GET /api/sweep-status/:address` - Check sweep status
- `GET /health` - Health check

### Laravel Artisan Commands
```bash
php artisan sweeper:install              # Install package
php artisan sweeper:seed                 # Seed chains and tokens
php artisan sweeper:monitor              # Start monitoring
php artisan sweeper:sweep                # Manual sweep
php artisan sweeper:generate-address     # Generate deposit address
php artisan sweeper:balance              # Check balance
php artisan sweeper:pending              # Check pending sweeps
```

## 🔒 Security

Both implementations use:
- **AES-256-GCM encryption** for private keys
- **Environment variables** for sensitive data
- **Secure RPC connections**
- **Database encryption** for master wallet keys

## 🤝 Contributing

1. Choose your implementation (Node.js or Laravel)
2. Follow the respective coding standards
3. Add tests for new features
4. Update documentation

## 📄 License

MIT License

## 🆘 Support

- Node.js issues: See `nodejs-app/docs/`
- Laravel issues: See `laravel-package/README.md`
- General questions: Check `shared/docs/`

---

## ⚠️ Legacy Files

The following files in the root directory are legacy and can be archived:
- `main_app.js`
- `token_sweeper.js`
- `token_monitor.js`
- `deposit_address_generator.js`
- `db.sql`
- `php_*.php` files
- `php-package/` directory

These files are kept temporarily for reference but are no longer needed.
