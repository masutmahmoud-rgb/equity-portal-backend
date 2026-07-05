<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FinancialData extends Model
{
    public const TYPE_PROFIT = 'profit';
    public const TYPE_INDICATIVE_VALUE = 'indicative_value';

    public const TYPES = [
        self::TYPE_PROFIT,
        self::TYPE_INDICATIVE_VALUE,
    ];

    public const HALF_YEARS = ['H1', 'H2'];

    protected $fillable = [
        'type',
        'year',
        'half_year',
        'amount',
        'currency',
        'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'amount' => 'decimal:2',
    ];
}
