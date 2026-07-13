<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatementOfAccount extends Model
{
    public const TYPE_DIVIDEND = 'Dividend';
    public const TYPE_WITHDRAWAL = 'Withdrawal';
    public const TYPE_DEPOSIT = 'Deposit';

    public const DIRECTION_CREDIT = 'Credit';
    public const DIRECTION_DEBIT = 'Debit';

    public const STATUS_PENDING = 'Pending';
    public const STATUS_PAID = 'Paid';

    public const TRANSACTION_TYPES = [
        self::TYPE_DIVIDEND,
        self::TYPE_WITHDRAWAL,
        self::TYPE_DEPOSIT,
    ];

    public const ENTRY_DIRECTIONS = [
        self::DIRECTION_CREDIT,
        self::DIRECTION_DEBIT,
    ];

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_PAID,
    ];

    protected $fillable = [
        'company_id',
        'investment_id',
        'investor_id',
        'source_dividend_id',
        'transaction_type',
        'entry_direction',
        'amount',
        'original_amount',
        'original_currency',
        'exchange_rate',
        'status',
        'transaction_date',
        'description',
        'notes',
        'attachment_paths',
        'bank_name',
        'transfer_reference',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'original_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'transaction_date' => 'datetime',
        'attachment_paths' => 'array',
    ];

    protected $hidden = [
        'attachment_paths',
    ];

    protected $appends = [
        'attachment_urls',
    ];

    public function getAttachmentUrlsAttribute()
    {
        $paths = $this->normalizeAttachmentPaths($this->attachment_paths);

        if (empty($paths)) {
            return [];
        }

        return array_map(function ($path, $index) {
            return url("/api/statement-of-accounts/{$this->id}/attachments/{$index}");
        }, $paths, array_keys($paths));
    }

    protected function normalizeAttachmentPaths($rawPaths): array
    {
        if (is_array($rawPaths)) {
            return array_values(array_filter($rawPaths, fn ($path) => is_string($path) && trim($path) !== ''));
        }

        if (! is_string($rawPaths) || trim($rawPaths) === '') {
            return [];
        }

        $decoded = json_decode($rawPaths, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_values(array_filter($decoded, fn ($path) => is_string($path) && trim($path) !== ''));
        }

        return [trim($rawPaths)];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function investment()
    {
        return $this->belongsTo(Investment::class);
    }

    public function investor()
    {
        return $this->belongsTo(Investor::class);
    }

    public function sourceDividend()
    {
        return $this->belongsTo(Dividend::class, 'source_dividend_id');
    }
}
