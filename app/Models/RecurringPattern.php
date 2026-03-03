<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RecurringPattern extends Model
{
    /** @use HasFactory<\Database\Factories\RecurringPatternFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'description_pattern',
        'bank_format',
        'account_head_id',
        'avg_amount',
        'frequency',
        'occurrence_count',
        'last_seen_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'avg_amount' => 'decimal:2',
            'occurrence_count' => 'integer',
            'last_seen_at' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<AccountHead, $this> */
    public function accountHead(): BelongsTo
    {
        return $this->belongsTo(AccountHead::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
