<?php

namespace App\Models;

use App\Enums\PeriodType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property PeriodType $period_type
 */
class Budget extends Model
{
    /** @use HasFactory<\Database\Factories\BudgetFactory> */
    use HasFactory;

    protected $fillable = [
        'company_id',
        'account_head_id',
        'period_type',
        'amount',
        'year_month',
        'financial_year',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'period_type' => PeriodType::class,
            'amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<Company, $this> */
    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    /** @return BelongsTo<AccountHead, $this> */
    public function accountHead(): BelongsTo
    {
        return $this->belongsTo(AccountHead::class);
    }
}
