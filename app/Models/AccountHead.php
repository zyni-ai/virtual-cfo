<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountHead extends Model
{
    protected $fillable = [
        'name',
        'parent_id',
        'tally_guid',
        'group_name',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(AccountHead::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(AccountHead::class, 'parent_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function headMappings(): HasMany
    {
        return $this->hasMany(HeadMapping::class);
    }

    public function getFullPathAttribute(): string
    {
        $parts = [$this->name];
        $current = $this;

        while ($current->parent) {
            $current = $current->parent;
            array_unshift($parts, $current->name);
        }

        return implode(' > ', $parts);
    }
}
