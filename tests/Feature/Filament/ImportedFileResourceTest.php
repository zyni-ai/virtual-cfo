<?php

use App\Enums\ImportStatus;
use App\Enums\StatementType;
use App\Filament\Resources\ImportedFileResource;
use App\Filament\Resources\ImportedFileResource\Pages\CreateImportedFile;
use App\Filament\Resources\ImportedFileResource\Pages\ListImportedFiles;
use App\Filament\Resources\ImportedFileResource\Pages\ViewImportedFile;
use App\Jobs\ProcessImportedFile;
use App\Models\BankAccount;
use App\Models\CreditCard;
use App\Models\ImportedFile;
use Illuminate\Support\Facades\Queue;

use function Pest\Livewire\livewire;

describe('ImportedFileResource reactive form', function () {
    beforeEach(function () {
        asUser();
    });

    it('shows bank_account_id when statement_type is Bank', function () {
        livewire(CreateImportedFile::class)
            ->set('data.statement_type', StatementType::Bank->value)
            ->assertFormFieldVisible('bank_account_id')
            ->assertFormFieldHidden('credit_card_id');
    });

    it('shows credit_card_id when statement_type is CreditCard', function () {
        livewire(CreateImportedFile::class)
            ->set('data.statement_type', StatementType::CreditCard->value)
            ->assertFormFieldVisible('credit_card_id')
            ->assertFormFieldHidden('bank_account_id');
    });

    it('hides both account fields when statement_type is Invoice', function () {
        livewire(CreateImportedFile::class)
            ->set('data.statement_type', StatementType::Invoice->value)
            ->assertFormFieldHidden('bank_account_id')
            ->assertFormFieldHidden('credit_card_id');
    });

    it('does not have bank_name field on create form', function () {
        $component = livewire(CreateImportedFile::class);

        $fields = $component->instance()
            ->getSchema('form')
            ->getFlatFields(withHidden: true);

        expect($fields)->not->toHaveKey('bank_name');
    });
});

describe('ImportedFileResource view page', function () {
    beforeEach(function () {
        asUser();
    });

    it('shows linked bank account name', function () {
        $account = BankAccount::factory()->create([
            'company_id' => tenant()->id,
            'name' => 'HDFC Current Account',
        ]);
        $file = ImportedFile::factory()->completed()->create([
            'bank_account_id' => $account->id,
        ]);

        livewire(ViewImportedFile::class, ['record' => $file->getRouteKey()])
            ->assertSchemaStateSet([
                'bankAccount.name' => 'HDFC Current Account',
            ]);
    });

    it('shows linked credit card name', function () {
        $card = CreditCard::factory()->create([
            'company_id' => tenant()->id,
            'name' => 'ICICI Amazon Pay',
        ]);
        $file = ImportedFile::factory()->completed()->create([
            'credit_card_id' => $card->id,
            'statement_type' => StatementType::CreditCard,
        ]);

        livewire(ViewImportedFile::class, ['record' => $file->getRouteKey()])
            ->assertSchemaStateSet([
                'creditCard.name' => 'ICICI Amazon Pay',
            ]);
    });
});

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
        $needsPasswordFile = ImportedFile::factory()->create(['status' => ImportStatus::NeedsPassword]);

        livewire(ListImportedFiles::class)
            ->assertTableActionVisible('reprocess', $completedFile)
            ->assertTableActionVisible('reprocess', $failedFile)
            ->assertTableActionHidden('reprocess', $pendingFile)
            ->assertTableActionHidden('reprocess', $processingFile)
            ->assertTableActionHidden('reprocess', $needsPasswordFile);
    });

    it('setPassword action is visible only for NeedsPassword files', function () {
        $needsPasswordFile = ImportedFile::factory()->create(['status' => ImportStatus::NeedsPassword]);
        $completedFile = ImportedFile::factory()->completed()->create();
        $pendingFile = ImportedFile::factory()->create(['status' => ImportStatus::Pending]);

        livewire(ListImportedFiles::class)
            ->assertTableActionVisible('setPassword', $needsPasswordFile)
            ->assertTableActionHidden('setPassword', $completedFile)
            ->assertTableActionHidden('setPassword', $pendingFile);
    });

    it('setPassword action saves password and dispatches reprocessing', function () {
        Queue::fake();

        $file = ImportedFile::factory()->create([
            'status' => ImportStatus::NeedsPassword,
            'error_message' => 'This PDF is password-protected.',
        ]);

        livewire(ListImportedFiles::class)
            ->callTableAction('setPassword', $file, data: [
                'pdf_password' => 'mysecret123',
            ]);

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::Pending)
            ->and($file->error_message)->toBeNull()
            ->and($file->source_metadata['manual_password'])->toBe('mysecret123');

        Queue::assertPushed(ProcessImportedFile::class, fn ($job) => $job->importedFile->id === $file->id);
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

    it('can filter by source', function () {
        $manual = ImportedFile::factory()->create(['source' => \App\Enums\ImportSource::ManualUpload]);
        $email = ImportedFile::factory()->fromEmail()->create();

        livewire(ListImportedFiles::class)
            ->filterTable('source', \App\Enums\ImportSource::Email->value)
            ->assertCanSeeTableRecords([$email])
            ->assertCanNotSeeTableRecords([$manual]);
    });

    it('displays source badge in table', function () {
        ImportedFile::factory()->fromEmail()->create();

        livewire(ListImportedFiles::class)
            ->assertSuccessful();
    });
});
