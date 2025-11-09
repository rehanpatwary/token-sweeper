# Token Sweeper Documentation

Comprehensive documentation for the Multi-Chain Token Sweeper system.

---

## Documentation Structure

The documentation is organized into three main categories:

### 📘 Guides
Step-by-step tutorials and user guides for common tasks.

### 🏗️ Architecture
Technical architecture documentation, design patterns, and system design.

### 📋 Reference
Reports, specifications, and reference materials.

---

## Getting Started

### New to Token Sweeper?

Start here for a quick introduction and setup:

1. **[Quick Start Guide](guides/QUICK-START.md)** - Get up and running in 5 minutes
2. **[Developer Setup](guides/DEVELOPER-SETUP.md)** - Complete development environment setup
3. **[API Usage Guide](guides/API-GUIDE.md)** - Learn how to integrate the API

### For Developers

**Setting Up Your Environment:**
- [Developer Setup Guide](guides/DEVELOPER-SETUP.md) - Prerequisites, installation, configuration
- [Git Workflow](guides/GIT-WORKFLOW.md) - Git Flow branching model and commit conventions

**Building with Token Sweeper:**
- [API Usage Guide](guides/API-GUIDE.md) - Complete API integration examples
- [Architecture Overview](architecture/ARCHITECTURE.md) - Understand system design
- [OpenAPI Specification](openapi.yaml) - Machine-readable API specification

**Testing and Validation:**
- [Test Report](reference/TEST-REPORT.md) - Test coverage and results
- [Verification Report](reference/VERIFICATION-REPORT.md) - Validation status

---

## Documentation Index

### 📘 Guides

| Document | Description |
|----------|-------------|
| **[Quick Start](guides/QUICK-START.md)** | Get started in 5 minutes with minimal setup |
| **[Developer Setup](guides/DEVELOPER-SETUP.md)** | Complete development environment configuration |
| **[API Usage Guide](guides/API-GUIDE.md)** | Integration examples with PHP, JavaScript, Python, cURL |
| **[Git Workflow](guides/GIT-WORKFLOW.md)** | Git Flow branching strategy and commit conventions |

### 🏗️ Architecture

| Document | Description |
|----------|-------------|
| **[Architecture Overview](architecture/ARCHITECTURE.md)** | System architecture, design patterns, and diagrams |

### 📋 Reference

| Document | Description |
|----------|-------------|
| **[Test Report](reference/TEST-REPORT.md)** | Test suite coverage and results (248 tests) |
| **[Verification Report](reference/VERIFICATION-REPORT.md)** | Codebase validation and verification status |
| **[Complete Package Report](reference/COMPLETE-PACKAGE-REPORT.md)** | Full project overview and implementation details |
| **[Migration Report](reference/MONOREPO-MIGRATION-REPORT.md)** | Migration history and architectural changes |

### 🌐 API Documentation

| Resource | Description |
|----------|-------------|
| **[OpenAPI Specification](openapi.yaml)** | Complete API specification in OpenAPI 3.0 format |
| **[Interactive API Docs](api/index.html)** | Swagger UI for testing and exploring endpoints |

---

## Quick Navigation

### Common Tasks

