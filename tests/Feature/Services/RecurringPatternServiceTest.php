<?php

use App\Ai\Agents\HeadMatcher;
use App\Enums\MappingType;
use App\Models\AccountHead;
use App\Models\Company;
use App\Models\ImportedFile;
use App\Models\RecurringPattern;
use App\Models\Transaction;
use App\Services\HeadMatcher\HeadMatcherService;
use App\Services\RecurringPatterns\RecurringPatternService;
use Carbon\Carbon;

describe('RecurringPatternService::normalizeDescription()', function () {
    it('removes numbers from descriptions', function () {
        $service = app(RecurringPatternService::class);

        $result = $service->normalizeDescription('NEFT-123456-ACME CORP');

        expect($result)->not->toContain('123456');
    });

    it('removes noise words like NEFT, UPI, RTGS, IMPS, INR, REF', function () {
        $service = app(RecurringPatternService::class);

        $result = $service->normalizeDescription('NEFT UPI RTGS IMPS INR REF ACME CORP PAYMENT');

        expect($result)->not->toContain('neft')
            ->not->toContain('upi')
            ->not->toContain('rtgs')
            ->not->toContain('imps')
            ->not->toContain('inr')
            ->not->toContain('ref')
            ->and($result)->toContain('acme')
            ->toContain('corp')
            ->toContain('payment');
    });

    it('lowercases the output', function () {
        $service = app(RecurringPatternService::class);

        $result = $service->normalizeDescription('SALARY PAYMENT JUNE');

        expect($result)->toBe($result); // already lowercase
        expect($result)->not->toMatch('/[A-Z]/');
    });

    it('trims extra spaces', function () {
        $service = app(RecurringPatternService::class);

        $result = $service->normalizeDescription('  SALARY   PAYMENT   JUNE  ');

        expect($result)->not->toContain('  ');
        expect($result)->toBe(trim($result));
    });

    it('produces consistent output for similar descriptions', function () {
        $service = app(RecurringPatternService::class);

        $a = $service->normalizeDescription('NEFT-123456-ACME CORP');
        $b = $service->normalizeDescription('NEFT-789012-ACME CORP');

        expect($a)->toBe($b);
    });
});

describe('RecurringPatternService::detectPatterns()', function () {
    beforeEach(function () {
        $this->user = asUser();
        $this->company = tenant();
    });

    it('finds patterns with 3+ occurrences across different months', function () {
        $file = ImportedFile::factory()->create(['company_id' => $this->company->id]);

        // Create 3 similar transactions in different months
        Transaction::factory()->for($file)->create([
            'company_id' => $this->company->id,
            'description' => 'NEFT-111111-ACME CORP',
            'date' => Carbon::parse('2025-01-15'),
            'debit' => '10000',
        ]);
        Transaction::factory()->for($file)->create([
            'company_id' => $this->company->id,
            'description' => 'NEFT-222222-ACME CORP',
            'date' => Carbon::parse('2025-02-15'),
            'debit' => '10500',
        ]);
        Transaction::factory()->for($file)->create([
            'company_id' => $this->company->id,
            'description' => 'NEFT-333333-ACME CORP',
            'date' => Carbon::parse('2025-03-15'),
            'debit' => '9800',
        ]);

        $service = app(RecurringPatternService::class);
        $count = $service->detectPatterns($this->company);

        expect($count)->toBeGreaterThanOrEqual(1);

        $pattern = RecurringPattern::where('company_id', $this->company->id)->first();
        expect($pattern)->not->toBeNull()
            ->and($pattern->occurrence_count)->toBeGreaterThanOrEqual(3);
    });

    it('does not create patterns for fewer than 3 occurrences', function () {
        $file = ImportedFile::factory()->create(['company_id' => $this->company->id]);

        // Only 2 similar transactions
        Transaction::factory()->for($file)->create([
            'company_id' => $this->company->id,
            'description' => 'NEFT-111111-UNIQUE VENDOR',
            'date' => Carbon::parse('2025-01-15'),
            'debit' => '5000',
        ]);
        Transaction::factory()->for($file)->create([
            'company_id' => $this->company->id,
            'description' => 'NEFT-222222-UNIQUE VENDOR',
            'date' => Carbon::parse('2025-02-15'),
            'debit' => '5000',
        ]);

        $service = app(RecurringPatternService::class);
        $count = $service->detectPatterns($this->company);

        $pattern = RecurringPattern::where('company_id', $this->company->id)
            ->where('description_pattern', 'like', '%unique vendor%')
            ->first();

        expect($pattern)->toBeNull();
    });

    it('updates existing patterns on re-detection', function () {
        $file = ImportedFile::factory()->create(['company_id' => $this->company->id]);

        // Create 3 transactions
        foreach (range(1, 3) as $i) {
            Transaction::factory()->for($file)->create([
                'company_id' => $this->company->id,
                'description' => "NEFT-{$i}{$i}{$i}{$i}{$i}{$i}-RECURRING VENDOR",
                'date' => Carbon::parse("2025-0{$i}-15"),
                'debit' => '10000',
            ]);
        }

        $service = app(RecurringPatternService::class);
        $service->detectPatterns($this->company);

        $initialCount = RecurringPattern::where('company_id', $this->company->id)->count();

        // Add a 4th transaction and re-detect
        Transaction::factory()->for($file)->create([
            'company_id' => $this->company->id,
            'description' => 'NEFT-444444-RECURRING VENDOR',
            'date' => Carbon::parse('2025-04-15'),
            'debit' => '10000',
        ]);

        $service->detectPatterns($this->company);

        // Should update, not create duplicate
        $finalCount = RecurringPattern::where('company_id', $this->company->id)->count();
        expect($finalCount)->toBe($initialCount);
    });
});

