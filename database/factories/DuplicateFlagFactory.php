<?php

namespace Database\Factories;

use App\Enums\DuplicateConfidence;
use App\Enums\DuplicateStatus;
use App\Models\Company;
use App\Models\DuplicateFlag;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DuplicateFlag>
 */
class DuplicateFlagFactory extends Factory
{
    protected $model = DuplicateFlag::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'transaction_id' => Transaction::factory(),
            'duplicate_transaction_id' => Transaction::factory(),
            'confidence' => fake()->randomElement(DuplicateConfidence::cases()),
            'match_reasons' => ['amount_date'],
            'status' => DuplicateStatus::Pending,
        ];
    }

    public function highConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence' => DuplicateConfidence::High,
            'match_reasons' => ['reference_number', 'amount_date'],
        ]);
    }

    public function mediumConfidence(): static
    {
        return $this->state(fn (array $attributes) => [
            'confidence' => DuplicateConfidence::Medium,
            'match_reasons' => ['amount_date', 'description_similarity'],
        ]);
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DuplicateStatus::Confirmed,
            'resolved_at' => now(),
        ]);
    }

    public function dismissed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => DuplicateStatus::Dismissed,
            'resolved_at' => now(),
        ]);
    }
}
