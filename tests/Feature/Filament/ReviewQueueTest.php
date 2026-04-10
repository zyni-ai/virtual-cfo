<?php

use App\Enums\MappingType;
use App\Filament\Pages\ReviewQueue;
use App\Models\AccountHead;
use App\Models\Company;
use App\Models\Transaction;
use Filament\Actions\Testing\TestAction;

use function Pest\Livewire\livewire;

describe('ReviewQueue page', function () {
    beforeEach(function () {
        asUser();
        $this->company = tenant();
    });

    it('can render the review queue page', function () {
        livewire(ReviewQueue::class)->assertSuccessful();
    });

    it('lists AI-mapped transactions below confidence threshold', function () {
        $this->company->update(['review_confidence_threshold' => 0.80]);

        $lowConfidence = Transaction::factory()->for($this->company)->create([
            'mapping_type' => MappingType::Ai,
            'ai_confidence' => 0.65,
            'account_head_id' => AccountHead::factory()->for($this->company)->create()->id,
        ]);

        $highConfidence = Transaction::factory()->for($this->company)->create([
            'mapping_type' => MappingType::Ai,
            'ai_confidence' => 0.95,
            'account_head_id' => AccountHead::factory()->for($this->company)->create()->id,
        ]);

        $manual = Transaction::factory()->for($this->company)->create([
            'mapping_type' => MappingType::Manual,
            'ai_confidence' => 0.50,
        ]);

        livewire(ReviewQueue::class)
            ->assertCanSeeTableRecords([$lowConfidence])
            ->assertCanNotSeeTableRecords([$highConfidence, $manual]);
    });

    it('uses company review_confidence_threshold', function () {
        $this->company->update(['review_confidence_threshold' => 0.90]);

        $belowThreshold = Transaction::factory()->for($this->company)->create([
            'mapping_type' => MappingType::Ai,
            'ai_confidence' => 0.85,
            'account_head_id' => AccountHead::factory()->for($this->company)->create()->id,
        ]);

        livewire(ReviewQueue::class)
            ->assertCanSeeTableRecords([$belowThreshold]);
    });

    it('can approve a transaction mapping', function () {
        $head = AccountHead::factory()->for($this->company)->create();
        $txn = Transaction::factory()->for($this->company)->create([
            'mapping_type' => MappingType::Ai,
            'ai_confidence' => 0.65,
            'account_head_id' => $head->id,
        ]);

        livewire(ReviewQueue::class)
            ->callTableAction('approve', $txn);

        $txn->refresh();
        expect($txn->mapping_type)->toBe(MappingType::Manual)
            ->and($txn->account_head_id)->toBe($head->id);
    });

    it('can reject a transaction mapping', function () {
        $head = AccountHead::factory()->for($this->company)->create();
        $txn = Transaction::factory()->for($this->company)->create([
            'mapping_type' => MappingType::Ai,
            'ai_confidence' => 0.65,
            'account_head_id' => $head->id,
        ]);

        livewire(ReviewQueue::class)
            ->callTableAction('reject', $txn);

        $txn->refresh();
        expect($txn->mapping_type)->toBe(MappingType::Unmapped)
            ->and($txn->account_head_id)->toBeNull();
    });

    it('can reassign a transaction to a different account head', function () {
        $oldHead = AccountHead::factory()->for($this->company)->create();
        $newHead = AccountHead::factory()->for($this->company)->create();
        $txn = Transaction::factory()->for($this->company)->create([
            'mapping_type' => MappingType::Ai,
            'ai_confidence' => 0.65,
            'account_head_id' => $oldHead->id,
        ]);

        livewire(ReviewQueue::class)
            ->callTableAction('reassign', $txn, data: [
                'account_head_id' => $newHead->id,
            ]);

        $txn->refresh();
        expect($txn->mapping_type)->toBe(MappingType::Manual)
            ->and($txn->account_head_id)->toBe($newHead->id);
    });

    it('can bulk approve transactions', function () {
        $head = AccountHead::factory()->for($this->company)->create();
        $txns = Transaction::factory()->count(3)->for($this->company)->create([
            'mapping_type' => MappingType::Ai,
            'ai_confidence' => 0.65,
            'account_head_id' => $head->id,
        ]);

        livewire(ReviewQueue::class)
            ->selectTableRecords($txns->pluck('id')->toArray())
            ->callAction(TestAction::make('bulk_approve')->table()->bulk());

        expect(Transaction::where('mapping_type', MappingType::Manual)->count())->toBe(3);
    });

    it('can bulk reject transactions', function () {
        $head = AccountHead::factory()->for($this->company)->create();
        $txns = Transaction::factory()->count(3)->for($this->company)->create([
            'mapping_type' => MappingType::Ai,
            'ai_confidence' => 0.65,
            'account_head_id' => $head->id,
        ]);

        livewire(ReviewQueue::class)
            ->selectTableRecords($txns->pluck('id')->toArray())
            ->callAction(TestAction::make('bulk_reject')->table()->bulk());

        expect(Transaction::where('mapping_type', MappingType::Unmapped)->count())->toBe(3);
    });

    it('shows navigation badge with pending review count', function () {
        $this->company->update(['review_confidence_threshold' => 0.80]);

        Transaction::factory()->count(5)->for($this->company)->create([
            'mapping_type' => MappingType::Ai,
            'ai_confidence' => 0.65,
            'account_head_id' => AccountHead::factory()->for($this->company)->create()->id,
        ]);

        expect(ReviewQueue::getNavigationBadge())->toBe('5');
    });
});

