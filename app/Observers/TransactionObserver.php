<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Services\AggregateService;

class TransactionObserver
{
    public function __construct(private AggregateService $aggregateService) {}

    public function created(Transaction $transaction): void
    {
        $this->aggregateService->incrementForTransaction($transaction);
    }

    public function updated(Transaction $transaction): void
    {
        $trackedFields = ['company_id', 'account_head_id', 'date', 'debit', 'credit'];

        $originalAttributes = [];
        foreach ($trackedFields as $field) {
            if ($transaction->isDirty($field)) {
                $originalAttributes[$field] = $transaction->getOriginal($field);
            }
        }

        if (! empty($originalAttributes)) {
            $this->aggregateService->adjustForUpdate($transaction, $originalAttributes);
        }
    }

    public function deleted(Transaction $transaction): void
    {
        $this->aggregateService->decrementForTransaction($transaction);
    }

    public function restored(Transaction $transaction): void
    {
        $this->aggregateService->incrementForTransaction($transaction);
    }
}
