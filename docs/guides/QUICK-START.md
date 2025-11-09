# Quick Start Guide

## 🚀 Get Started in 3 Minutes

### Option 1: Node.js Implementation

```bash
# 1. Navigate to Node.js app
cd nodejs-app

# 2. Install dependencies
npm install

# 3. Configure environment
cp .env.example .env
nano .env  # or use your favorite editor

# 4. Setup database
createdb sweeper_db
psql -d sweeper_db -f database/schema.sql

# 5. Start the server
npm start

# Server running on http://localhost:3000
```

### Option 2: Laravel Package

```bash
# 1. Navigate to Laravel package
cd laravel-package

# 2. Install dependencies
composer install

# 3. Integrate into your Laravel app
# (See laravel-package/README.md for detailed instructions)

# 4. Publish configuration
php artisan vendor:publish --tag=token-sweeper-config

# 5. Run migrations
php artisan migrate

# 6. Seed chains
php artisan sweeper:seed

# 7. Start monitoring
php artisan sweeper:monitor
```

---

## 📝 Quick Reference

### Node.js API Endpoints

```bash
# Generate deposit address
curl -X POST http://localhost:3000/api/deposit-address \
  -H "Content-Type: application/json" \
  -d '{"userId": 123}'

# Check sweep status
curl http://localhost:3000/api/sweep-status/0x...

# Health check
curl http://localhost:3000/health
```

### Laravel Commands

```bash
# Generate deposit address
php artisan sweeper:generate-address {user_id} {chain_id}

# Start monitoring
php artisan sweeper:monitor

# Manual sweep
php artisan sweeper:sweep {address} {token} {chain_id}

# Check balance
php artisan sweeper:balance {address} {token?} {--chain_id=1}

# View pending sweeps
php artisan sweeper:pending

# Check all commands
php artisan sweeper
```

---

## 🔧 Required Environment Variables

### Node.js (.env)

```env
RPC_URL=https://eth.nownodes.io/YOUR_API_KEY
MASTER_PRIVATE_KEY=0x...
HOT_WALLET_ADDRESS=0x...
DATABASE_URL=postgresql://user:password@localhost:5432/sweeper_db
ENCRYPTION_KEY=<generate with: openssl rand -hex 32>
PORT=3000
```

### Laravel (config/token-sweeper.php)

Published via `php artisan vendor:publish --tag=token-sweeper-config`

---

## 📚 Documentation

| Document | Purpose |
|----------|---------|
| [Project README](../../README.md) | Complete project overview |
| [Verification Report](../reference/VERIFICATION-REPORT.md) | Test results and validation |
| [Developer Setup](DEVELOPER-SETUP.md) | Complete development environment setup |
| [API Guide](API-GUIDE.md) | API integration examples |
| [Architecture](../architecture/ARCHITECTURE.md) | System architecture and design |

---

## 🆘 Troubleshooting

### Node.js won't start
```bash
# Check if .env exists
ls -la nodejs-app/.env

# Verify dependencies installed
cd nodejs-app && npm list --depth=0

# Check database connection
psql -d sweeper_db -c "SELECT 1"
```

### Laravel commands not found
```bash
# Ensure package is installed
composer show | grep token-sweeper

# Clear Laravel cache
php artisan config:clear
php artisan cache:clear

# Re-discover packages
composer dump-autoload
php artisan package:discover
```

---

## ✅ Verification

Run these commands to verify everything is set up correctly:

```bash
# Node.js
cd nodejs-app
npm start &
curl http://localhost:3000/health
# Should return: {"status":"ok","timestamp":"..."}

# Laravel
php artisan sweeper:install
php artisan sweeper:seed
php artisan sweeper:balance 0x... --chain_id=1
```

---

## 📊 Project Structure

```
eth-sweeper-for-token/
├── nodejs-app/          # Node.js implementation
├── laravel-package/     # Laravel package
├── shared/              # Shared resources
└── examples/            # Example code
```

---

## 🔐 Security Checklist

- [ ] Update `YourVendor` namespace to your actual vendor name
- [ ] Generate new `ENCRYPTION_KEY` for production
- [ ] Never commit `.env` files
- [ ] Use HTTPS in production
- [ ] Secure database with firewall rules
- [ ] Rotate master wallet private key regularly
- [ ] Enable 2FA on hot wallet
- [ ] Monitor logs for suspicious activity

---

## 🎯 Next Steps

1. ✅ Install dependencies
2. ✅ Configure environment variables
3. ✅ Setup database
4. ✅ Test endpoints/commands
5. ⏳ Deploy to staging
6. ⏳ Run integration tests
7. ⏳ Deploy to production
8. ⏳ Setup monitoring and alerts

---

**Need Help?**
- Full Documentation: [Documentation Index](../README.md)
- Developer Setup: [Setup Guide](DEVELOPER-SETUP.md)
- API Integration: [API Guide](API-GUIDE.md)
