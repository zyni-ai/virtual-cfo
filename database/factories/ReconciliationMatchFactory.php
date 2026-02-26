<?php

namespace Database\Factories;

use App\Enums\MatchMethod;
use App\Models\ReconciliationMatch;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ReconciliationMatch>
 */
class ReconciliationMatchFactory extends Factory
{
    protected $model = ReconciliationMatch::class;

    public function definition(): array
    {
        return [
            'bank_transaction_id' => Transaction::factory(),
            'invoice_transaction_id' => Transaction::factory(),
            'confidence' => fake()->randomFloat(4, 0.5, 1.0),
            'match_method' => fake()->randomElement(MatchMethod::cases()),
            'notes' => null,
        ];
    }

    public function amountMatch(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_method' => MatchMethod::Amount,
            'confidence' => fake()->randomFloat(4, 0.9, 1.0),
        ]);
    }

    public function manualMatch(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_method' => MatchMethod::Manual,
            'confidence' => 1.0,
        ]);
    }
}
