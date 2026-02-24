<?php

use App\Enums\MappingType;
use App\Filament\Resources\TransactionResource;
use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Models\AccountHead;
use App\Models\ImportedFile;
use App\Models\Transaction;

use function Pest\Livewire\livewire;

describe('TransactionResource', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render the list page', function () {
        livewire(ListTransactions::class)->assertSuccessful();
    });

    it('can list transactions', function () {
        $transactions = Transaction::factory()->count(3)->create();

        livewire(ListTransactions::class)
            ->assertCanSeeTableRecords($transactions);
    });

    it('can filter by mapping type', function () {
        $head = AccountHead::factory()->create();
        $mapped = Transaction::factory()->mapped($head)->create();
        $unmapped = Transaction::factory()->unmapped()->create();

        livewire(ListTransactions::class)
            ->filterTable('mapping_type', MappingType::Unmapped->value)
            ->assertCanSeeTableRecords([$unmapped])
            ->assertCanNotSeeTableRecords([$mapped]);
    });

    it('can filter by imported file', function () {
        $file1 = ImportedFile::factory()->create();
        $file2 = ImportedFile::factory()->create();
        $t1 = Transaction::factory()->for($file1, 'importedFile')->create();
        $t2 = Transaction::factory()->for($file2, 'importedFile')->create();

        livewire(ListTransactions::class)
            ->filterTable('imported_file_id', $file1->id)
            ->assertCanSeeTableRecords([$t1])
            ->assertCanNotSeeTableRecords([$t2]);
    });

    it('uses Transaction model', function () {
        expect(TransactionResource::getModel())->toBe(Transaction::class);
    });
});
