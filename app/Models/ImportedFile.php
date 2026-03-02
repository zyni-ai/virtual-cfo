<?php

namespace App\Models;

use App\Enums\ImportSource;
use App\Enums\ImportStatus;
use App\Enums\StatementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property array<string, mixed>|null $source_metadata
 */
class ImportedFile extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::deleting(function (ImportedFile $file) {
            if ($file->isForceDeleting()) {
                if ($file->file_path && Storage::disk('local')->exists($file->file_path)) {
                    Storage::disk('local')->delete($file->file_path);
                }
            } else {
                Transaction::where('imported_file_id', $file->id)->each(
                    fn (Transaction $transaction) => $transaction->delete()
                );
            }
        });

        static::restoring(function (ImportedFile $file) {
            Transaction::onlyTrashed()->where('imported_file_id', $file->id)->each(
                fn (Transaction $transaction) => $transaction->restore()
            );
        });
    }

    protected $fillable = [
        'company_id',
        'bank_name',
        'account_number',
        'statement_type',
        'file_path',
        'original_filename',
        'file_hash',
        'status',
        'source',
        'source_metadata',
        'message_id',
        'total_rows',
        'mapped_rows',
        'error_message',
        'uploaded_by',
        'processed_at',
        'bank_account_id',
        'credit_card_id',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'bank_name',
                'statement_type',
                'file_path',
                'original_filename',
                'file_hash',
                'status',
                'source',
                'total_rows',
                'mapped_rows',
                'error_message',
                'uploaded_by',
                'processed_at',
            ])
            ->logOnlyDirty()
            ->useLogName('imported-files');
    }

    protected function casts(): array
    {
        return [
            'status' => ImportStatus::class,
            'statement_type' => StatementType::class,
            'source' => ImportSource::class,
            'source_metadata' => 'encrypted:array',
            'account_number' => 'encrypted',
            'processed_at' => 'datetime',
            'total_rows' => 'integer',
            'mapped_rows' => 'integer',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /** @return BelongsTo<BankAccount, $this> */
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    /** @return BelongsTo<CreditCard, $this> */
    public function creditCard(): BelongsTo
    {
        return $this->belongsTo(CreditCard::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function getMappedPercentageAttribute(): float
    {
        if ($this->total_rows === 0) {
            return 0;
        }

        return round(($this->mapped_rows / $this->total_rows) * 100, 1);
    }
}
