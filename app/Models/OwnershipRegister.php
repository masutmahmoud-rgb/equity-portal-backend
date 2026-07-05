<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OwnershipRegister extends Model
{
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_PUBLISHED = 'Published';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
    ];

    protected $fillable = [
        'company_id',
        'portfolio_valuation_id',
        'effective_date',
        'status',
        'version',
        'is_current',
        'published_at',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'is_current' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function valuation(): BelongsTo
    {
        return $this->belongsTo(PortfolioValuation::class, 'portfolio_valuation_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OwnershipRegisterItem::class);
    }
}
