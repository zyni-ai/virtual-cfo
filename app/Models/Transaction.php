<?php

namespace App\Models;

use App\Enums\MappingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Transaction extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'imported_file_id',
        'date',
        'description',
        'reference_number',
        'debit',
        'credit',
        'balance',
        'account_head_id',
        'mapping_type',
        'ai_confidence',
        'raw_data',
        'bank_format',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'imported_file_id',
                'date',
                'reference_number',
                'account_head_id',
                'mapping_type',
                'ai_confidence',
                'bank_format',
            ])
            ->logOnlyDirty()
            ->useLogName('transactions');
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'description' => 'encrypted',
            'debit' => 'encrypted',
            'credit' => 'encrypted',
            'balance' => 'encrypted',
            'raw_data' => 'encrypted:array',
            'mapping_type' => MappingType::class,
            'ai_confidence' => 'float',
        ];
    }

    public function importedFile(): BelongsTo
    {
        return $this->belongsTo(ImportedFile::class);
    }

    public function accountHead(): BelongsTo
    {
        return $this->belongsTo(AccountHead::class);
    }

    public function scopeUnmapped(Builder $query): Builder
    {
        return $query->where('mapping_type', MappingType::Unmapped);
    }

    public function scopeMapped(Builder $query): Builder
    {
        return $query->where('mapping_type', '!=', MappingType::Unmapped);
    }

    public function scopeNeedsReview(Builder $query): Builder
    {
        return $query->where('mapping_type', MappingType::Ai)
            ->where('ai_confidence', '<', 0.8);
    }

    public function getDecryptedDebitAttribute(): ?float
    {
        return $this->debit !== null ? (float) $this->debit : null;
    }

    public function getDecryptedCreditAttribute(): ?float
    {
        return $this->credit !== null ? (float) $this->credit : null;
    }

    public function getDecryptedBalanceAttribute(): ?float
    {
        return $this->balance !== null ? (float) $this->balance : null;
    }
}
