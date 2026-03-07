<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Company extends Model
{
    /** @use HasFactory<\Database\Factories\CompanyFactory> */
    use HasFactory;

    use LogsActivity;

    protected $fillable = [
        'name',
        'gstin',
        'state',
        'gst_registration_type',
        'financial_year',
        'currency',
        'fy_start_month',
        'inbox_address',
        'account_holder_name',
        'date_of_birth',
        'pan_number',
        'mobile_number',
    ];

    protected function casts(): array
    {
        return [
            'account_holder_name' => 'encrypted',
            'date_of_birth' => 'encrypted',
            'pan_number' => 'encrypted',
            'mobile_number' => 'encrypted',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'gstin', 'state', 'gst_registration_type', 'financial_year', 'currency', 'fy_start_month'])
            ->logOnlyDirty()
            ->useLogName('companies');
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('role')->withTimestamps();
    }

    /** @return HasMany<BankAccount, $this> */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    /** @return HasMany<ImportedFile, $this> */
    public function importedFiles(): HasMany
    {
        return $this->hasMany(ImportedFile::class);
    }

    /** @return HasMany<AccountHead, $this> */
    public function accountHeads(): HasMany
    {
        return $this->hasMany(AccountHead::class);
    }

    /** @return HasMany<HeadMapping, $this> */
    public function headMappings(): HasMany
    {
        return $this->hasMany(HeadMapping::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return HasMany<Connector, $this> */
    public function connectors(): HasMany
    {
        return $this->hasMany(Connector::class);
    }

    /** @return BelongsToMany<CreditCard, $this> */
    public function sharedCreditCards(): BelongsToMany
    {
        return $this->belongsToMany(CreditCard::class, 'company_credit_card')
            ->withPivot('shared_by')
            ->withTimestamps();
    }
}
