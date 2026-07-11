<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdditionalInvestment extends Model
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
        'investor_id',
        'valuation_year',
        'valuation_half',
        'card_label',
        'investment_amount',
        'profit',
        'status',
        'notes',
    ];

    protected $casts = [
        'valuation_year' => 'integer',
        'investment_amount' => 'decimal:2',
        'profit' => 'decimal:2',
    ];

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    public function getValuationPeriodAttribute(): string
    {
        return sprintf('%d-%s', (int) $this->valuation_year, (string) $this->valuation_half);
    }
}
