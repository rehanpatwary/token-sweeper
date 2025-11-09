<?php

namespace Multicoin\TokenSweeper\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Chain extends Model
{
    protected $table = 'sweeper_chains';

    protected $fillable = [
        'chain_id',
        'name',
        'rpc_url',
        'master_wallet_address',
        'master_private_key_encrypted',
        'hot_wallet_address',
        'native_symbol',
        'gas_amount_wei',
        'gas_limit_token_transfer',
        'is_active',
    ];

    protected $casts = [
        'chain_id' => 'integer',
        'gas_limit_token_transfer' => 'integer',
        'is_active' => 'boolean',
    ];

    public function tokens(): HasMany
    {
        return $this->hasMany(Token::class, 'chain_id', 'chain_id');
    }

    public function depositAddresses(): HasMany
    {
        return $this->hasMany(DepositAddress::class, 'chain_id', 'chain_id');
    }

    public function pendingSweeps(): HasMany
    {
        return $this->hasMany(PendingSweep::class, 'chain_id', 'chain_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
