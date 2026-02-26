<?php

namespace Database\Factories;

use App\Models\AccountHead;
use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountHead>
 */
class AccountHeadFactory extends Factory
{
    protected $model = AccountHead::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'name' => fake()->unique()->words(rand(2, 4), true),
            'parent_id' => null,
            'tally_guid' => fake()->optional(0.3)->uuid(),
            'group_name' => fake()->optional(0.5)->randomElement([
                'Direct Expenses',
                'Indirect Expenses',
                'Direct Income',
                'Indirect Income',
                'Current Assets',
                'Current Liabilities',
                'Fixed Assets',
            ]),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withParent(?AccountHead $parent = null): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent?->id ?? AccountHead::factory(),
        ]);
    }

    public function inGroup(string $group): static
    {
        return $this->state(fn (array $attributes) => [
            'group_name' => $group,
        ]);
    }
}
