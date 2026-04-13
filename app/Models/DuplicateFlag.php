<?php

namespace App\Models;

use App\Enums\DuplicateConfidence;
use App\Enums\DuplicateStatus;
use Database\Factories\DuplicateFlagFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property DuplicateConfidence $confidence
 * @property DuplicateStatus $status
 * @property array<int, string> $match_reasons
 */
class DuplicateFlag extends Model
{
    /** @use HasFactory<DuplicateFlagFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'transaction_id',
        'duplicate_transaction_id',
        'confidence',
        'match_reasons',
        'status',
        'resolved_by',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'confidence' => DuplicateConfidence::class,
            'status' => DuplicateStatus::class,
            'match_reasons' => 'array',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<Transaction, $this> */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /** @return BelongsTo<Transaction, $this> */
    public function duplicateTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'duplicate_transaction_id');
    }

    /** @return BelongsTo<User, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
