# Git Workflow Guide

This project follows the **Git Flow** branching model for organized development and releases.

## Branch Structure

### Main Branches

- **`main`** (or `master`) - Production-ready code only
  - Contains stable, released versions
  - Protected branch (requires pull requests)
  - Tagged with version numbers (v1.0.0, v1.1.0, etc.)

- **`develop`** - Integration branch for features
  - Default development branch
  - Contains latest development changes
  - Features are merged here first

### Supporting Branches

- **`feature/*`** - New features and enhancements
  - Branch from: `develop`
  - Merge back to: `develop`
  - Naming: `feature/add-new-chain`, `feature/improve-gas-estimation`

- **`release/*`** - Preparing production releases
  - Branch from: `develop`
  - Merge to: `main` and `develop`
  - Naming: `release/1.0.0`, `release/1.1.0`

- **`hotfix/*`** - Critical production fixes
  - Branch from: `main`
  - Merge to: `main` and `develop`
  - Naming: `hotfix/fix-sweep-bug`, `hotfix/security-patch`

## Common Workflows

### Starting a New Feature

```bash
# Update develop branch
git checkout develop
git pull origin develop

# Create feature branch
git checkout -b feature/add-polygon-support

# Work on your feature...
git add .
git commit -m "feat: Add Polygon network support"

# Push feature branch
git push -u origin feature/add-polygon-support

# Create Pull Request on GitHub
# Target: develop branch
```

### Completing a Feature

```bash
# Update develop and rebase
git checkout develop
git pull origin develop
git checkout feature/add-polygon-support
git rebase develop

# Merge to develop (after PR approval)
git checkout develop
git merge --no-ff feature/add-polygon-support

# Delete feature branch
git branch -d feature/add-polygon-support
git push origin --delete feature/add-polygon-support

# Push updated develop
git push origin develop
```

### Creating a Release

```bash
# Create release branch from develop
git checkout develop
git pull origin develop
git checkout -b release/1.1.0

# Update version numbers in package.json, composer.json, etc.
# Update CHANGELOG.md

git commit -am "chore: Bump version to 1.1.0"
git push -u origin release/1.1.0

# After testing and approval, merge to main
git checkout main
git merge --no-ff release/1.1.0
git tag -a v1.1.0 -m "Release version 1.1.0"
git push origin main --tags

# Merge back to develop
git checkout develop
git merge --no-ff release/1.1.0
git push origin develop

# Delete release branch
git branch -d release/1.1.0
git push origin --delete release/1.1.0
```

### Creating a Hotfix

```bash
# Create hotfix branch from main
git checkout main
git pull origin main
git checkout -b hotfix/fix-critical-bug

# Fix the bug
git commit -am "fix: Resolve critical sweep failure"

# Test thoroughly!

# Merge to main
git checkout main
git merge --no-ff hotfix/fix-critical-bug
git tag -a v1.0.1 -m "Hotfix version 1.0.1"
git push origin main --tags

# Merge to develop
git checkout develop
git merge --no-ff hotfix/fix-critical-bug
git push origin develop

# Delete hotfix branch
git branch -d hotfix/fix-critical-bug
git push origin --delete hotfix/fix-critical-bug
```

## Commit Message Convention

We follow the **Conventional Commits** specification:

### Format

```
<type>(<scope>): <subject>

<body>

<footer>
```

### Types

- **feat**: New feature
- **fix**: Bug fix
- **docs**: Documentation changes
- **style**: Code style changes (formatting, missing semicolons, etc.)
- **refactor**: Code refactoring (no feature change, no bug fix)
- **perf**: Performance improvements
- **test**: Adding or updating tests
- **chore**: Maintenance tasks, dependency updates
- **ci**: CI/CD configuration changes
- **build**: Build system or external dependencies

### Examples

```bash
# Feature
git commit -m "feat(wallet): Add BIP-44 derivation support"

# Bug fix
git commit -m "fix(sweeper): Resolve gas estimation issue on BSC"

# Documentation
git commit -m "docs(api): Add OpenAPI specification"

# Refactoring
git commit -m "refactor(services): Extract Web3Service interface"

# With body and breaking change
git commit -m "feat(api): Change deposit address endpoint format

BREAKING CHANGE: The /deposit-address endpoint now requires chain_id
as a required parameter instead of optional.

Migration guide:
- Old: POST /deposit-address {user_id: 123}
- New: POST /deposit-address {user_id: 123, chain_id: 1}
"
```

