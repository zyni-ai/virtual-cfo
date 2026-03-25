<?php

use App\Enums\MappingType;
use App\Enums\MatchType;
use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Models\AccountHead;
use App\Models\HeadMapping;
use App\Models\ImportedFile;
use App\Models\Transaction;
use App\Services\RuleSuggestion\RuleSuggestionService;

use function Pest\Livewire\livewire;

describe('RuleSuggestionService', function () {
    it('extracts keyword from description', function () {
        $service = app(RuleSuggestionService::class);

        expect($service->extractKeyword('SALARY MARCH'))->toBe('SALARY')
            ->and($service->extractKeyword('NEFT-123456-ACME CORP'))->toBe('NEFT')
            ->and($service->extractKeyword('UPI/123456/John Smith'))->toBe('John')
            ->and($service->extractKeyword('CC PAYMENT'))->toBe('PAYMENT');
    });

    it('counts similar unmapped transactions in the same import', function () {
        asUser();

        $file = ImportedFile::factory()->create(['company_id' => tenant()->id]);
        $transaction = Transaction::factory()
            ->for($file, 'importedFile')
            ->unmapped()
            ->create(['description' => 'SALARY MARCH', 'company_id' => tenant()->id]);

        // Similar unmapped transactions in the same import
        Transaction::factory()->count(3)
            ->for($file, 'importedFile')
            ->unmapped()
            ->create(['description' => 'SALARY APRIL', 'company_id' => tenant()->id]);

        // Different import — should not count
        Transaction::factory()->unmapped()->create([
            'description' => 'SALARY MAY',
            'company_id' => tenant()->id,
        ]);

        $service = app(RuleSuggestionService::class);
        $count = $service->countSimilarUnmapped($transaction, 'SALARY');

        expect($count)->toBe(3);
    });

    it('returns null suggestion when no similar unmapped transactions exist', function () {
        $user = asUser();

        $file = ImportedFile::factory()->create(['company_id' => tenant()->id]);
        $transaction = Transaction::factory()
            ->for($file, 'importedFile')
            ->mapped()
            ->create(['description' => 'SALARY MARCH', 'company_id' => tenant()->id]);

        $service = app(RuleSuggestionService::class);
        $suggestion = $service->suggest($transaction, $user, tenant()->id);

        expect($suggestion)->toBeNull();
    });

    it('returns a suggestion with pattern and match count when similar transactions exist', function () {
        $user = asUser();

        $file = ImportedFile::factory()->create(['company_id' => tenant()->id]);
        $head = AccountHead::factory()->create(['company_id' => tenant()->id]);

        $transaction = Transaction::factory()
            ->for($file, 'importedFile')
            ->mapped($head)
            ->create(['description' => 'SALARY MARCH', 'company_id' => tenant()->id]);

        Transaction::factory()->count(2)
            ->for($file, 'importedFile')
            ->unmapped()
            ->create(['description' => 'SALARY APRIL', 'company_id' => tenant()->id]);

        $service = app(RuleSuggestionService::class);
        $suggestion = $service->suggest($transaction, $user, tenant()->id);

        expect($suggestion)->not->toBeNull()
            ->and($suggestion->pattern)->toBe('SALARY')
            ->and($suggestion->matchCount)->toBe(2)
            ->and($suggestion->accountHeadId)->toBe($head->id);
    });

    it('returns null for dismissed patterns', function () {
        $user = asUser();
        $companyId = tenant()->id;
        $user->update([
            'dismissed_suggestions' => ["{$companyId}:SALARY"],
        ]);

        $file = ImportedFile::factory()->create(['company_id' => $companyId]);
        $head = AccountHead::factory()->create(['company_id' => $companyId]);

        $transaction = Transaction::factory()
            ->for($file, 'importedFile')
            ->mapped($head)
            ->create(['description' => 'SALARY MARCH', 'company_id' => $companyId]);

        Transaction::factory()
            ->for($file, 'importedFile')
            ->unmapped()
            ->create(['description' => 'SALARY APRIL', 'company_id' => $companyId]);

        $service = app(RuleSuggestionService::class);
        $suggestion = $service->suggest($transaction, $user, $companyId);

        expect($suggestion)->toBeNull();
    });
});

