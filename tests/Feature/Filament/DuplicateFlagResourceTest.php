<?php

use App\Enums\DuplicateStatus;
use App\Filament\Resources\DuplicateFlags\Pages\ManageDuplicateFlags;
use App\Models\DuplicateFlag;
use App\Models\Transaction;
use Filament\Actions\Testing\TestAction;

use function Pest\Livewire\livewire;

describe('DuplicateFlagResource', function () {
    beforeEach(function () {
        asUser();
        $this->company = tenant();
    });

    it('can render the list page', function () {
        livewire(ManageDuplicateFlags::class)->assertSuccessful();
    });

    it('can list pending duplicate flags', function () {
        $flags = DuplicateFlag::factory()
            ->count(3)
            ->for($this->company)
            ->create(['status' => DuplicateStatus::Pending]);

        livewire(ManageDuplicateFlags::class)
            ->assertCanSeeTableRecords($flags);
    });

    it('can confirm a duplicate flag', function () {
        $t1 = Transaction::factory()->for($this->company)->create();
        $t2 = Transaction::factory()->for($this->company)->create();

        $flag = DuplicateFlag::factory()->for($this->company)->create([
            'transaction_id' => $t1->id,
            'duplicate_transaction_id' => $t2->id,
            'status' => DuplicateStatus::Pending,
        ]);

        livewire(ManageDuplicateFlags::class)
            ->callTableAction('confirm_duplicate', $flag);

        $flag->refresh();
        expect($flag->status)->toBe(DuplicateStatus::Confirmed)
            ->and($flag->resolved_by)->not->toBeNull()
            ->and($flag->resolved_at)->not->toBeNull();

        $t2->refresh();
        expect($t2->duplicate_of_id)->toBe($t1->id)
            ->and($t2->trashed())->toBeTrue();
    });

    it('can dismiss a duplicate flag', function () {
        $flag = DuplicateFlag::factory()->for($this->company)->create([
            'status' => DuplicateStatus::Pending,
        ]);

        livewire(ManageDuplicateFlags::class)
            ->callTableAction('dismiss', $flag);

        $flag->refresh();
        expect($flag->status)->toBe(DuplicateStatus::Dismissed)
            ->and($flag->resolved_by)->not->toBeNull();
    });

    it('can bulk confirm duplicates', function () {
        $t1 = Transaction::factory()->for($this->company)->create();
        $t2 = Transaction::factory()->for($this->company)->create();
        $t3 = Transaction::factory()->for($this->company)->create();
        $t4 = Transaction::factory()->for($this->company)->create();

        $flag1 = DuplicateFlag::factory()->for($this->company)->create([
            'transaction_id' => $t1->id,
            'duplicate_transaction_id' => $t2->id,
            'status' => DuplicateStatus::Pending,
        ]);
        $flag2 = DuplicateFlag::factory()->for($this->company)->create([
            'transaction_id' => $t3->id,
            'duplicate_transaction_id' => $t4->id,
            'status' => DuplicateStatus::Pending,
        ]);

        livewire(ManageDuplicateFlags::class)
            ->selectTableRecords([$flag1->id, $flag2->id])
            ->callAction(TestAction::make('bulk_confirm')->table()->bulk());

        expect(DuplicateFlag::where('status', DuplicateStatus::Confirmed)->count())->toBe(2);
    });

    it('can bulk dismiss duplicates', function () {
        $flags = DuplicateFlag::factory()
            ->count(2)
            ->for($this->company)
            ->create(['status' => DuplicateStatus::Pending]);

        livewire(ManageDuplicateFlags::class)
            ->selectTableRecords($flags->pluck('id')->toArray())
            ->callAction(TestAction::make('bulk_dismiss')->table()->bulk());

        expect(DuplicateFlag::where('status', DuplicateStatus::Dismissed)->count())->toBe(2);
    });

    it('shows navigation badge for pending flags', function () {
        DuplicateFlag::factory()
            ->count(3)
            ->for($this->company)
            ->create(['status' => DuplicateStatus::Pending]);

        DuplicateFlag::factory()
            ->for($this->company)
            ->dismissed()
            ->create();

        expect(\App\Filament\Resources\DuplicateFlags\DuplicateFlagResource::getNavigationBadge())
            ->toBe('3');
    });

    it('hides confirm and dismiss actions for resolved flags', function () {
        $t1 = Transaction::factory()->for($this->company)->create();
        $t2 = Transaction::factory()->for($this->company)->create();

        $flag = DuplicateFlag::factory()->for($this->company)->create([
            'transaction_id' => $t1->id,
            'duplicate_transaction_id' => $t2->id,
            'status' => DuplicateStatus::Confirmed,
            'resolved_at' => now(),
        ]);

        livewire(ManageDuplicateFlags::class)
            ->filterTable('status', DuplicateStatus::Confirmed->value)
            ->assertTableActionHidden('confirm_duplicate', $flag)
            ->assertTableActionHidden('dismiss', $flag);
    });
});
