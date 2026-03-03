<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\RecurringPattern;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RecurringPattern>
 */
class RecurringPatternFactory extends Factory
{
    protected $model = RecurringPattern::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'description_pattern' => fake()->randomElement([
                'acme corp',
                'salary payment',
                'rent payment',
                'insurance premium',
                'electricity bill',
                'internet charges',
            ]),
            'bank_format' => null,
            'account_head_id' => null,
            'avg_amount' => fake()->randomFloat(2, 1000, 50000),
            'frequency' => fake()->randomElement(['monthly', 'quarterly', 'annual', 'irregular']),
            'occurrence_count' => fake()->numberBetween(3, 20),
            'last_seen_at' => fake()->dateTimeBetween('-3 months', 'now'),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function stale(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_seen_at' => fake()->dateTimeBetween('-12 months', '-7 months'),
        ]);
    }
}
