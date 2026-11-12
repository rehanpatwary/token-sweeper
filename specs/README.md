# Specs

This package uses a short spec-driven loop, matching the harness on `multicoin-backend`:

1. Write or update a spec in `specs/` that describes the behavior, including amounts, failure cases, and the public contract.
2. Add a failing test that names that behavior. Run it and confirm it fails because the behavior is missing.
3. Implement the smallest change that makes the test pass.
4. Refactor only while the tests stay green.

Do not change sweep, gas, signing, or amount behavior without a spec update and a test. Specs describe the contract callers can rely on. They are not a wishlist.

| Spec | What it covers |
| --- | --- |
| [public-api.md](public-api.md) | HTTP routes, the `TokenSweeper` facade, Artisan commands, and service entry points as they behave now |
| [sweep-invariants.md](sweep-invariants.md) | Money-path rules: base-unit amounts, gas funding, signing, idempotency, and failed transactions |

The routes registered by the package are the ones in `php/src/routes/api.php` and `docs/openapi.yaml` (`/api/sweeper/...`).
