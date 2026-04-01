<?php

use App\Filament\Resources\ImportedFileResource\Pages\ViewImportedFile;
use App\Filament\Resources\ImportedFileResource\RelationManagers\TransactionsRelationManager;
use App\Models\ImportedFile;
use App\Models\Transaction;

use function Pest\Livewire\livewire;

describe('ImportedFile Transactions RelationManager', function () {
    beforeEach(function () {
        asUser();
    });

    it('renders successfully on the view page', function () {
        $file = ImportedFile::factory()->create();

        livewire(ViewImportedFile::class, ['record' => $file->getRouteKey()])
            ->assertSeeLivewire(TransactionsRelationManager::class);
    });

    it('shows transactions belonging to the file', function () {
        $file = ImportedFile::factory()->create();
        $transactions = Transaction::factory()->count(3)->for($file, 'importedFile')->create();

        livewire(TransactionsRelationManager::class, [
            'ownerRecord' => $file,
            'pageClass' => ViewImportedFile::class,
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords($transactions);
    });

    it('does not show transactions from other files', function () {
        $file = ImportedFile::factory()->create();
        $otherFile = ImportedFile::factory()->create();

        $ours = Transaction::factory()->for($file, 'importedFile')->create();
        $theirs = Transaction::factory()->for($otherFile, 'importedFile')->create();

        livewire(TransactionsRelationManager::class, [
            'ownerRecord' => $file,
            'pageClass' => ViewImportedFile::class,
        ])
            ->assertCanSeeTableRecords([$ours])
            ->assertCanNotSeeTableRecords([$theirs]);
    });

    it('shows an empty state when the file has no transactions', function () {
        $file = ImportedFile::factory()->create();

        livewire(TransactionsRelationManager::class, [
            'ownerRecord' => $file,
            'pageClass' => ViewImportedFile::class,
        ])
            ->assertSuccessful()
            ->assertCanSeeTableRecords([]);
    });

    it('has a view action for each transaction row', function () {
        $file = ImportedFile::factory()->create();
        $transaction = Transaction::factory()->for($file, 'importedFile')->create();

        livewire(TransactionsRelationManager::class, [
            'ownerRecord' => $file,
            'pageClass' => ViewImportedFile::class,
        ])
            ->assertTableActionExists('view', record: $transaction);
    });

    it('has no create action', function () {
        $file = ImportedFile::factory()->create();

        livewire(TransactionsRelationManager::class, [
            'ownerRecord' => $file,
            'pageClass' => ViewImportedFile::class,
        ])
            ->assertTableActionDoesNotExist('create');
    });
});
