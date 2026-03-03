<?php

use App\Models\Transaction;
use App\Models\TransactionAggregate;

describe('aggregates:rebuild command', function () {
    beforeEach(function () {
        asUser();
    });

    it('runs successfully', function () {
        $this->artisan('aggregates:rebuild')
            ->expectsOutputToContain('Rebuilding transaction aggregates')
            ->expectsOutputToContain('rebuilt successfully')
            ->assertSuccessful();
    });

    it('rebuilds aggregates from transactions', function () {
        $company = tenant();

        Transaction::factory()->debit(1000)->create([
            'company_id' => $company->id,
            'date' => '2025-04-15',
        ]);

        // Delete observer-created aggregates to verify rebuild creates them
        TransactionAggregate::query()->delete();

        $this->artisan('aggregates:rebuild')
            ->assertSuccessful();

        $aggregate = TransactionAggregate::where('company_id', $company->id)->first();
        expect($aggregate)->not->toBeNull()
            ->and((float) $aggregate->total_debit)->toBe(1000.0);
    });

    it('filters by company with --company flag', function () {
        $company = tenant();

        Transaction::factory()->debit(1000)->create([
            'company_id' => $company->id,
            'date' => '2025-04-15',
        ]);

        TransactionAggregate::query()->delete();

        $this->artisan("aggregates:rebuild --company={$company->id}")
            ->expectsOutputToContain("company ID: {$company->id}")
            ->assertSuccessful();

        expect(TransactionAggregate::where('company_id', $company->id)->exists())->toBeTrue();
    });

    it('filters by month with --month flag', function () {
        $company = tenant();

        Transaction::factory()->debit(1000)->create([
            'company_id' => $company->id,
            'date' => '2025-04-15',
        ]);

        TransactionAggregate::query()->delete();

        $this->artisan('aggregates:rebuild --month=2025-04')
            ->expectsOutputToContain('month: 2025-04')
            ->assertSuccessful();

        expect(TransactionAggregate::where('year_month', '2025-04')->exists())->toBeTrue();
    });
});
