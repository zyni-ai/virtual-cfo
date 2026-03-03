<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\TransactionAggregate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionAggregate>
 */
class TransactionAggregateFactory extends Factory
{
    protected $model = TransactionAggregate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'account_head_id' => null,
            'bank_account_id' => null,
            'credit_card_id' => null,
            'year_month' => now()->format('Y-m'),
            'total_debit' => fake()->randomFloat(2, 0, 100000),
            'total_credit' => fake()->randomFloat(2, 0, 100000),
            'transaction_count' => fake()->numberBetween(1, 100),
        ];
    }
}
