<?php

namespace Multicoin\TokenSweeper\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SweepLog extends Model
{
    protected $table = 'sweeper_logs';

    protected $fillable = [
        'sweep_id',
        'chain_id',
        'deposit_address',
        'token_address',
        'amount',
        'funding_tx_hash',
        'sweep_tx_hash',
        'gas_used',
        'status',
    ];

    protected $casts = [
        'sweep_id' => 'integer',
        'chain_id' => 'integer',
    ];

    public function sweep(): BelongsTo
    {
        return $this->belongsTo(PendingSweep::class, 'sweep_id');
    }

    public function chain(): BelongsTo
    {
        return $this->belongsTo(Chain::class, 'chain_id', 'chain_id');
    }

    public function scopeForChain($query, int $chainId)
    {
        return $query->where('chain_id', $chainId);
    }

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'completed');
    }
}
