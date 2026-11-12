# Public API

Amounts in this API are base-unit decimal strings (wei, or token units as returned by `balanceOf`). They are not floats and they are not already-formatted token amounts.

## HTTP

Prefix: `/api/sweeper`. Middleware: Laravel `api`.

| Method | Path | Behavior |
| --- | --- | --- |
| GET | `/health` | `{ "status": "ok", "timestamp": ISO-8601 }` |
| GET | `/chains` | `{ "success": true, "data": [id, chain_id, name, native_symbol, is_active] }` |
| GET | `/tokens` | Active tokens. Optional `chain_id` query filter. |
| POST | `/deposit-address` | Body `user_id` (int) and `chain_id` (int, must exist). Returns 201 with the address. The same user and chain returns the existing address. |
| GET | `/user-addresses` | Query `user_id` (int). |
| GET | `/sweep-status` | Query `address` and `chain_id`. Latest 10 sweeps for that pair. |
| GET | `/pending-sweeps` | Query `status` (default `pending`) and optional `chain_id`. Latest 50. |
| GET | `/sweep-logs` | Query `limit` (default 50, max 100) and optional `chain_id`. |
| POST | `/process-sweep` | Body `deposit_address`, `token_address`, `chain_id`. `200` when `SweeperService::processSweep` returns true, `500` when it returns false. |

Address matching for keys and in-flight sweeps is case-insensitive. Callers may send EIP-55 checksums.

## Facade

`Multicoin\TokenSweeper\Facades\TokenSweeper` resolves the `token-sweeper` container binding (`TokenSweeperManager`).

| Method | Delegates to |
| --- | --- |
| `createDepositAddress(int $userId, int $chainId): string` | `WalletService` |
| `getUserDepositAddresses(int $userId)` | `WalletService` |
| `generateWallet(): array` | `WalletService` (`address`, `privateKey`) |
| `getPrivateKey(string $address, int $chainId): string` | `WalletService` |
| `processSweep(string $depositAddress, string $tokenAddress, int $chainId): bool` | `SweeperService` |

`true` means the sweep completed, or an equivalent sweep is already `pending`, `funding`, or `sweeping`. `false` means this call failed. A failed call is safe to retry until `monitoring.max_retry_attempts`.

## Artisan

| Command | Behavior |
| --- | --- |
| `sweeper:install` | Publishes `token-sweeper-config` and runs migrations. |
| `sweeper:seed` | Upserts `default_chains` and `default_tokens`. |
| `sweeper:generate-address {user_id} {chain_id}` | Creates or returns a deposit address. |
| `sweeper:monitor` | Polls active chains. The loop sleeps `monitoring.check_interval` seconds. |
| `sweeper:sweep {address} {token} {chain_id}` | Runs `processSweep`. Exit 0 on true, 1 on false. |
| `sweeper:balance {address} {token?} {--chain_id=}` | Reads balances through the RPC client. |
| `sweeper:pending {--status=pending}` | Prints up to 20 sweeps, newest `created_at` first. |

## Services used by the backend

- `SweeperService::processSweep(string $depositAddress, string $tokenAddress, int $chainId): bool`
- `WalletService::createDepositAddress`, `getPrivateKey`, `getMasterWallet`, `generateWallet`
- `Web3Service` JSON-RPC wrapper. `getTransactionCount` asks for the `pending` nonce. `waitForConfirmation` returns true only for receipt status `0x1`, false for `0x0`, and throws on timeout.
- `TransactionSignerService::signTransaction(array $transaction, string $privateKey, int $chainId): string` returns a legacy EIP-155 raw transaction.

Queue jobs `FundDepositAddress`, `SweepTokens`, and `CheckPendingSweeps` follow the same amount, gas, and confirmation rules as `SweeperService`.

## Configuration that changes behavior

`gas_amount_wei` is an integer quantity. Both decimal strings (`"2000000000000000"`) and `0x` hex strings are accepted. Seed data uses decimal wei.

`monitoring.retry_delay` is minutes. A null or non-numeric value uses 60. `monitoring.max_retry_attempts` defaults to 3 the same way.

`monitoring.post_funding_delay_seconds` defaults to 2 and is the pause after a funding transaction before the token transfer. Tests set it to 0.

`monitoring.block_confirmations` is stored but not read. Confirmation today means a mined receipt.
