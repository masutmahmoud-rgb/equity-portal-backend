<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InitialCapitalizationItem extends Model
{
    protected $fillable = [
        'initial_capitalization_id',
        'investor_id',
        'initial_investment',
        'ownership_percentage',
    ];

    protected $casts = [
        'initial_investment' => 'decimal:2',
        'ownership_percentage' => 'decimal:4',
    ];

    public function capitalization(): BelongsTo
    {
        return $this->belongsTo(InitialCapitalization::class, 'initial_capitalization_id');
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }
}
