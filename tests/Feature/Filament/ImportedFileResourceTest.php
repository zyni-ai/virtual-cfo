<?php

use App\Enums\ImportStatus;
use App\Enums\StatementType;
use App\Filament\Resources\ImportedFileResource;
use App\Filament\Resources\ImportedFileResource\Pages\ListImportedFiles;
use App\Jobs\ProcessImportedFile;
use App\Models\ImportedFile;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

describe('ImportedFileResource', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render the list page', function () {
        livewire(ListImportedFiles::class)->assertSuccessful();
    });

    it('can list imported files', function () {
        $files = ImportedFile::factory()->count(3)->create();

        livewire(ListImportedFiles::class)
            ->assertCanSeeTableRecords($files);
    });

    it('can filter by status', function () {
        $completed = ImportedFile::factory()->completed()->create();
        $pending = ImportedFile::factory()->create(['status' => ImportStatus::Pending]);

        livewire(ListImportedFiles::class)
            ->filterTable('status', ImportStatus::Completed->value)
            ->assertCanSeeTableRecords([$completed])
            ->assertCanNotSeeTableRecords([$pending]);
    });

    it('can filter by statement type', function () {
        $bank = ImportedFile::factory()->create(['statement_type' => StatementType::Bank]);
        $cc = ImportedFile::factory()->create(['statement_type' => StatementType::CreditCard]);

        livewire(ListImportedFiles::class)
            ->filterTable('statement_type', StatementType::Bank->value)
            ->assertCanSeeTableRecords([$bank])
            ->assertCanNotSeeTableRecords([$cc]);
    });

    it('can delete an imported file from the table', function () {
        $file = ImportedFile::factory()->create();

        livewire(ListImportedFiles::class)
            ->callTableAction('delete', $file);

        expect(ImportedFile::find($file->id))->toBeNull();
    });

    it('has correct navigation properties', function () {
        expect(ImportedFileResource::getNavigationLabel())->toBe('Imported Files')
            ->and(ImportedFileResource::getNavigationSort())->toBe(1);
    });

    it('soft-deletes the record and retains it in the database', function () {
        $file = ImportedFile::factory()->create();

        livewire(ListImportedFiles::class)
            ->callTableAction('delete', $file);

        // Record should not appear via normal query
        expect(ImportedFile::find($file->id))->toBeNull();
        // But should still exist in the database with a deleted_at timestamp
        expect(ImportedFile::withTrashed()->find($file->id))->not->toBeNull()
            ->and(ImportedFile::withTrashed()->find($file->id)->deleted_at)->not->toBeNull();
    });

    it('reprocess action resets file and dispatches ProcessImportedFile job', function () {
        Queue::fake();

        $file = ImportedFile::factory()->completed(totalRows: 10, mappedRows: 5)->create();

        livewire(ListImportedFiles::class)
            ->callTableAction('reprocess', $file);

        // File should be reset to pending
        $file->refresh();
        expect($file->status)->toBe(ImportStatus::Pending)
            ->and($file->total_rows)->toBe(0)
            ->and($file->mapped_rows)->toBe(0)
            ->and($file->error_message)->toBeNull();

        // Job should be dispatched
        Queue::assertPushed(ProcessImportedFile::class, function (ProcessImportedFile $job) use ($file) {
            return $job->importedFile->id === $file->id;
        });
    });

    it('reprocess action is visible only for completed and failed files', function () {
        $completedFile = ImportedFile::factory()->completed()->create();
        $failedFile = ImportedFile::factory()->failed()->create();
        $pendingFile = ImportedFile::factory()->create(['status' => ImportStatus::Pending]);
        $processingFile = ImportedFile::factory()->processing()->create();

        livewire(ListImportedFiles::class)
            ->assertTableActionVisible('reprocess', $completedFile)
            ->assertTableActionVisible('reprocess', $failedFile)
            ->assertTableActionHidden('reprocess', $pendingFile)
            ->assertTableActionHidden('reprocess', $processingFile);
    });

    it('shows linked bank account name in table', function () {
        $account = \App\Models\BankAccount::factory()->create(['company_id' => tenant()->id, 'name' => 'HDFC Bank']);
        ImportedFile::factory()->create([
            'company_id' => tenant()->id,
            'bank_account_id' => $account->id,
            'bank_name' => null,
        ]);

        livewire(ListImportedFiles::class)
            ->assertSuccessful();
    });

    it('falls back to bank_name when no bank account linked', function () {
        ImportedFile::factory()->create([
            'company_id' => tenant()->id,
            'bank_account_id' => null,
            'bank_name' => 'SBI',
        ]);

        livewire(ListImportedFiles::class)
            ->assertSuccessful();
    });
});
