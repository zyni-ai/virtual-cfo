<?php

namespace Database\Factories;

use App\Enums\ConnectorProvider;
use App\Enums\ZohoDataCenter;
use App\Models\Company;
use App\Models\Connector;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Connector>
 */
class ConnectorFactory extends Factory
{
    protected $model = Connector::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'provider' => ConnectorProvider::Zoho,
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'token_expires_at' => now()->addHour(),
            'settings' => [
                'data_center' => ZohoDataCenter::India->value,
                'client_id' => fake()->regexify('[0-9]{19}\.[A-Z0-9]{32}'),
                'client_secret' => fake()->sha256(),
                'organization_id' => fake()->numerify('##########'),
            ],
            'last_synced_at' => null,
            'is_active' => true,
        ];
    }

    public function zohoConnected(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => ConnectorProvider::Zoho,
            'access_token' => fake()->sha256(),
            'refresh_token' => fake()->sha256(),
            'token_expires_at' => now()->addHour(),
            'settings' => [
                'data_center' => ZohoDataCenter::India->value,
                'client_id' => 'test-client',
                'client_secret' => 'test-secret',
                'organization_id' => '12345678',
            ],
            'is_active' => true,
            'last_synced_at' => now()->subHours(2),
        ]);
    }

    public function withOrganization(string $organizationId): static
    {
        return $this->state(fn (array $attributes) => [
            'settings' => array_merge($attributes['settings'], ['organization_id' => $organizationId]),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'token_expires_at' => now()->subHour(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
