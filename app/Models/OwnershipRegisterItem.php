<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OwnershipRegisterItem extends Model
{
    protected $fillable = [
        'ownership_register_id',
        'investor_id',
        'ownership_percentage',
    ];

    protected $casts = [
        'ownership_percentage' => 'decimal:4',
    ];

    public function register(): BelongsTo
    {
        return $this->belongsTo(OwnershipRegister::class, 'ownership_register_id');
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }
}