describe('Rule suggestion after assign_head action', function () {
    beforeEach(fn () => asUser());

    it('sends a suggestion notification when similar unmapped transactions exist', function () {
        $file = ImportedFile::factory()->completed(totalRows: 3, mappedRows: 0)->create(['company_id' => tenant()->id]);
        $head = AccountHead::factory()->create(['company_id' => tenant()->id]);

        $transaction = Transaction::factory()
            ->for($file, 'importedFile')
            ->unmapped()
            ->create(['description' => 'SALARY MARCH', 'company_id' => tenant()->id]);

        Transaction::factory()->count(2)
            ->for($file, 'importedFile')
            ->unmapped()
            ->create(['description' => 'SALARY APRIL', 'company_id' => tenant()->id]);

        livewire(ListTransactions::class)
            ->callTableAction('assign_head', $transaction, [
                'account_head_id' => $head->id,
            ])
            ->assertNotified('Create a mapping rule?');
    });

    it('does not send suggestion notification when no similar unmapped transactions exist', function () {
        $file = ImportedFile::factory()->completed(totalRows: 1, mappedRows: 0)->create(['company_id' => tenant()->id]);
        $head = AccountHead::factory()->create(['company_id' => tenant()->id]);

        $transaction = Transaction::factory()
            ->for($file, 'importedFile')
            ->unmapped()
            ->create(['description' => 'SALARY MARCH', 'company_id' => tenant()->id]);

        livewire(ListTransactions::class)
            ->callTableAction('assign_head', $transaction, [
                'account_head_id' => $head->id,
            ])
            ->assertNotNotified('Create a mapping rule?');
    });

    it('does not send suggestion notification for dismissed patterns', function () {
        $user = auth()->user();
        $companyId = tenant()->id;
        $user->update(['dismissed_suggestions' => ["{$companyId}:SALARY"]]);

        $file = ImportedFile::factory()->completed(totalRows: 2, mappedRows: 0)->create(['company_id' => $companyId]);
        $head = AccountHead::factory()->create(['company_id' => $companyId]);

        $transaction = Transaction::factory()
            ->for($file, 'importedFile')
            ->unmapped()
            ->create(['description' => 'SALARY MARCH', 'company_id' => $companyId]);

        Transaction::factory()
            ->for($file, 'importedFile')
            ->unmapped()
            ->create(['description' => 'SALARY APRIL', 'company_id' => $companyId]);

        livewire(ListTransactions::class)
            ->callTableAction('assign_head', $transaction, [
                'account_head_id' => $head->id,
            ])
            ->assertNotNotified('Create a mapping rule?');
    });
});

describe('Rule suggestion after bulk assign_head action', function () {
    beforeEach(fn () => asUser());

    it('sends a suggestion notification after bulk assign when similar unmapped transactions exist', function () {
        $file = ImportedFile::factory()->completed(totalRows: 5, mappedRows: 0)->create(['company_id' => tenant()->id]);
        $head = AccountHead::factory()->create(['company_id' => tenant()->id]);

        $selected = Transaction::factory()->count(2)
            ->for($file, 'importedFile')
            ->unmapped()
            ->create(['description' => 'SALARY MARCH', 'company_id' => tenant()->id]);

        // Additional similar unmapped transactions not being mapped in this batch
        Transaction::factory()->count(3)
            ->for($file, 'importedFile')
            ->unmapped()
            ->create(['description' => 'SALARY APRIL', 'company_id' => tenant()->id]);

        livewire(ListTransactions::class)
            ->callTableBulkAction('bulk_assign_head', $selected, [
                'account_head_id' => $head->id,
            ])
            ->assertNotified('Create a mapping rule?');
    });
});

describe('dismissRuleSuggestion listener', function () {
    it('stores dismissed pattern in user dismissed_suggestions', function () {
        $user = asUser();
        $companyId = tenant()->id;

        livewire(ListTransactions::class)
            ->dispatch('dismissRuleSuggestion', pattern: 'SALARY', companyId: $companyId);

        $user->refresh();
        expect($user->dismissed_suggestions)->toContain("{$companyId}:SALARY");
    });

    it('does not duplicate dismissed patterns', function () {
        $user = asUser();
        $companyId = tenant()->id;
        $user->update(['dismissed_suggestions' => ["{$companyId}:SALARY"]]);

        livewire(ListTransactions::class)
            ->dispatch('dismissRuleSuggestion', pattern: 'SALARY', companyId: $companyId);

        $user->refresh();
        expect(array_count_values($user->dismissed_suggestions)["{$companyId}:SALARY"])->toBe(1);
    });
});

