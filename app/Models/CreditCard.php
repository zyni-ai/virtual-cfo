<?php

namespace App\Models;

use App\Enums\ImportStatus;
use App\Jobs\ProcessImportedFile;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CreditCard extends Model
{
    /** @use HasFactory<\Database\Factories\CreditCardFactory> */
    use HasFactory;

    use LogsActivity;
    use SoftDeletes;

    protected static function booted(): void
    {
        static::updated(function (CreditCard $card) {
            if ($card->wasChanged('pdf_password') && $card->pdf_password) {
                $card->importedFiles()
                    ->where('status', ImportStatus::NeedsPassword)
                    ->each(fn (ImportedFile $file) => ProcessImportedFile::dispatch($file));
            }
        });
    }

    protected $fillable = [
        'company_id',
        'name',
        'card_number',
        'pdf_password',
        'is_active',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['company_id', 'name', 'is_active'])
            ->logOnlyDirty()
            ->useLogName('credit-cards');
    }

    protected function casts(): array
    {
        return [
            'card_number' => 'encrypted',
            'pdf_password' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    /** @return Attribute<string|null, never> */
    protected function maskedCardNumber(): Attribute
    {
        return Attribute::make(
            get: function (): ?string {
                if (! $this->card_number) {
                    return null;
                }

                $number = $this->card_number;

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

    /** @return BelongsToMany<Company, $this> */
    public function sharedCompanies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_credit_card')
            ->withPivot('shared_by')
            ->withTimestamps();
    }

    public function isSharedWith(Company $company): bool
    {
        return $this->sharedCompanies()->where('companies.id', $company->id)->exists();
    }

    /**
     * @param  Builder<CreditCard>  $query
     * @return Builder<CreditCard>
     */
    public function scopeVisibleToCompany(Builder $query, int $companyId): Builder
    {
        return $query->where(function (Builder $q) use ($companyId) {
            $q->where('company_id', $companyId)
                ->orWhereHas('sharedCompanies', fn (Builder $sub) => $sub->where('companies.id', $companyId));
        });
    }
}
