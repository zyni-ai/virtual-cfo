<?php

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class BankAccount extends Model
{
    /** @use HasFactory<\Database\Factories\BankAccountFactory> */
    use HasFactory;

    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'name',
        'account_number',
        'ifsc_code',
        'branch',
        'account_type',
        'is_active',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['company_id', 'name', 'ifsc_code', 'branch', 'account_type', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('bank-accounts');
    }

    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'account_type' => AccountType::class,
            'is_active' => 'boolean',
        ];
    }

    /** @return Attribute<string|null, never> */
    protected function maskedAccountNumber(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if (! $this->account_number) {
                    return null;
                }

                $number = $this->account_number;

                return str_repeat('•', max(0, strlen($number) - 4)).substr($number, -4);
            },
        );
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return HasMany<ImportedFile, $this> */
    public function importedFiles(): HasMany
    {
        return $this->hasMany(ImportedFile::class);
    }
}
