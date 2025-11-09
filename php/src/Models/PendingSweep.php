<?php

namespace Multicoin\TokenSweeper\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PendingSweep extends Model
{
    protected $table = 'sweeper_pending_sweeps';

    protected $fillable = [
        'deposit_address',
        'chain_id',
        'token_address',
        'token_symbol',
        'amount',
        'status',
        'funding_tx_hash',
        'sweep_tx_hash',
        'error_message',
        'retry_count',
    ];

    protected $casts = [
        'chain_id' => 'integer',
        'retry_count' => 'integer',
    ];

    public function chain(): BelongsTo
    {
        return $this->belongsTo(Chain::class, 'chain_id', 'chain_id');
    }

    public function depositAddressModel(): BelongsTo
    {
        return $this->belongsTo(DepositAddress::class, 'deposit_address', 'address');
    }

    public function log(): HasOne
    {
        return $this->hasOne(SweepLog::class, 'sweep_id');
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeForChain($query, int $chainId)
    {
        return $query->where('chain_id', $chainId);
    }

    public function markAsCompleted(string $sweepTxHash): void
    {
        $this->update([
            'status' => 'completed',
            'sweep_tx_hash' => $sweepTxHash,
        ]);
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    public function incrementRetry(): void
    {
        $this->increment('retry_count');
    }
}
