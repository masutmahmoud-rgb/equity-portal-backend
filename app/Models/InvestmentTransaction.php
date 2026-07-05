<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvestmentTransaction extends Model
{
    public const TYPE_INITIAL = 'Initial Investment';
    public const TYPE_ADDITIONAL = 'Additional Investment';
    public const TYPE_INITIAL_CAPITALIZATION = 'Initial Capitalization';
    public const TYPE_CAPITAL_RAISE = 'Capital Raise';

    public const TYPES = [
        self::TYPE_INITIAL,
        self::TYPE_ADDITIONAL,
        self::TYPE_INITIAL_CAPITALIZATION,
        self::TYPE_CAPITAL_RAISE,
    ];

    protected $fillable = [
        'investment_id',
        'transaction_type',
        'source',
        'status',
        'amount',
        'transaction_date',
        'notes',
        'is_read_only',
        'capital_raise_id',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'amount' => 'decimal:2',
        'is_read_only' => 'boolean',
    ];

    /**
     * Get the investment that owns this transaction.
     */
    public function investment(): BelongsTo
    {
        return $this->belongsTo(Investment::class);
    }
}
