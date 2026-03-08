<?php

namespace Database\Factories;

use App\Enums\PeriodType;
use App\Models\AccountHead;
use App\Models\Budget;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Budget>
 */
class BudgetFactory extends Factory
{
    protected $model = Budget::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'account_head_id' => AccountHead::factory(),
            'period_type' => PeriodType::Monthly,
            'amount' => fake()->randomFloat(2, 10000, 500000),
            'year_month' => now()->format('Y-m'),
            'financial_year' => '2025-26',
            'is_active' => true,
        ];
    }

    public function quarterly(): static
    {
        $quarter = 'Q'.ceil(now()->month / 3);

        return $this->state(fn () => [
            'period_type' => PeriodType::Quarterly,
            'year_month' => now()->format('Y').'-'.$quarter,
        ]);
    }

    public function annual(): static
    {
        return $this->state(fn () => [
            'period_type' => PeriodType::Annual,
            'year_month' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }
}
