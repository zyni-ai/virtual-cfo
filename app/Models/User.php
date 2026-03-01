<?php

namespace App\Models;

use App\Enums\UserRole;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

/**
 * @property UserRole|null $role
 */
class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->role !== null;
    }

    /** @return BelongsToMany<Company, $this> */
    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class)->withPivot('role')->withTimestamps();
    }

    /** @return Collection<int, Company> */
    public function getTenants(Panel $panel): Collection
    {
        return $this->companies;
    }

    public function canAccessTenant(Model $tenant): bool
    {
        return $this->companies()->whereKey($tenant)->exists();
    }

    public function getDefaultTenant(Panel $panel): ?Model
    {
        return $this->companies->first();
    }

    public function roleForCompany(Company $company): ?UserRole
    {
        $pivot = $this->companies()->where('company_id', $company->id)->first();

        return $pivot ? UserRole::tryFrom($pivot->pivot->role) : null;
    }

    public function currentRole(): ?UserRole
    {
        $tenant = Filament::getTenant();

        if (! $tenant instanceof Company) {
            return null;
        }

        return $this->roleForCompany($tenant);
    }

    public function importedFiles(): HasMany
    {
        return $this->hasMany(ImportedFile::class, 'uploaded_by');
    }

    public function headMappings(): HasMany
    {
        return $this->hasMany(HeadMapping::class, 'created_by');
    }
}
