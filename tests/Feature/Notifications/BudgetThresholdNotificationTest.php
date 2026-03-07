<?php

use App\Models\AccountHead;
use App\Models\Budget;
use App\Notifications\BudgetThresholdNotification;
use Illuminate\Support\Facades\Notification;

describe('BudgetThresholdNotification', function () {
    beforeEach(function () {
        $this->user = asUser();
        $this->company = tenant();
    });

    it('sends only database notification at 80% threshold', function () {
        Notification::fake();

        $head = AccountHead::factory()->for($this->company)->create();
        $budget = Budget::factory()->for($this->company)->create([
            'account_head_id' => $head->id,
            'amount' => 100000,
        ]);

        $notification = new BudgetThresholdNotification(
            budget: $budget,
            actual: 85000,
            percentage: 85.0,
            threshold: 80,
        );

        $this->user->notify($notification);

        Notification::assertSentTo($this->user, BudgetThresholdNotification::class, function ($n) {
            return $n->threshold === 80
                && $n->via($this->user) === ['database'];
        });
    });

    it('sends database and email notification at 100% threshold', function () {
        Notification::fake();

        $head = AccountHead::factory()->for($this->company)->create();
        $budget = Budget::factory()->for($this->company)->create([
            'account_head_id' => $head->id,
            'amount' => 100000,
        ]);

        $notification = new BudgetThresholdNotification(
            budget: $budget,
            actual: 110000,
            percentage: 110.0,
            threshold: 100,
        );

        $this->user->notify($notification);

        Notification::assertSentTo($this->user, BudgetThresholdNotification::class, function ($n) {
            return $n->threshold === 100
                && $n->via($this->user) === ['database', 'mail'];
        });
    });

    it('formats database notification correctly', function () {
        $head = AccountHead::factory()->for($this->company)->create(['name' => 'Office Rent']);
        $budget = Budget::factory()->for($this->company)->create([
            'account_head_id' => $head->id,
            'amount' => 100000,
        ]);

        $notification = new BudgetThresholdNotification(
            budget: $budget,
            actual: 85000,
            percentage: 85.0,
            threshold: 80,
        );

        $data = $notification->toDatabase($this->user);
        expect($data['body'])->toContain('Office Rent')
            ->and($data['body'])->toContain('85.0%');
    });
});
