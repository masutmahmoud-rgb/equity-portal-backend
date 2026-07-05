<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortfolioValuation extends Model
{
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_PUBLISHED = 'Published';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
    ];

    public const HALF_H1 = 'H1';
    public const HALF_H2 = 'H2';

    public const HALVES = [
        self::HALF_H1,
        self::HALF_H2,
    ];

    protected $fillable = [
        'company_id',
        'investor_id',
        'valuation_year',
        'valuation_half',
        'valuation_period',
        'indicative_value',
        'profit',
        'valuation_date',
        'notes',
        'status',
    ];

    protected $casts = [
        'valuation_year' => 'integer',
        'indicative_value' => 'decimal:2',
        'profit' => 'decimal:2',
        'valuation_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }
}
