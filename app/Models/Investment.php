<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Investment extends Model
{
    public const STATUS_ACTIVE = 'Active';
    public const STATUS_CLOSED = 'Closed';
    public const STATUS_PENDING = 'Pending';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_CLOSED,
        self::STATUS_PENDING,
    ];

    protected $fillable = [
        'investor_id',
        'company_id',
        'amount',
        'status',
        'notes',
        'invested_at',
        'indicative_value',
        'profit',
        'valuation_date',
        'valuation_notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'invested_at' => 'datetime',
        'indicative_value' => 'decimal:2',
        'profit' => 'decimal:2',
        'valuation_date' => 'date',
    ];

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * Get all investment transactions for this investment
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(InvestmentTransaction::class);
    }

    /**
     * Get current investment balance from all transactions
     */
    public function getCurrentBalance(): float
    {
        return (float) $this->transactions()
            ->sum('amount');
    }

    /**
     * Get Return on Investment (ROI) percentage
     * ROI = (profit / total_invested) × 100
     * Returns null if no transactions or profit not set
     */
    public function getROI(): ?float
    {
        $totalInvested = $this->getCurrentBalance();
        
        if ($totalInvested <= 0 || $this->profit === null) {
            return null;
        }
        
        return round(((float)$this->profit / $totalInvested) * 100, 2);
    }

    /**
     * Get current value (alias for indicative_value)
     */
    public function getCurrentValue(): ?float
    {
        return $this->indicative_value !== null ? (float) $this->indicative_value : null;
    }

    /**
     * Get total active investment amount for a company and investor
     */
    public static function getTotalActiveForCompanyAndPartner($companyId, $investorId)
    {
        return self::where('company_id', $companyId)
                   ->where('investor_id', $investorId)
                   ->where('status', self::STATUS_ACTIVE)
                   ->sum('amount');
    }

    /**
     * Get all active investments for a company and investor
     */
    public static function getActiveInvestmentsForCompanyAndPartner($companyId, $investorId)
    {
        return self::where('company_id', $companyId)
                   ->where('investor_id', $investorId)
                   ->where('status', self::STATUS_ACTIVE)
                   ->get();
    }
}
