<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    public const STATUS_OPERATING = 'Operating';
    public const STATUS_EXITED = 'Exited';
    public const STATUS_PENDING = 'Pending';

    public const STATUSES = [
        self::STATUS_OPERATING,
        self::STATUS_EXITED,
        self::STATUS_PENDING,
    ];

    protected $fillable = [
        'name',
        'industry',
        'total_equity',
        'latest_valuation',
        'base_currency',
        'exchange_rate',
        'status',
        'notes',
    ];

    protected $casts = [
        'total_equity' => 'decimal:2',
        'latest_valuation' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
    ];
}