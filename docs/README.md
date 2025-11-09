# Token Sweeper Documentation

Comprehensive documentation for the Multi-chain Token Sweeping System.

## 📚 Documentation Index

### Quick Start
- **[Developer Setup Guide](./DEVELOPER-SETUP.md)** - Complete setup instructions for PHP/Laravel and Node.js development environments
- **[Quick Start (Root)](../QUICK-START.md)** - Get started in 5 minutes
- **[API Usage Guide](./API-GUIDE.md)** - Code examples and integration patterns

### Architecture & Design
- **[Architecture Overview](./ARCHITECTURE.md)** - System architecture, diagrams, and design patterns
- **[OpenAPI Specification](./openapi.yaml)** - Complete API specification in OpenAPI 3.0 format
- **[Interactive API Docs](./api/index.html)** - Swagger UI for testing and exploring the API

### Reference
- **[CLAUDE.md](../CLAUDE.md)** - Project structure and coding conventions for AI assistance
- **[Migration Guide](../MIGRATION-GUIDE.md)** - Migrating from older versions
- **[Verification Report](../VERIFICATION-REPORT.md)** - Test coverage and verification status

## 🚀 Quick Navigation

### For Developers

**Just Getting Started?**
1. Start with [Developer Setup Guide](./DEVELOPER-SETUP.md)
2. Read [Quick Start](../QUICK-START.md) for a working example
3. Review [API Usage Guide](./API-GUIDE.md) for integration patterns

**Need to Integrate the API?**
1. Check [OpenAPI Specification](./openapi.yaml) for complete API reference
2. Use [Interactive API Docs](./api/index.html) to test endpoints
3. Follow examples in [API Usage Guide](./API-GUIDE.md)

**Understanding the System?**
1. Read [Architecture Overview](./ARCHITECTURE.md) for system design
2. Study the diagrams for visual understanding
3. Review [CLAUDE.md](../CLAUDE.md) for project conventions

### For API Users

**Browse Documentation:**
- 📖 [API Usage Guide](./API-GUIDE.md) - Complete guide with code examples
- 🔧 [Interactive API Docs](./api/index.html) - Try the API in your browser
- 📄 [OpenAPI Spec](./openapi.yaml) - Machine-readable API specification

