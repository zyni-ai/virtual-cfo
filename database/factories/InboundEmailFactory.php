<?php

namespace Database\Factories;

use App\Enums\InboundEmailStatus;
use App\Models\Company;
use App\Models\InboundEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InboundEmail>
 */
class InboundEmailFactory extends Factory
{
    protected $model = InboundEmail::class;

    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'message_id' => '<'.fake()->uuid().'@mail.example.com>',
            'from_address' => fake()->name().' <'.fake()->email().'>',
            'subject' => fake()->sentence(4),
            'body_text' => null,
            'recipient' => 'invoices@inbox.example.com',
            'attachment_count' => 1,
            'processed_count' => 1,
            'skipped_count' => 0,
            'status' => InboundEmailStatus::Processed,
            'rejection_reason' => null,
            'received_at' => now(),
            'raw_headers' => null,
        ];
    }

    public function rejected(string $reason = 'Unknown inbox address'): static
    {
        return $this->state(fn (array $attributes) => [
            'company_id' => null,
            'status' => InboundEmailStatus::Rejected,
            'rejection_reason' => $reason,
            'attachment_count' => 0,
            'processed_count' => 0,
            'skipped_count' => 0,
        ]);
    }

    public function duplicate(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InboundEmailStatus::Duplicate,
            'rejection_reason' => 'Duplicate message_id',
            'attachment_count' => 0,
            'processed_count' => 0,
            'skipped_count' => 0,
        ]);
    }

    public function noAttachments(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InboundEmailStatus::NoAttachments,
            'attachment_count' => 0,
            'processed_count' => 0,
            'skipped_count' => 0,
        ]);
    }
}
