# Sweep invariants

These rules apply to `SweeperService::processSweep` and to the `FundDepositAddress` / `SweepTokens` jobs.

## Amounts

- Token and native amounts are arbitrary-size integers. The implementation uses `ext-bcmath` and decimal strings. It does not use `hexdec`, `dechex`, or floats for value math.
- A sweep records `pending_sweeps.amount` and `sweep_logs.amount` as the base-unit amount that was transferred, including balances above `PHP_INT_MAX` (any 18-decimal balance of about 9.22 tokens or more).
- The ERC-20 `transfer` data is `balanceOf` at send time, encoded as a 32-byte big-endian integer. The recipient is the chain hot wallet.
- A zero token balance does not broadcast a funding transaction and does not insert a sweep row.

## Gas funding

- The deposit address is funded only when its native balance is strictly below `chains.gas_amount_wei`.
- The value field of that transaction is exactly `gas_amount_wei`, interpreted as a decimal integer when it has no `0x` prefix.
- Funding uses the master wallet. The nonce is `eth_getTransactionCount(address, "pending")`, taken inside a per-chain cache lock. The lock is held for 180 seconds, which covers the RPC calls inside it, and it is released before waiting for the receipt. The native balance is read again inside the lock so a second funder does not send gas that the first funder already sent.
- A funding receipt of `0x0` fails the sweep. The token transfer is not sent.

## Signing

- Legacy transactions are RLP-encoded with EIP-155 for a positive chain id. Integer 0 is the empty RLP string. Integer fields are minimal big-endian bytes with no leading zero bytes, including `v`, `r`, and `s`.
- A deposit address whose nonce is 0 must produce a broadcastable raw transaction. This is the common case for a first sweep.

## Idempotency and retries

- While a row for the same chain, deposit address, and token is `pending`, `funding`, or `sweeping` and was updated within `monitoring.stale_sweep_minutes` (default 15), `processSweep` returns true and does not broadcast another transaction. An older in-progress row is treated as abandoned and is resumed. `CheckPendingSweeps` selects those stale rows as well as failed rows.
- A reverted receipt marks the sweep failed and increments `retry_count` once. It does not raise into the queue, so the worker does not immediately broadcast the same funding or transfer again. Other failures are rethrown for the queue, and `retry_count` increases once when the job has used its attempts.
- A later call reuses the newest `failed` row whose `retry_count` is still below `max_retry_attempts` instead of inserting a second row. Success does not reset `retry_count`. Failure increments it.
- `CheckPendingSweeps` selects failed rows older than `retry_delay` minutes. One throwing retry does not skip the rest of the batch; the first exception is rethrown after the batch.

## Partial failure

- A reverted token transfer (`waitForConfirmation` is false) marks the sweep `failed`, does not write a completed `sweep_logs` row, and does not emit `SweepCompleted`.
- After a confirmed token transfer, sweeping leftover native gas is best-effort. A failure there does not fail the token sweep. The value is `balance - (21000 * gasPrice)` and is sent only when the balance is greater than `1.1` times that gas cost, computed with integer math.
- The hot wallet must be a 20-byte `0x` address before any gas is sent. Otherwise `processSweep` returns false and does not insert a row.

## Not in this contract

See the PR description for behaviors that are intentionally unchanged: dust thresholds, `block_confirmations` greater than one receipt, `eth_estimateGas`, and BIP-44 derivation.