describe('openRuleSuggestion listener mounts suggestRule action', function () {
    it('mounts the suggest rule action with pre-filled data', function () {
        asUser();

        $head = AccountHead::factory()->create(['company_id' => tenant()->id]);
        $file = ImportedFile::factory()->create(['company_id' => tenant()->id]);

        livewire(ListTransactions::class)
            ->dispatch('openRuleSuggestion', [
                'pattern' => 'SALARY',
                'accountHeadId' => $head->id,
                'importedFileId' => $file->id,
                'matchCount' => 3,
            ])
            ->assertActionMounted('suggestRule');
    });

    it('pre-fills the suggestRule form with pattern and account head from the suggestion', function () {
        asUser();

        $head = AccountHead::factory()->create(['company_id' => tenant()->id]);
        $file = ImportedFile::factory()->create(['company_id' => tenant()->id]);

        livewire(ListTransactions::class)
            ->dispatch('openRuleSuggestion', [
                'pattern' => 'SALARY',
                'accountHeadId' => $head->id,
                'importedFileId' => $file->id,
                'matchCount' => 3,
            ])
            ->assertSchemaStateSet([
                'pattern' => 'SALARY',
                'account_head_id' => $head->id,
                'imported_file_id' => (string) $file->id,
            ]);
    });
});

describe('suggestRule action creates rule and optionally applies it', function () {
    beforeEach(fn () => asUser());

    it('creates a HeadMapping when the suggest rule form is submitted', function () {
        $head = AccountHead::factory()->create(['company_id' => tenant()->id]);
        $file = ImportedFile::factory()->create(['company_id' => tenant()->id]);

        livewire(ListTransactions::class)
            ->callAction('suggestRule', [
                'pattern' => 'SALARY',
                'match_type' => MatchType::Contains->value,
                'account_head_id' => $head->id,
                'apply_immediately' => false,
                'imported_file_id' => $file->id,
            ])
            ->assertHasNoActionErrors();

        expect(HeadMapping::where('pattern', 'SALARY')->exists())->toBeTrue();
    });

    it('applies rule immediately to unmapped transactions when apply_immediately is true', function () {
        $head = AccountHead::factory()->create(['company_id' => tenant()->id]);
        $file = ImportedFile::factory()->create(['company_id' => tenant()->id]);

        $unmapped = Transaction::factory()->count(2)
            ->for($file, 'importedFile')
            ->unmapped()
            ->create(['description' => 'SALARY APRIL', 'company_id' => tenant()->id]);

        // One that should NOT be matched
        $other = Transaction::factory()
            ->for($file, 'importedFile')
            ->unmapped()
            ->create(['description' => 'AMAZON PAYMENT', 'company_id' => tenant()->id]);

        livewire(ListTransactions::class)
            ->callAction('suggestRule', [
                'pattern' => 'SALARY',
                'match_type' => MatchType::Contains->value,
                'account_head_id' => $head->id,
                'apply_immediately' => true,
                'imported_file_id' => $file->id,
            ])
            ->assertHasNoActionErrors();

        foreach ($unmapped as $t) {
            $t->refresh();
            expect($t->account_head_id)->toBe($head->id)
                ->and($t->mapping_type)->toBe(MappingType::Auto);
        }

        $other->refresh();
        expect($other->account_head_id)->toBeNull();
    });

    it('does not apply rule when apply_immediately is false', function () {
        $head = AccountHead::factory()->create(['company_id' => tenant()->id]);
        $file = ImportedFile::factory()->create(['company_id' => tenant()->id]);

        $unmapped = Transaction::factory()
            ->for($file, 'importedFile')
            ->unmapped()
            ->create(['description' => 'SALARY APRIL', 'company_id' => tenant()->id]);

        livewire(ListTransactions::class)
            ->callAction('suggestRule', [
                'pattern' => 'SALARY',
                'match_type' => MatchType::Contains->value,
                'account_head_id' => $head->id,
                'apply_immediately' => false,
                'imported_file_id' => $file->id,
            ])
            ->assertHasNoActionErrors();

        $unmapped->refresh();
        expect($unmapped->account_head_id)->toBeNull();
    });
});