**Generate a Deposit Address**
- PHP: [API Guide - Deposit Generation](guides/API-GUIDE.md#generate-deposit-address)
- CLI: `php artisan sweeper:generate-address {user_id} {chain_id}`

**Monitor Token Sweeps**
- CLI: `php artisan sweeper:monitor`
- API: [Sweep Status Endpoint](guides/API-GUIDE.md#check-sweep-status)

**Check Sweep History**
- API: [Sweep Logs Endpoint](guides/API-GUIDE.md#get-sweep-history)

**Add a New Blockchain**
- Guide: [Architecture - Multi-Chain Configuration](architecture/ARCHITECTURE.md#multi-chain-configuration)
- Reference: [CLAUDE.md - Adding Blockchains](../CLAUDE.md#adding-a-new-blockchain)

**Debug Failed Sweeps**
- Guide: [Developer Setup - Troubleshooting](guides/DEVELOPER-SETUP.md#troubleshooting)
- Reference: [Test Report - Common Issues](reference/TEST-REPORT.md)

### By Role

**Backend Developers**
→ [Developer Setup](guides/DEVELOPER-SETUP.md) → [Architecture](architecture/ARCHITECTURE.md) → [API Guide](guides/API-GUIDE.md)

**API Integrators**
→ [Quick Start](guides/QUICK-START.md) → [API Guide](guides/API-GUIDE.md) → [OpenAPI Spec](openapi.yaml)

**DevOps Engineers**
→ [Developer Setup - Deployment](guides/DEVELOPER-SETUP.md#production-deployment) → [Architecture - Deployment](architecture/ARCHITECTURE.md#deployment-architecture)

**QA Engineers**
→ [Test Report](reference/TEST-REPORT.md) → [Verification Report](reference/VERIFICATION-REPORT.md)

---

## Using the Documentation

### View Documentation Locally

**Serve API Documentation:**
```bash
# Using Python
cd docs/api
python3 -m http.server 8080

# Using Node.js
npx http-server docs/api -p 8080

# Using PHP
cd docs/api
php -S localhost:8080
```

Then visit: http://localhost:8080

### Generate API Clients

Use the OpenAPI specification to generate client libraries:

**TypeScript/JavaScript:**
```bash
npx @openapitools/openapi-generator-cli generate \
  -i docs/openapi.yaml \
  -g typescript-axios \
  -o generated/typescript-client
```

**Python:**
```bash
openapi-generator-cli generate \
  -i docs/openapi.yaml \
  -g python \
  -o generated/python-client
```

**PHP:**
```bash
openapi-generator-cli generate \
  -i docs/openapi.yaml \
  -g php \
  -o generated/php-client
```

### Validate OpenAPI Specification

```bash
# Using Redocly CLI
npx @redocly/cli lint docs/openapi.yaml

# Using Swagger CLI
npx swagger-cli validate docs/openapi.yaml
```

---

## Documentation Standards

### File Organization

```
docs/
├── guides/              # Step-by-step tutorials and user guides
│   ├── QUICK-START.md
│   ├── DEVELOPER-SETUP.md
│   ├── API-GUIDE.md
│   └── GIT-WORKFLOW.md
│
├── architecture/        # Technical architecture and design
│   └── ARCHITECTURE.md
│
├── reference/          # Reference materials and reports
│   ├── TEST-REPORT.md
│   ├── VERIFICATION-REPORT.md
│   ├── COMPLETE-PACKAGE-REPORT.md
│   └── MONOREPO-MIGRATION-REPORT.md
│
├── api/                # Interactive API documentation
│   └── index.html
│
├── openapi.yaml        # API specification
└── README.md           # This file
```

### Writing Guidelines

**When contributing documentation:**

1. **Use clear, concise language** - Technical but accessible
2. **Include code examples** - Show don't tell
3. **Add diagrams where helpful** - Use Mermaid for diagrams
4. **Follow existing formatting** - Maintain consistency
5. **Test all code examples** - Ensure they work
6. **Update the index** - Add new docs to this README

### Mermaid Diagrams

All architecture diagrams use Mermaid format and render natively on GitHub. View them in:
- GitHub (native rendering)
- VS Code (with Mermaid extension)
- [Mermaid Live Editor](https://mermaid.live)

---

## Frequently Asked Questions

### How do I get started quickly?
See the [Quick Start Guide](guides/QUICK-START.md) for a 5-minute setup.

### How do I integrate the API into my application?
Follow the [API Usage Guide](guides/API-GUIDE.md) with examples in multiple languages.

### Where can I find the database schema?
See [Architecture - Database Schema](architecture/ARCHITECTURE.md#database-schema) for ERD diagrams.

### How do I add support for a new blockchain?
See [CLAUDE.md - Adding a New Blockchain](../CLAUDE.md#adding-a-new-blockchain).

### What's the test coverage?
See [Test Report](reference/TEST-REPORT.md) - 217/248 tests passing (87.5%).

### How do I debug failed sweeps?
See [Developer Setup - Troubleshooting](guides/DEVELOPER-SETUP.md#troubleshooting).

---

## Contributing to Documentation

We welcome documentation improvements!

### How to Contribute

1. Fork the repository
2. Create a feature branch: `git checkout -b docs/improve-api-guide`
3. Make your changes
4. Test documentation locally
5. Commit: `git commit -m 'docs: Improve API guide examples'`
6. Push: `git push origin docs/improve-api-guide`
7. Open a Pull Request

### Reporting Issues

Found an error or unclear section?

1. Open an issue on [GitHub Issues](https://github.com/multicoin/token-sweeper/issues)
2. Tag it with `documentation` label
3. Describe the issue and suggest improvements

---

## Related Documentation

### Project Documentation
- **[Project README](../README.md)** - Main project overview
- **[CLAUDE.md](../CLAUDE.md)** - AI assistant instructions and project conventions

### Implementation Documentation
- **PHP Source**: `php/src/` - PHPDoc comments in source files
- **Node.js Source**: `nodejs/src/` - JSDoc comments in source files

---

## Version Information

**Documentation Version:** 1.0.0
**Last Updated:** January 2025
**Maintained by:** Multicoin Team

---

## Quick Links

- 🏠 [Project Home](../README.md)
- 🚀 [Quick Start](guides/QUICK-START.md)
- 🔧 [Developer Setup](guides/DEVELOPER-SETUP.md)
- 📖 [API Guide](guides/API-GUIDE.md)
- 🏗️ [Architecture](architecture/ARCHITECTURE.md)
- 🌐 [Interactive API Docs](api/index.html)
- 📄 [OpenAPI Spec](openapi.yaml)
- 🤖 [CLAUDE.md](../CLAUDE.md)

---

**Need Help?** Check the guides above or open an issue on GitHub.
