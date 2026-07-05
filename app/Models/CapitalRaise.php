<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CapitalRaise extends Model
{
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_PUBLISHED = 'Published';

    public const METHOD_PRO_RATA = 'Pro-rata';
    public const METHOD_CUSTOM = 'Custom Allocation';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
    ];

    public const METHODS = [
        self::METHOD_PRO_RATA,
        self::METHOD_CUSTOM,
    ];

    protected $fillable = [
        'company_id',
        'portfolio_valuation_id',
        'effective_date',
        'raise_amount',
        'participation_method',
        'status',
        'ownership_register_id',
        'created_by',
        'published_by',
        'published_at',
        'generated_transactions',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'raise_amount' => 'decimal:2',
        'published_at' => 'datetime',
        'generated_transactions' => 'array',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function valuation(): BelongsTo
    {
        return $this->belongsTo(PortfolioValuation::class, 'portfolio_valuation_id');
    }

    public function ownershipRegister(): BelongsTo
    {
        return $this->belongsTo(OwnershipRegister::class, 'ownership_register_id');
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(CapitalRaiseContribution::class);
    }
}
