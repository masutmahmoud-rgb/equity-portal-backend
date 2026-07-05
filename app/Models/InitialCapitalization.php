<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InitialCapitalization extends Model
{
    public const STATUS_DRAFT = 'Draft';
    public const STATUS_PUBLISHED = 'Published';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_PUBLISHED,
    ];

    protected $fillable = [
        'company_id',
        'effective_date',
        'status',
        'published_at',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'published_at' => 'datetime',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InitialCapitalizationItem::class);
    }
}
