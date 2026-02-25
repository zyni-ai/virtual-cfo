<?php

use App\Enums\MappingType;
use App\Filament\Resources\TransactionResource;
use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Jobs\MatchTransactionHeads;
use App\Models\AccountHead;
use App\Models\ImportedFile;
use App\Models\Transaction;
use Illuminate\Support\Facades\Queue;

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

    it('can bulk assign account head to selected transactions', function () {
        $head = AccountHead::factory()->create();
        $file = ImportedFile::factory()->completed(totalRows: 3, mappedRows: 0)->create();
        $transactions = Transaction::factory()
            ->count(3)
            ->unmapped()
            ->for($file, 'importedFile')
            ->create();

        livewire(ListTransactions::class)
            ->callTableBulkAction('bulk_assign_head', $transactions, [
                'account_head_id' => $head->id,
            ]);

        foreach ($transactions as $transaction) {
            $transaction->refresh();
            expect($transaction->account_head_id)->toBe($head->id)
                ->and($transaction->mapping_type)->toBe(MappingType::Manual)
                ->and($transaction->ai_confidence)->toBeNull();
        }

        // File mapped_rows should be updated
        $file->refresh();
        expect($file->mapped_rows)->toBe(3);
    });

    it('assign head action updates a single transaction to manual mapping', function () {
        $head = AccountHead::factory()->create();
        $file = ImportedFile::factory()->completed(totalRows: 1, mappedRows: 0)->create();
        $transaction = Transaction::factory()
            ->unmapped()
            ->for($file, 'importedFile')
            ->create();

        livewire(ListTransactions::class)
            ->callTableAction('assign_head', $transaction, [
                'account_head_id' => $head->id,
            ]);

        $transaction->refresh();
        expect($transaction->account_head_id)->toBe($head->id)
            ->and($transaction->mapping_type)->toBe(MappingType::Manual)
            ->and($transaction->ai_confidence)->toBeNull();

        // File mapped_rows should be updated
        $file->refresh();
        expect($file->mapped_rows)->toBe(1);
    });

    it('run AI matching header action dispatches MatchTransactionHeads jobs', function () {
        Queue::fake();

        $fileWithUnmapped = ImportedFile::factory()->completed()->create();
        Transaction::factory()
            ->unmapped()
            ->for($fileWithUnmapped, 'importedFile')
            ->create();

        $fileFullyMapped = ImportedFile::factory()->completed()->create();
        Transaction::factory()
            ->mapped()
            ->for($fileFullyMapped, 'importedFile')
            ->create();

        livewire(ListTransactions::class)
            ->callTableAction('run_ai_matching');

        // Job should be dispatched for the file with unmapped transactions
        Queue::assertPushed(MatchTransactionHeads::class, function (MatchTransactionHeads $job) use ($fileWithUnmapped) {
            return $job->importedFile->id === $fileWithUnmapped->id;
        });

        // Job should NOT be dispatched for the file with all mapped transactions
        Queue::assertNotPushed(MatchTransactionHeads::class, function (MatchTransactionHeads $job) use ($fileFullyMapped) {
            return $job->importedFile->id === $fileFullyMapped->id;
        });
    });
});