describe('RecurringPatternService::matchTransaction()', function () {
    beforeEach(function () {
        $this->user = asUser();
        $this->company = tenant();
    });

    it('matches transaction to existing pattern', function () {
        $pattern = RecurringPattern::factory()->create([
            'company_id' => $this->company->id,
            'description_pattern' => 'acme corp',
            'avg_amount' => null,
            'is_active' => true,
        ]);

        $file = ImportedFile::factory()->create(['company_id' => $this->company->id]);
        $transaction = Transaction::factory()->unmapped()->for($file)->create([
            'company_id' => $this->company->id,
            'description' => 'NEFT-999999-ACME CORP',
        ]);

        $service = app(RecurringPatternService::class);
        $result = $service->matchTransaction($transaction);

        expect($result)->not->toBeNull()
            ->and($result->id)->toBe($pattern->id);
    });

    it('auto-maps when pattern has account_head_id', function () {
        $head = AccountHead::factory()->create(['company_id' => $this->company->id]);
        $pattern = RecurringPattern::factory()->create([
            'company_id' => $this->company->id,
            'description_pattern' => 'acme corp',
            'account_head_id' => $head->id,
            'avg_amount' => null,
            'is_active' => true,
        ]);

        $file = ImportedFile::factory()->create(['company_id' => $this->company->id]);
        $transaction = Transaction::factory()->unmapped()->for($file)->create([
            'company_id' => $this->company->id,
            'description' => 'NEFT-999999-ACME CORP',
        ]);

        $service = app(RecurringPatternService::class);
        $service->matchTransaction($transaction);

        $transaction->refresh();
        expect($transaction->mapping_type)->toBe(MappingType::Auto)
            ->and($transaction->account_head_id)->toBe($head->id)
            ->and($transaction->recurring_pattern_id)->toBe($pattern->id);
    });

    it('returns null for no match', function () {
        $file = ImportedFile::factory()->create(['company_id' => $this->company->id]);
        $transaction = Transaction::factory()->unmapped()->for($file)->create([
            'company_id' => $this->company->id,
            'description' => 'COMPLETELY UNIQUE PAYMENT',
        ]);

        $service = app(RecurringPatternService::class);
        $result = $service->matchTransaction($transaction);

        expect($result)->toBeNull();
    });

    it('does not match inactive patterns', function () {
        RecurringPattern::factory()->create([
            'company_id' => $this->company->id,
            'description_pattern' => 'acme corp',
            'avg_amount' => null,
            'is_active' => false,
        ]);

        $file = ImportedFile::factory()->create(['company_id' => $this->company->id]);
        $transaction = Transaction::factory()->unmapped()->for($file)->create([
            'company_id' => $this->company->id,
            'description' => 'NEFT-999999-ACME CORP',
        ]);

        $service = app(RecurringPatternService::class);
        $result = $service->matchTransaction($transaction);

        expect($result)->toBeNull();
    });

    it('respects amount tolerance of ±10%', function () {
        $pattern = RecurringPattern::factory()->create([
            'company_id' => $this->company->id,
            'description_pattern' => 'acme corp',
            'avg_amount' => 10000.00,
            'is_active' => true,
        ]);

        $file = ImportedFile::factory()->create(['company_id' => $this->company->id]);

        // Within tolerance (10% of 10000 = 1000, so 9000-11000 is acceptable)
        $withinTolerance = Transaction::factory()->unmapped()->for($file)->create([
            'company_id' => $this->company->id,
            'description' => 'NEFT-111-ACME CORP',
            'debit' => '10500',
        ]);

        $service = app(RecurringPatternService::class);
        $result = $service->matchTransaction($withinTolerance);
        expect($result)->not->toBeNull();

        // Outside tolerance
        $outsideTolerance = Transaction::factory()->unmapped()->for($file)->create([
            'company_id' => $this->company->id,
            'description' => 'NEFT-222-ACME CORP',
            'debit' => '15000',
        ]);

        $result = $service->matchTransaction($outsideTolerance);
        expect($result)->toBeNull();
    });
});

