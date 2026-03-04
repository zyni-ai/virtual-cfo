<?php

namespace App\Models;

use App\Enums\MatchMethod;
use App\Enums\MatchStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property MatchStatus $status
 * @property MatchMethod $match_method
 * @property int $id
 * @property int $bank_transaction_id
 * @property int $invoice_transaction_id
 */
class ReconciliationMatch extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'bank_transaction_id',
        'invoice_transaction_id',
        'confidence',
        'match_method',
        'notes',
        'status',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'bank_transaction_id',
                'invoice_transaction_id',
                'confidence',
                'match_method',
                'notes',
                'status',
            ])
            ->logOnlyDirty()
            ->useLogName('reconciliation-matches');
    }

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'match_method' => MatchMethod::class,
            'status' => MatchStatus::class,
        ];
    }

    /** @return BelongsTo<Transaction, $this> */
    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'bank_transaction_id');
    }

    /** @return BelongsTo<Transaction, $this> */
    public function invoiceTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'invoice_transaction_id');
    }

    /** @param Builder<ReconciliationMatch> $query */
    public function scopeSuggested(Builder $query): void
    {
        $query->where('status', MatchStatus::Suggested);
    }

    /** @param Builder<ReconciliationMatch> $query */
    public function scopeConfirmed(Builder $query): void
    {
        $query->where('status', MatchStatus::Confirmed);
    }

    /** @param Builder<ReconciliationMatch> $query */
    public function scopeRejected(Builder $query): void
    {
        $query->where('status', MatchStatus::Rejected);
    }
}
