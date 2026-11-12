# Contributing

Money-path changes follow the spec-driven loop used by `multicoin-backend`.

## Loop

1. **Spec.** Add or edit a markdown file in [`specs/`](specs/README.md). State the observable behavior: inputs, amounts, and what a failure must not do.
2. **Failing test.** Add a PHPUnit test whose name describes that behavior. Run it and confirm it fails because the behavior is absent, not because of a typo or a missing fixture.
3. **Implementation.** Write the smallest change that makes the test pass. Keep PHP and any Node port of the same rule aligned when both implement it.
4. **Green.** `composer test` passes. `composer analyse` and `composer format:check` pass.

Configuration files, CI, and docs do not need a failing test. Behavior changes do.

## Checks

```bash
composer install
composer validate --strict --no-check-publish
composer analyse
composer format:check
composer test
```

CI runs those checks on push and pull requests. PHPUnit runs on PHP 8.2, 8.3, and 8.4. PHPStan and PHP CS Fixer run on PHP 8.3. A tag named `v*` runs the same checks and does not publish the package.

`ext-bcmath` and `ext-gmp` are required. `gmp` is required by the secp256k1 signer. `bcmath` is required so token amounts are not converted through floats.

## Amounts and keys

- Store and pass token amounts as decimal strings in base units.
- `gas_amount_wei` may be a decimal string or a `0x` hex quantity. Do not treat an unprefixed decimal as hex.
- Do not log private keys or signed-transaction secrets.
- Deposit address lookup is case-insensitive.

## Packages

This repository is the Composer package `multicoin/token-sweeper`. Consumers install it by VCS tag. Do not create tags or publish to Packagist from a pull request. `COMPOSER_AUTH` is only needed in CI when a machine must authenticate to a private Composer endpoint. This package's own dependencies are public; CI validates the secret when it is present and continues without it when it is not.
