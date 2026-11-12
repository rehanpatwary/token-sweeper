<?php

namespace Multicoin\TokenSweeper\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $chain_id
 * @property string $symbol
 * @property string $name
 * @property string $contract_address
 * @property int $decimals
 * @property bool $is_active
 */
class Token extends Model
{
    protected $table = 'sweeper_tokens';

    protected $fillable = [
        'chain_id',
        'symbol',
        'name',
        'contract_address',
        'decimals',
        'is_active',
    ];

    protected $casts = [
        'chain_id' => 'integer',
        'decimals' => 'integer',
        'is_active' => 'boolean',
    ];

    public function chain(): BelongsTo
    {
        return $this->belongsTo(Chain::class, 'chain_id', 'chain_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForChain($query, int $chainId)
    {
        return $query->where('chain_id', $chainId);
    }
}
