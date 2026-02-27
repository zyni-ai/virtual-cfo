<?php

namespace Database\Factories;

use App\Enums\ImportSource;
use App\Enums\ImportStatus;
use App\Enums\StatementType;
use App\Models\Company;
use App\Models\ImportedFile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ImportedFile>
 */
class ImportedFileFactory extends Factory
{
    protected $model = ImportedFile::class;

    public function definition(): array
    {
        $bank = fake()->randomElement(['HDFC', 'ICICI', 'SBI', 'Axis', 'Kotak']);

        return [
            'company_id' => Company::factory(),
            'bank_name' => $bank,
            'account_number' => fake()->numerify('################'),
            'statement_type' => StatementType::Bank,
            'file_path' => 'statements/'.fake()->uuid().'.pdf',
            'original_filename' => $bank.'_statement_'.fake()->date('Y_m').'.pdf',
            'file_hash' => fake()->unique()->sha256(),
            'status' => ImportStatus::Pending,
            'source' => ImportSource::ManualUpload,
            'source_metadata' => null,
            'total_rows' => 0,
            'mapped_rows' => 0,
            'error_message' => null,
            'uploaded_by' => User::factory(),
            'processed_at' => null,
        ];
    }

    public function completed(int $totalRows = 50, int $mappedRows = 30): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportStatus::Completed,
            'total_rows' => $totalRows,
            'mapped_rows' => $mappedRows,
            'processed_at' => now(),
        ]);
    }

    public function processing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportStatus::Processing,
        ]);
    }

    public function failed(string $error = 'PDF parsing failed'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ImportStatus::Failed,
            'error_message' => $error,
            'processed_at' => now(),
        ]);
    }

    public function creditCard(): static
    {
        return $this->state(fn (array $attributes) => [
            'statement_type' => StatementType::CreditCard,
        ]);
    }

    public function invoice(): static
    {
        return $this->state(fn (array $attributes) => [
            'statement_type' => StatementType::Invoice,
            'file_path' => 'statements/'.fake()->uuid().'.pdf',
            'original_filename' => 'invoice_'.fake()->date('Y_m').'.pdf',
        ]);
    }

    public function csv(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_path' => 'statements/'.fake()->uuid().'.csv',
            'original_filename' => ($attributes['bank_name'] ?? 'Bank').'_statement.csv',
        ]);
    }

    public function xlsx(): static
    {
        return $this->state(fn (array $attributes) => [
            'file_path' => 'statements/'.fake()->uuid().'.xlsx',
            'original_filename' => ($attributes['bank_name'] ?? 'Bank').'_statement.xlsx',
        ]);
    }

    public function forBank(string $bank): static
    {
        return $this->state(fn (array $attributes) => [
            'bank_name' => $bank,
        ]);
    }

    public function fromEmail(?string $messageId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => ImportSource::Email,
            'source_metadata' => [
                'message_id' => $messageId ?? '<'.fake()->uuid().'@mail.example.com>',
                'from' => fake()->email(),
                'subject' => 'Invoice '.fake()->date('M Y'),
                'received_at' => now()->toIso8601String(),
            ],
        ]);
    }

    public function fromZoho(?string $invoiceId = null): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => ImportSource::Zoho,
            'source_metadata' => [
                'zoho_invoice_id' => $invoiceId ?? fake()->numerify('##########'),
                'zoho_org_id' => fake()->numerify('##########'),
                'synced_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
