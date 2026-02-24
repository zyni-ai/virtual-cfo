<?php

namespace Database\Factories;

use App\Enums\ImportStatus;
use App\Enums\StatementType;
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
            'bank_name' => $bank,
            'account_number' => fake()->numerify('################'),
            'statement_type' => StatementType::Bank,
            'file_path' => 'statements/'.fake()->uuid().'.pdf',
            'original_filename' => $bank.'_statement_'.fake()->date('Y_m').'.pdf',
            'file_hash' => fake()->unique()->sha256(),
            'status' => ImportStatus::Pending,
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

    public function forBank(string $bank): static
    {
        return $this->state(fn (array $attributes) => [
            'bank_name' => $bank,
        ]);
    }
}
