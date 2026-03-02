<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property \Illuminate\Support\Carbon $logged_in_at
 * @property \Illuminate\Support\Carbon $last_active_at
 * @property \Illuminate\Support\Carbon|null $logged_out_at
 */
class LoginSession extends Model
{
    /** @use HasFactory<\Database\Factories\LoginSessionFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'ip_address',
        'user_agent',
        'device_type',
        'browser',
        'os',
        'logged_in_at',
        'last_active_at',
        'logged_out_at',
    ];

    protected function casts(): array
    {
        return [
            'logged_in_at' => 'datetime',
            'last_active_at' => 'datetime',
            'logged_out_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @param Builder<LoginSession> $query */
    public function scopeActiveForUser(Builder $query, int $userId): void
    {
        $query->where('user_id', $userId)
            ->whereNull('logged_out_at')
            ->latest('logged_in_at');
    }
}