describe('ReviewQueue tenant isolation', function () {
    beforeEach(function () {
        asUser();
        $this->company = tenant();
        $this->company->update(['review_confidence_threshold' => 0.80]);
    });

    it('does not show transactions from another company', function () {
        $otherCompany = Company::factory()->create(['review_confidence_threshold' => 0.80]);

        $ownTxn = Transaction::factory()->for($this->company)->create([
            'mapping_type' => MappingType::Ai,
            'ai_confidence' => 0.65,
            'account_head_id' => AccountHead::factory()->for($this->company)->create()->id,
        ]);

        Transaction::withoutEvents(function () use ($otherCompany) {
            Transaction::factory()->for($otherCompany)->create([
                'mapping_type' => MappingType::Ai,
                'ai_confidence' => 0.65,
                'account_head_id' => AccountHead::factory()->for($otherCompany)->create()->id,
            ]);
        });

        livewire(ReviewQueue::class)
            ->assertCanSeeTableRecords([$ownTxn])
            ->assertCountTableRecords(1);
    });

    it('shows the correct AI suggested head name', function () {
        $head = AccountHead::factory()->for($this->company)->create(['name' => 'Office Supplies']);

        Transaction::factory()->for($this->company)->create([
            'mapping_type' => MappingType::Ai,
            'ai_confidence' => 0.65,
            'account_head_id' => $head->id,
        ]);

        livewire(ReviewQueue::class)
            ->assertSeeText('Office Supplies');
    });

    it('navigation badge only counts current company transactions', function () {
        $otherCompany = Company::factory()->create(['review_confidence_threshold' => 0.80]);

        Transaction::factory()->count(3)->for($this->company)->create([
            'mapping_type' => MappingType::Ai,
            'ai_confidence' => 0.65,
            'account_head_id' => AccountHead::factory()->for($this->company)->create()->id,
        ]);

        Transaction::withoutEvents(function () use ($otherCompany) {
            Transaction::factory()->count(10)->for($otherCompany)->create([
                'mapping_type' => MappingType::Ai,
                'ai_confidence' => 0.65,
                'account_head_id' => AccountHead::factory()->for($otherCompany)->create()->id,
            ]);
        });

        expect(ReviewQueue::getNavigationBadge())->toBe('3');
    });
});
