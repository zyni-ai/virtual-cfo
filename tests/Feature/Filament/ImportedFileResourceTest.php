<?php

use App\Enums\ImportStatus;
use App\Enums\StatementType;
use App\Filament\Resources\ImportedFileResource;
use App\Filament\Resources\ImportedFileResource\Pages\ListImportedFiles;
use App\Models\ImportedFile;

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
});
