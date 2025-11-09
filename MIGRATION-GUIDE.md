# Migration Guide - Old to New Structure

This guide helps you transition from the old mixed structure to the new organized structure.

## Overview

The codebase has been reorganized from a single directory with mixed JavaScript, PHP, and SQL files into separate, well-organized implementations:

- **nodejs-app/** - Node.js implementation
- **laravel-package/** - Laravel package implementation

## Pre-Migration Checklist

Before archiving old files, ensure:

1. ✅ New directory structure is created
2. ✅ All files have been copied/split correctly
3. ✅ Dependencies are installed
4. ✅ Configuration files are set up
5. ✅ Tests pass (if applicable)

## Step 1: Verify New Structure

### Check Node.js App
```bash
cd nodejs-app
ls -la src/
# Should see: controllers/, services/, routes/, models/, utils/, config/
```

### Check Laravel Package
```bash
cd laravel-package
ls -la src/
# Should see: Services/, Models/, Commands/, Jobs/, Events/, Controllers/, Facades/
```

## Step 2: Archive Old Files

Create an archive directory:
```bash
mkdir -p archive/original-files
```

Move legacy files to archive:
```bash
# JavaScript files
mv main_app.js archive/original-files/
mv token_sweeper.js archive/original-files/
mv token_monitor.js archive/original-files/
mv deposit_address_generator.js archive/original-files/

# SQL files
mv db.sql archive/original-files/
mv php_database_schema.sql archive/original-files/

# PHP files
mv php_token_sweeper.php archive/original-files/
mv php_token_monitor.php archive/original-files/
mv php_config.php archive/original-files/

# Shell scripts
mv config_files.sh archive/original-files/

# PHP package directory
mv php-package archive/original-files/

# Documentation (keep copies in root)
cp readme.md archive/original-files/
cp laravel_quickstart_summary.md archive/original-files/
```

## Step 3: Update Git (if using)

Add new directories:
```bash
git add nodejs-app/
git add laravel-package/
git add shared/
git add examples/
git add README-NEW-STRUCTURE.md
git add MIGRATION-GUIDE.md
```

Remove old files from git:
```bash
git rm main_app.js token_sweeper.js token_monitor.js deposit_address_generator.js
git rm db.sql php_database_schema.sql php_config.php config_files.sh
git rm php_token_sweeper.php php_token_monitor.php
git rm -r php-package/
```

Commit changes:
```bash
git commit -m "Reorganize codebase into proper structure

- Split Node.js and Laravel implementations
- One class per file following PSR-4
- Proper directory structure
- Added documentation and configuration
- Archived legacy files"
```

## Step 4: Update Deployment

### Node.js Deployment

If you were running `main_app.js`:
```bash
# Old
node main_app.js

# New
cd nodejs-app && node src/index.js
# or
cd nodejs-app && npm start
```

### Update PM2 configuration (if using):
```javascript
// ecosystem.config.js
module.exports = {
  apps: [{
    name: 'token-sweeper',
    script: './nodejs-app/src/index.js',
    cwd: '/path/to/eth-sweeper-for-token',
    env: {
      NODE_ENV: 'production'
    }
  }]
}
```

### Laravel Integration

If you were including PHP files directly:
```php
// Old
require_once 'php_token_sweeper.php';
require_once 'php_token_monitor.php';

// New - install as Composer package
// See laravel-package/README.md for integration
```

## Step 5: Update Environment Variables

### Node.js (.env location changed)

```bash
# Old location: /
# New location: /nodejs-app/

cp .env nodejs-app/.env  # Copy existing config
cd nodejs-app
# Update paths in .env if needed
```

### Laravel (config location changed)

```php
// Old: php_config.php directly included
// New: Laravel config file at config/token-sweeper.php
// Published via: php artisan vendor:publish --tag=token-sweeper-config
```

## Step 6: Update Database Connection Strings

If using relative paths in DATABASE_URL:

```bash
# Old (from root)
DATABASE_URL=postgresql://localhost/sweeper

# New (still from project root, but running from subdirectory)
# No changes needed if using absolute connection strings
```

## Step 7: Update Import Paths

### JavaScript

```javascript
// Old
const { monitorTokenDeposits } = require('./tokenMonitor');

// New
const { monitorTokenDeposits } = require('./services/TokenMonitor');
```

### PHP

```php
// Old
require_once __DIR__ . '/php_token_sweeper.php';
use TokenSweeper;

// New - via Composer autoload
use YourVendor\TokenSweeper\Services\SweeperService;
```

## Step 8: Run Tests

### Node.js
```bash
cd nodejs-app
npm install
npm test
```

### Laravel
```bash
cd laravel-package
composer install
composer test
```

## Step 9: Update Documentation References

Update any internal documentation that references:
- Old file paths
- Old class locations
- Old import statements
- Old command-line scripts

## Step 10: Verify Functionality

### Test Node.js App
```bash
cd nodejs-app
npm start
# Test API endpoints
curl http://localhost:3000/health
```

### Test Laravel Package
```bash
php artisan sweeper:install
php artisan sweeper:seed
php artisan sweeper:balance <address> --chain_id=1
```

## Rollback Plan

If you need to rollback:

```bash
# Restore from archive
cp -r archive/original-files/* .

# Or revert git commit
git revert HEAD
```

## Common Issues

### Issue: Module not found errors
**Solution**: Check that you're running commands from correct directory
```bash
cd nodejs-app  # For Node.js
# or
cd laravel-package  # For Laravel
```

### Issue: Database connection failures
**Solution**: Update DATABASE_URL with absolute paths or correct host

### Issue: Import/require errors
**Solution**: Update paths in code to reflect new structure

### Issue: Environment variables not found
**Solution**: Ensure .env file is in correct location (nodejs-app/ or project root)

## Benefits After Migration

✅ **Clear separation** - Node.js and Laravel are independent
✅ **Maintainable** - One class per file, easy to locate code
✅ **Testable** - Proper test structure for both implementations
✅ **Scalable** - Can add features without cluttering
✅ **Professional** - Follows industry standards (PSR-4, npm packages)
✅ **Documented** - Clear README files for each component

## Questions?

- Node.js issues: See `nodejs-app/README.md`
- Laravel issues: See `laravel-package/README.md`
- Structure questions: See `README-NEW-STRUCTURE.md`

## Archive Cleanup (After 30 days)

Once you're confident the new structure works:

```bash
# Remove archive directory
rm -rf archive/

# Commit cleanup
git add -A
git commit -m "Remove archived legacy files"
```
