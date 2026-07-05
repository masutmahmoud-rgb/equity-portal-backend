<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Notification extends Model
{
    protected $fillable = [
        'notification_type',
        'title',
        'message',
        'important_notes',
        'publish_date',
        'expiry_date',
        'is_active',
        'target_investor_id',
        'valuation_id',
    ];

    protected $casts = [
        'publish_date' => 'date',
        'expiry_date' => 'date',
        'is_active' => 'boolean',
    ];

    public function investor()
    {
        return $this->belongsTo(Investor::class, 'target_investor_id');
    }

    public function valuation()
    {
        return $this->belongsTo(PortfolioValuation::class, 'valuation_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        $today = now()->startOfDay();

        return $query
            ->where('is_active', 1)
            ->whereDate('publish_date', '<=', $today)
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', $today);
            });
    }
}
