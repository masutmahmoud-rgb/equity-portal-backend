<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    public const AUDIENCE_ALL = 'All';
    public const AUDIENCE_COMPANY = 'Company';
    public const AUDIENCE_PARTNER = 'Partner';

    public const AUDIENCE_TYPES = [
        self::AUDIENCE_ALL,
        self::AUDIENCE_COMPANY,
        self::AUDIENCE_PARTNER,
    ];

    public const STATUS_DRAFT = 'Draft';
    public const STATUS_PUBLISHED = 'Published';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
    ];

    protected $fillable = [
        'title',
        'message',
        'category',
        'audience_type',
        'company_id',
        'investor_id',
        'attachment',
        'publish_date',
        'expiry_date',
        'status',
    ];

    protected $casts = [
        'publish_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function investor(): BelongsTo
    {
        return $this->belongsTo(Investor::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        $today = now()->startOfDay();

        return $query
            ->whereRaw('LOWER(status) = ?', [strtolower(self::STATUS_PUBLISHED)])
            ->whereDate('publish_date', '<=', $today)
            ->where(function (Builder $q) use ($today) {
                $q->whereNull('expiry_date')
                    ->orWhereDate('expiry_date', '>=', $today);
            });
    }
}
