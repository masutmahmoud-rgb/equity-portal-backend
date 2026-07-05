<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Dividend extends Model
{
    public const STATUS_PENDING = 'Pending';
    public const STATUS_PAID = 'Paid';

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
    ];

    protected $fillable = [
        'company_id',
        'investment_id',
        'amount',
        'status',
        'payment_date',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function investment()
    {
        return $this->belongsTo(Investment::class);
    }
}