## Pull Request Guidelines

### Creating a Pull Request

1. **Title**: Use conventional commit format
   - ✅ `feat: Add Arbitrum support`
   - ❌ `Added arbitrum`

2. **Description**: Include:
   - What changed and why
   - How to test the changes
   - Screenshots (if UI changes)
   - Related issues (fixes #123)

3. **Checklist**:
   - [ ] Code follows project style guide
   - [ ] Tests added/updated and passing
   - [ ] Documentation updated
   - [ ] No breaking changes (or documented if necessary)
   - [ ] Commit messages follow conventions

### Example PR Template

```markdown
## Description
Add support for Arbitrum One network (Chain ID 42161).

## Changes
- Added Arbitrum configuration to token-sweeper.php
- Updated RPC URL environment variables
- Added Arbitrum to default chains seed
- Updated documentation

## Testing
- [ ] Generated deposit address on Arbitrum
- [ ] Successfully funded address with ETH
- [ ] Swept tokens to hot wallet
- [ ] All tests passing (248/248)

## Screenshots
N/A

Fixes #45
```

## Branch Protection Rules

**For `main` branch:**
- Require pull request reviews (at least 1)
- Require status checks to pass (CI tests)
- No direct pushes (except for emergencies)
- Require linear history (no merge commits from PRs)

**For `develop` branch:**
- Require pull request for features
- Require status checks to pass
- Allow fast-forward merges

## Version Tagging

Follow **Semantic Versioning** (SemVer):

```
MAJOR.MINOR.PATCH
```

- **MAJOR**: Breaking changes (v2.0.0)
- **MINOR**: New features, backward compatible (v1.1.0)
- **PATCH**: Bug fixes, backward compatible (v1.0.1)

### Creating Tags

```bash
# Annotated tag (recommended)
git tag -a v1.0.0 -m "Release version 1.0.0"

# List tags
git tag -l

# Push tags
git push origin --tags

# Delete tag
git tag -d v1.0.0
git push origin :refs/tags/v1.0.0
```

## Current Repository Status

```
Repository: git@github.com:rehanpatwary/-eth-sweeper-for-token.git

Branches:
├── main (production)
│   └── v1.0.0 (Initial release)
│
└── develop (development)
    └── Latest: Documentation suite

Active Features:
- None (ready for development)

Latest Release:
- v1.0.0 - Initial release with full functionality
```

## Quick Reference

```bash
# Clone repository
git clone git@github.com:rehanpatwary/-eth-sweeper-for-token.git
cd -eth-sweeper-for-token

# Start new feature
git checkout develop
git pull origin develop
git checkout -b feature/my-feature

# Commit changes
git add .
git commit -m "feat(scope): description"

# Push and create PR
git push -u origin feature/my-feature
# Then create PR on GitHub targeting develop

# Update your branch with latest develop
git fetch origin
git rebase origin/develop

# View branch graph
git log --oneline --graph --all --decorate

# Check current branch and status
git status
git branch -vv
```

## Resources

- [Git Flow Cheatsheet](https://danielkummer.github.io/git-flow-cheatsheet/)
- [Conventional Commits](https://www.conventionalcommits.org/)
- [Semantic Versioning](https://semver.org/)
- [GitHub Flow Guide](https://guides.github.com/introduction/flow/)

## Tips

1. **Always pull before creating branches**
   ```bash
   git checkout develop
   git pull origin develop
   ```

2. **Keep commits atomic** - One logical change per commit

3. **Write descriptive commit messages** - Future you will thank you

4. **Test before pushing** - Run `composer test` and `npm test`

5. **Rebase instead of merge** for cleaner history
   ```bash
   git rebase origin/develop
   ```

6. **Use `.gitignore`** - Never commit secrets or generated files

7. **Keep branches up-to-date** - Regularly rebase on develop

8. **Delete merged branches** - Keep repository clean

---

**Questions?** Open an issue or check the [Developer Setup Guide](DEVELOPER-SETUP.md)
