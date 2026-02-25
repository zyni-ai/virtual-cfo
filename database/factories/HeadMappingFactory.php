<?php

namespace Database\Factories;

use App\Enums\MatchType;
use App\Models\AccountHead;
use App\Models\HeadMapping;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\HeadMapping>
 */
class HeadMappingFactory extends Factory
{
    protected $model = HeadMapping::class;

    public function definition(): array
    {
        return [
            'pattern' => fake()->randomElement([
                'SALARY',
                'NEFT',
                'UPI',
                'EMI',
                'ATM WDL',
                'RTGS',
                'CC PAYMENT',
                fake()->company(),
            ]),
            'match_type' => MatchType::Contains,
            'account_head_id' => AccountHead::factory(),
            'bank_name' => null,
            'created_by' => User::factory(),
            'usage_count' => 0,
            'priority' => null,
        ];
    }

    public function exact(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_type' => MatchType::Exact,
        ]);
    }

    public function regex(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_type' => MatchType::Regex,
            'pattern' => '/NEFT[-\/]\d+/i',
        ]);
    }

    public function forBank(string $bank): static
    {
        return $this->state(fn (array $attributes) => [
            'bank_name' => $bank,
        ]);
    }

    public function withUsage(int $count = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_count' => $count,
        ]);
    }

    public function withPriority(int $priority): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
        ]);
    }
}
