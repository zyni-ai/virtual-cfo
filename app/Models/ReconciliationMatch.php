<?php

namespace App\Models;

use App\Enums\MatchMethod;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

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
            ])
            ->logOnlyDirty()
            ->useLogName('reconciliation-matches');
    }

    protected function casts(): array
    {
        return [
            'confidence' => 'float',
            'match_method' => MatchMethod::class,
        ];
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'bank_transaction_id');
    }

    public function invoiceTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'invoice_transaction_id');
    }
}