describe('RecurringPatternService::deactivateStalePatterns()', function () {
    beforeEach(function () {
        $this->user = asUser();
        $this->company = tenant();
    });

    it('deactivates patterns not seen in over 6 months', function () {
        $stalePattern = RecurringPattern::factory()->create([
            'company_id' => $this->company->id,
            'last_seen_at' => Carbon::now()->subMonths(7),
            'is_active' => true,
        ]);

        $freshPattern = RecurringPattern::factory()->create([
            'company_id' => $this->company->id,
            'last_seen_at' => Carbon::now()->subMonths(2),
            'is_active' => true,
        ]);

        $service = app(RecurringPatternService::class);
        $count = $service->deactivateStalePatterns($this->company);

        expect($count)->toBe(1)
            ->and($stalePattern->fresh()->is_active)->toBeFalse()
            ->and($freshPattern->fresh()->is_active)->toBeTrue();
    });
});

describe('RecurringPatternService tenant scoping', function () {
    it('only matches patterns within the same company', function () {
        asUser();
        $company1 = tenant();

        RecurringPattern::factory()->create([
            'company_id' => $company1->id,
            'description_pattern' => 'tenant isolation vendor',
            'avg_amount' => null,
            'is_active' => true,
        ]);

        // Create a second company
        $company2 = Company::factory()->create();

        $file = ImportedFile::factory()->create(['company_id' => $company2->id]);
        $transaction = Transaction::factory()->unmapped()->for($file)->create([
            'company_id' => $company2->id,
            'description' => 'TENANT ISOLATION VENDOR PAYMENT',
        ]);

        $service = app(RecurringPatternService::class);
        $result = $service->matchTransaction($transaction);

        expect($result)->toBeNull();
    });
});

describe('HeadMatcher recurring pattern integration', function () {
    beforeEach(function () {
        $this->user = asUser();
        $this->company = tenant();
    });

    it('matches via recurring pattern before AI and sets mapping_type to Auto', function () {
        $head = AccountHead::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Vendor Payment',
            'is_active' => true,
        ]);

        $pattern = RecurringPattern::factory()->create([
            'company_id' => $this->company->id,
            'description_pattern' => 'acme corp',
            'account_head_id' => $head->id,
            'avg_amount' => null,
            'is_active' => true,
        ]);

        $file = ImportedFile::factory()->create(['company_id' => $this->company->id]);
        $transaction = Transaction::factory()->unmapped()->for($file)->create([
            'company_id' => $this->company->id,
            'description' => 'NEFT-999999-ACME CORP',
        ]);

        // Fake AI — it should NOT be called for this transaction
        HeadMatcher::fake([['matches' => []]]);

        $service = app(HeadMatcherService::class);
        $results = $service->matchForFile($file);

        $transaction->refresh();
        expect($transaction->mapping_type)->toBe(MappingType::Auto)
            ->and($transaction->account_head_id)->toBe($head->id)
            ->and($transaction->recurring_pattern_id)->toBe($pattern->id)
            ->and($results['recurring_matched'])->toBeGreaterThanOrEqual(1);
    });
});
