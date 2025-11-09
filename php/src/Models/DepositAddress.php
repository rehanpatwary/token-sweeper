<?php

namespace Multicoin\TokenSweeper\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DepositAddress extends Model
{
    protected $table = 'sweeper_deposit_addresses';

    protected $fillable = [
        'user_id',
        'chain_id',
        'address',
        'private_key_encrypted',
        'last_sweep_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'chain_id' => 'integer',
        'last_sweep_at' => 'datetime',
    ];

    protected $hidden = [
        'private_key_encrypted',
    ];

    public function chain(): BelongsTo
    {
        return $this->belongsTo(Chain::class, 'chain_id', 'chain_id');
    }

    public function pendingSweeps(): HasMany
    {
        return $this->hasMany(PendingSweep::class, 'deposit_address', 'address');
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForChain($query, int $chainId)
    {
        return $query->where('chain_id', $chainId);
    }
}
