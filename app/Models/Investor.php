<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Investor extends Model
{
    public const STATUS_ACTIVE = 'Active';
    public const STATUS_INACTIVE = 'Inactive';
    public const STATUS_PENDING = 'Pending';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
        self::STATUS_PENDING,
    ];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'status',
        'notes',
    ];

    /**
     * Get the user account associated with this investor.
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'email', 'email');
    }

    /**
     * Resolve the partner profile linked to a user email.
     * Prefer active records, then newest row for deterministic behavior.
     */
    public static function resolveLinkedByEmail(string $email): ?self
    {
        return self::query()
            ->where('email', $email)
            ->orderByRaw(
                "CASE WHEN status = ? THEN 0 ELSE 1 END",
                [self::STATUS_ACTIVE]
            )
            ->orderByDesc('id')
            ->first();
    }
}
