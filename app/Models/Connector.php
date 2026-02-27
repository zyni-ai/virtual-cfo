<?php

namespace App\Models;

use App\Enums\ConnectorProvider;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property \Illuminate\Support\Carbon|null $token_expires_at
 * @property \Illuminate\Support\Carbon|null $last_synced_at
 */
class Connector extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    protected $fillable = [
        'company_id',
        'provider',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'settings',
        'last_synced_at',
        'is_active',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['provider', 'is_active', 'last_synced_at'])
            ->logOnlyDirty()
            ->useLogName('connectors');
    }

    protected function casts(): array
    {
        return [
            'provider' => ConnectorProvider::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'settings' => 'encrypted:array',
            'last_synced_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isTokenExpired(): bool
    {
        if ($this->token_expires_at === null) {
            return true;
        }

        return $this->token_expires_at->isPast();
    }

    public function isTokenExpiringSoon(int $minutes = 5): bool
    {
        if ($this->token_expires_at === null) {
            return true;
        }

        return $this->token_expires_at->subMinutes($minutes)->isPast();
    }
}
