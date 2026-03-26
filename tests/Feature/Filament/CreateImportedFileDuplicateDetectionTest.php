<?php

use App\Enums\ImportStatus;
use App\Enums\StatementType;
use App\Filament\Resources\ImportedFileResource\Pages\CreateImportedFile;
use App\Models\ImportedFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Livewire\livewire;

describe('CreateImportedFile duplicate detection', function () {
    beforeEach(function () {
        asUser();
        Storage::fake('local');
        Queue::fake();
    });

    it('can create a new imported file successfully', function () {
        $file = UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf');

        livewire(CreateImportedFile::class)
            ->fillForm([
                'file_path' => $file,
                'statement_type' => StatementType::Bank->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(ImportedFile::count())->toBe(1);

        $importedFile = ImportedFile::first();
        expect($importedFile->file_hash)->not->toBeNull()
            ->and($importedFile->file_hash)->toHaveLength(64)
            ->and($importedFile->status)->toBe(ImportStatus::Pending);
    });

    it('computes SHA-256 hash correctly from file contents', function () {
        $file = UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf');

        livewire(CreateImportedFile::class)
            ->fillForm([
                'file_path' => $file,
                'statement_type' => StatementType::Bank->value,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $importedFile = ImportedFile::first();
        $fileContents = Storage::disk('local')->get($importedFile->file_path);
        $expectedHash = hash('sha256', $fileContents);

        expect($importedFile->file_hash)->toBe($expectedHash);
    });

    it('shows validation error when uploading a duplicate file', function () {
        // Create a fake file and store it
        $fileContent = 'duplicate-pdf-content-for-testing';
        $filePath = 'statements/existing-file.pdf';
        Storage::disk('local')->put($filePath, $fileContent);

        $existingHash = hash('sha256', $fileContent);

        // Create an existing ImportedFile record with this hash
        ImportedFile::factory()->create([
            'file_hash' => $existingHash,
            'original_filename' => 'existing-statement.pdf',
            'file_path' => $filePath,
            'created_at' => now()->subDays(3),
        ]);

        // Upload a file with the same content
        $duplicateFile = UploadedFile::fake()->createWithContent(
            'new-statement.pdf',
            $fileContent,
        );

        livewire(CreateImportedFile::class)
            ->fillForm([
                'file_path' => $duplicateFile,
                'statement_type' => StatementType::Bank->value,
            ])
            ->call('create')
            ->assertNotified();

        // Should NOT create a new record
        expect(ImportedFile::count())->toBe(1);
    });

    it('allows force re-import to bypass duplicate check', function () {
        // Create a fake file and store it
        $fileContent = 'duplicate-pdf-content-for-reimport';
        $filePath = 'statements/existing-file.pdf';
        Storage::disk('local')->put($filePath, $fileContent);

        $existingHash = hash('sha256', $fileContent);

        // Create an existing ImportedFile record
        $existingFile = ImportedFile::factory()->create([
            'file_hash' => $existingHash,
            'original_filename' => 'old-statement.pdf',
            'file_path' => $filePath,
        ]);

        // Upload same content with force re-import enabled
        $duplicateFile = UploadedFile::fake()->createWithContent(
            'reimport-statement.pdf',
            $fileContent,
        );

        livewire(CreateImportedFile::class)
            ->fillForm([
                'file_path' => $duplicateFile,
                'statement_type' => StatementType::Bank->value,
                'force_reimport' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        // Old record should be deleted, new one created
        expect(ImportedFile::count())->toBe(1);

        $newFile = ImportedFile::first();
        expect($newFile->id)->not->toBe($existingFile->id)
            ->and($newFile->file_hash)->toBe($existingHash)
            ->and($newFile->status)->toBe(ImportStatus::Pending);
    });

    it('includes existing filename and date in duplicate notification', function () {
        $fileContent = 'duplicate-for-notification-test';
        $filePath = 'statements/existing.pdf';
        Storage::disk('local')->put($filePath, $fileContent);

        $createdAt = now()->subDays(5);

        ImportedFile::factory()->create([
            'file_hash' => hash('sha256', $fileContent),
            'original_filename' => 'march-2024-hdfc.pdf',
            'display_name' => 'HDFC_Mar 2024',
            'file_path' => $filePath,
            'created_at' => $createdAt,
        ]);

        $duplicateFile = UploadedFile::fake()->createWithContent(
            'another-upload.pdf',
            $fileContent,
        );

        $expectedBody = "This file was already imported on {$createdAt->format('d M Y, H:i')} as \"HDFC_Mar 2024\". Enable \"Force re-import\" to replace it.";

        livewire(CreateImportedFile::class)
            ->fillForm([
                'file_path' => $duplicateFile,
                'statement_type' => StatementType::Bank->value,
            ])
            ->call('create')
            ->assertNotified(
                \Filament\Notifications\Notification::make()
                    ->danger()
                    ->title('Duplicate file detected')
                    ->body($expectedBody)
                    ->persistent()
            );

        expect(ImportedFile::count())->toBe(1);
    });
});