**Common Tasks:**
- [Generate Deposit Address](./API-GUIDE.md#generate-deposit-address)
- [Check Sweep Status](./API-GUIDE.md#check-sweep-status)
- [List Supported Chains](./API-GUIDE.md#list-supported-chains)
- [Get Sweep History](./API-GUIDE.md#get-sweep-history)

## 📖 Documentation Overview

### Developer Setup Guide

**File:** [DEVELOPER-SETUP.md](./DEVELOPER-SETUP.md)

Complete setup instructions for local development environment:

- Prerequisites and installation
- PHP/Laravel configuration
- Node.js configuration
- Database setup (PostgreSQL, MySQL, Redis)
- Environment variables
- Running tests
- Troubleshooting

**Start here if:** You're setting up the project for the first time or encountering environment issues.

### API Usage Guide

**File:** [API-GUIDE.md](./API-GUIDE.md)

Comprehensive API integration guide with code examples:

- Authentication
- All API endpoints explained
- PHP, JavaScript, Python, and cURL examples
- Error handling
- Best practices
- Complete integration examples

**Start here if:** You're integrating the Token Sweeper API into your application.

### Architecture Documentation

**File:** [ARCHITECTURE.md](./ARCHITECTURE.md)

Deep dive into system architecture and design:

- System overview diagram
- Sweep workflow sequence
- Component architecture
- Database schema with ER diagrams
- Service layer architecture
- Security architecture
- Deployment architecture
- Technology stack
- Design patterns
- Scalability considerations

**Start here if:** You want to understand how the system works internally or plan to extend functionality.

### OpenAPI Specification

**File:** [openapi.yaml](./openapi.yaml)

Machine-readable API specification in OpenAPI 3.0 format:

- All endpoints documented
- Request/response schemas
- Authentication requirements
- Error responses
- Code examples in multiple languages

**Use this for:**
- Generating API clients
- API testing tools (Postman, Insomnia)
- Documentation generation
- Contract testing

### Interactive API Documentation

**File:** [api/index.html](./api/index.html)

Swagger UI-based interactive documentation:

- Browse all endpoints
- Try API calls directly from the browser
- View request/response examples
- Test authentication
- Export API requests

**Access it by:** Opening `docs/api/index.html` in your browser or serving it with a local HTTP server.

## 🎯 Use Cases & Examples

### Use Case 1: Integrate Token Deposits into Your App

**Goal:** Allow users to deposit ERC-20 tokens into your application.

**Steps:**
1. Read [API Usage Guide - Generate Deposit Address](./API-GUIDE.md#generate-deposit-address)
2. Implement deposit address generation for your users
3. Monitor sweep status using the API
4. Credit user accounts when sweeps complete

**Code Example:** See [Complete Integration Example](./API-GUIDE.md#complete-integration-example-php-laravel)

### Use Case 2: Build a Custom Monitoring Dashboard

**Goal:** Create a dashboard to monitor token sweeps across chains.

**Steps:**
1. Review [Architecture - System Overview](./ARCHITECTURE.md#system-overview)
2. Use [Get Pending Sweeps](./API-GUIDE.md#get-pending-sweeps) endpoint
3. Use [Get Sweep History](./API-GUIDE.md#get-sweep-history) endpoint
4. Implement WebSocket updates for real-time monitoring

**API Endpoints:**
- `GET /pending-sweeps` - Active sweeps
- `GET /sweep-logs` - Historical data
- `GET /sweep-status` - Address-specific status

### Use Case 3: Add a New Blockchain Network

**Goal:** Extend support to a new EVM-compatible chain.

**Steps:**
1. Read [Architecture - Multi-Chain Configuration](./ARCHITECTURE.md#multi-chain-configuration)
2. Add chain configuration to `config/token-sweeper.php`
3. Update environment variables with RPC URL
4. Run `php artisan sweeper:seed` to add chain
5. Update both PHP and Node.js implementations

**References:**
- [CLAUDE.md - Adding a New Blockchain](../CLAUDE.md#adding-a-new-blockchain)
- [Developer Setup - Database Configuration](./DEVELOPER-SETUP.md#database-configuration)

### Use Case 4: Debug Failed Sweeps

**Goal:** Investigate and fix failed token sweeps.

**Steps:**
1. Check [API-GUIDE - Error Handling](./API-GUIDE.md#error-handling)
2. Query `GET /pending-sweeps?status=failed`
3. Review error messages in response
4. Check [Developer Setup - Troubleshooting](./DEVELOPER-SETUP.md#troubleshooting)

**Common Issues:**
- Insufficient gas in master wallet
- RPC connection failures
- Invalid token contract addresses
- Network congestion

## 🛠 Development Tools

### View API Documentation Locally

**Option 1: Simple HTTP Server**
```bash
cd docs/api
python3 -m http.server 8080
# Visit: http://localhost:8080
```

**Option 2: Using npx**
```bash
npx http-server docs/api -p 8080
# Visit: http://localhost:8080
```

**Option 3: Using PHP**
```bash
cd docs/api
php -S localhost:8080
# Visit: http://localhost:8080
```

### Generate API Client

Use the OpenAPI spec to generate client libraries:

**JavaScript/TypeScript:**
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

### Validate OpenAPI Spec

```bash
# Using Redocly CLI
npx @redocly/cli lint docs/openapi.yaml

# Using Swagger CLI
npx swagger-cli validate docs/openapi.yaml
```

### Generate Documentation Site

```bash
# Using Redoc
npx @redocly/cli build-docs docs/openapi.yaml \
  -o docs/api/redoc.html

# Using Swagger UI
npx swagger-ui-cli bundle docs/openapi.yaml \
  -o docs/api/swagger.html
```

## 📊 Diagrams

All architecture diagrams are written in Mermaid format and can be viewed in:

- GitHub (native rendering)
- VS Code (with Mermaid extension)
- [Mermaid Live Editor](https://mermaid.live)
- Most modern markdown viewers

**Key Diagrams:**
- [System Overview](./ARCHITECTURE.md#system-overview)
- [Sweep Workflow Sequence](./ARCHITECTURE.md#sweep-workflow)
- [Component Architecture](./ARCHITECTURE.md#component-architecture)
- [Database Schema](./ARCHITECTURE.md#database-schema)
- [Service Layer](./ARCHITECTURE.md#service-layer-architecture)
- [Security Architecture](./ARCHITECTURE.md#security-architecture)

## 🔍 Search Documentation

### Find by Topic

**Authentication:**
- [API Guide - Authentication](./API-GUIDE.md#authentication)
- [OpenAPI - Security Schemes](./openapi.yaml)

**Configuration:**
- [Developer Setup - Environment Variables](./DEVELOPER-SETUP.md#environment-variables)
- [CLAUDE.md - Configuration](../CLAUDE.md#configuration--environment)

**Testing:**
- [Developer Setup - Running Tests](./DEVELOPER-SETUP.md#running-tests)
- [CLAUDE.md - Testing Strategy](../CLAUDE.md#testing-strategy)

**Deployment:**
- [Architecture - Deployment](./ARCHITECTURE.md#deployment-architecture)
- [Developer Setup - Production Deployment](./DEVELOPER-SETUP.md#production-deployment)

**Troubleshooting:**
- [Developer Setup - Troubleshooting](./DEVELOPER-SETUP.md#troubleshooting)
- [API Guide - Error Handling](./API-GUIDE.md#error-handling)

## 🤝 Contributing

### Documentation Contributions

We welcome documentation improvements! To contribute:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test documentation locally
5. Submit a pull request

**Documentation Standards:**
- Use clear, concise language
- Include code examples
- Add diagrams where helpful
- Follow existing formatting
- Update table of contents

### Reporting Documentation Issues

Found an error or unclear section?

1. Open an issue at https://github.com/multicoin/token-sweeper/issues
2. Tag it with `documentation` label
3. Describe the issue and suggest improvements

## 📝 License

This documentation is part of the Token Sweeper project and is licensed under the MIT License.

---

## Quick Links

- 🏠 [Project README](../readme.md)
- 🚀 [Quick Start](../QUICK-START.md)
- 🔧 [Developer Setup](./DEVELOPER-SETUP.md)
- 📖 [API Guide](./API-GUIDE.md)
- 🏗 [Architecture](./ARCHITECTURE.md)
- 🌐 [Interactive API Docs](./api/index.html)
- 📄 [OpenAPI Spec](./openapi.yaml)

---

**Last Updated:** January 2025
**Version:** 1.0.0
**Maintained by:** Multicoin Team
