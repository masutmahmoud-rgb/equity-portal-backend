<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CapitalRaiseContribution extends Model
{
    protected $fillable = [
        'capital_raise_id',
        'investor_id',
        'contribution_amount',
        'current_ownership_percentage',
        'new_ownership_percentage',
    ];

    protected $casts = [
        'contribution_amount' => 'decimal:2',
        'current_ownership_percentage' => 'decimal:4',
        'new_ownership_percentage' => 'decimal:4',
    ];

    public function capitalRaise(): BelongsTo
    {
        return $this->belongsTo(CapitalRaise::class);
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }
}
