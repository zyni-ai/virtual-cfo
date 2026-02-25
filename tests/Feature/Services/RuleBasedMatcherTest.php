<?php

use App\Enums\MappingType;
use App\Enums\MatchType;
use App\Models\AccountHead;
use App\Models\HeadMapping;
use App\Models\ImportedFile;
use App\Models\Transaction;
use App\Services\HeadMatcher\RuleBasedMatcher;

describe('RuleBasedMatcher::match()', function () {
    it('matches transactions against contains rules', function () {
        $head = AccountHead::factory()->create();
        HeadMapping::factory()->create([
            'pattern' => 'SALARY',
            'match_type' => MatchType::Contains,
            'account_head_id' => $head->id,
        ]);

        $file = ImportedFile::factory()->create();
        $transaction = Transaction::factory()->unmapped()->for($file)->create(['description' => 'SALARY JUNE 2024']);

        $matcher = new RuleBasedMatcher;
        $matches = $matcher->match($file->transactions);

        expect($matches)->toHaveCount(1)
            ->and($matches[0]['transaction_id'])->toBe($transaction->id)
            ->and($matches[0]['account_head_id'])->toBe($head->id);
    });

    it('skips already-mapped transactions', function () {
        $head = AccountHead::factory()->create();
        HeadMapping::factory()->create([
            'pattern' => 'SALARY',
            'match_type' => MatchType::Contains,
            'account_head_id' => $head->id,
        ]);

        $file = ImportedFile::factory()->create();
        Transaction::factory()->mapped($head)->for($file)->create(['description' => 'SALARY JUNE']);

        $matcher = new RuleBasedMatcher;
        $matches = $matcher->match($file->transactions);

        expect($matches)->toBeEmpty();
    });

    it('filters rules by bank name when provided', function () {
        $head = AccountHead::factory()->create();
        HeadMapping::factory()->forBank('HDFC')->create([
            'pattern' => 'NEFT',
            'match_type' => MatchType::Contains,
            'account_head_id' => $head->id,
        ]);

        $file = ImportedFile::factory()->create();
        $transaction = Transaction::factory()->unmapped()->for($file)->create(['description' => 'NEFT TRANSFER']);

        $matcher = new RuleBasedMatcher;

        // ICICI bank should not match HDFC-specific rules
        $matches = $matcher->match($file->transactions, 'ICICI');
        expect($matches)->toBeEmpty();

        // HDFC should match
        $matches = $matcher->match($file->transactions, 'HDFC');
        expect($matches)->toHaveCount(1);
    });

    it('increments usage_count on match', function () {
        $head = AccountHead::factory()->create();
        $mapping = HeadMapping::factory()->create([
            'pattern' => 'EMI',
            'match_type' => MatchType::Contains,
            'account_head_id' => $head->id,
            'usage_count' => 0,
        ]);

        $file = ImportedFile::factory()->create();
        Transaction::factory()->unmapped()->for($file)->create(['description' => 'EMI PAYMENT']);

        $matcher = new RuleBasedMatcher;
        $matcher->match($file->transactions);

        expect($mapping->fresh()->usage_count)->toBe(1);
    });

    it('bulk increments usage_count for multiple matches on same rule', function () {
        $head = AccountHead::factory()->create();
        $mapping = HeadMapping::factory()->create([
            'pattern' => 'EMI',
            'match_type' => MatchType::Contains,
            'account_head_id' => $head->id,
            'usage_count' => 5,
        ]);

        $file = ImportedFile::factory()->create();
        Transaction::factory()->unmapped()->for($file)->count(3)->create(['description' => 'EMI PAYMENT']);

        $matcher = new RuleBasedMatcher;
        $matches = $matcher->match($file->transactions);

        expect($matches)->toHaveCount(3)
            ->and($mapping->fresh()->usage_count)->toBe(8);
    });

    it('bulk increments usage_count for multiple rules correctly', function () {
        $head1 = AccountHead::factory()->create();
        $head2 = AccountHead::factory()->create();

        $mapping1 = HeadMapping::factory()->create([
            'pattern' => 'SALARY',
            'match_type' => MatchType::Contains,
            'account_head_id' => $head1->id,
            'usage_count' => 0,
        ]);
        $mapping2 = HeadMapping::factory()->create([
            'pattern' => 'EMI',
            'match_type' => MatchType::Contains,
            'account_head_id' => $head2->id,
            'usage_count' => 2,
        ]);

        $file = ImportedFile::factory()->create();
        Transaction::factory()->unmapped()->for($file)->count(2)->create(['description' => 'SALARY JUNE']);
        Transaction::factory()->unmapped()->for($file)->count(3)->create(['description' => 'EMI PAYMENT']);

        $matcher = new RuleBasedMatcher;
        $matches = $matcher->match($file->transactions()->get());

        expect($matches)->toHaveCount(5)
            ->and($mapping1->fresh()->usage_count)->toBe(2)
            ->and($mapping2->fresh()->usage_count)->toBe(5);
    });

    it('uses first match wins from priority-ordered rules', function () {
        $head1 = AccountHead::factory()->create(['name' => 'Head 1']);
        $head2 = AccountHead::factory()->create(['name' => 'Head 2']);

        HeadMapping::factory()->create([
            'pattern' => 'SALARY',
            'match_type' => MatchType::Contains,
            'account_head_id' => $head1->id,
        ]);
        HeadMapping::factory()->create([
            'pattern' => 'SALARY JUNE',
            'match_type' => MatchType::Contains,
            'account_head_id' => $head2->id,
        ]);

        $file = ImportedFile::factory()->create();
        Transaction::factory()->unmapped()->for($file)->create(['description' => 'SALARY JUNE 2024']);

        $matcher = new RuleBasedMatcher;
        $matches = $matcher->match($file->transactions);

        expect($matches)->toHaveCount(1);
    });

    it('prioritizes exact match over contains match', function () {
        $exactHead = AccountHead::factory()->create(['name' => 'Exact Head']);
        $containsHead = AccountHead::factory()->create(['name' => 'Contains Head']);

        // Create contains rule first (inserted before exact)
        HeadMapping::factory()->create([
            'pattern' => 'SALARY JUNE 2024',
            'match_type' => MatchType::Contains,
            'account_head_id' => $containsHead->id,
        ]);

        // Create exact rule second (inserted after contains)
        HeadMapping::factory()->create([
            'pattern' => 'SALARY JUNE 2024',
            'match_type' => MatchType::Exact,
            'account_head_id' => $exactHead->id,
        ]);

        $file = ImportedFile::factory()->create();
        Transaction::factory()->unmapped()->for($file)->create(['description' => 'SALARY JUNE 2024']);

        $matcher = new RuleBasedMatcher;
        $matches = $matcher->match($file->transactions);

        expect($matches)->toHaveCount(1)
            ->and($matches[0]['account_head_id'])->toBe($exactHead->id);
    });

    it('prioritizes regex match over contains match', function () {
        $regexHead = AccountHead::factory()->create(['name' => 'Regex Head']);
        $containsHead = AccountHead::factory()->create(['name' => 'Contains Head']);

        // Create contains rule first
        HeadMapping::factory()->create([
            'pattern' => 'NEFT',
            'match_type' => MatchType::Contains,
            'account_head_id' => $containsHead->id,
        ]);

        // Create regex rule second
        HeadMapping::factory()->create([
            'pattern' => '/NEFT[-\/]\d+/i',
            'match_type' => MatchType::Regex,
            'account_head_id' => $regexHead->id,
        ]);

        $file = ImportedFile::factory()->create();
        Transaction::factory()->unmapped()->for($file)->create(['description' => 'NEFT-12345 TRANSFER']);

        $matcher = new RuleBasedMatcher;
        $matches = $matcher->match($file->transactions);

        expect($matches)->toHaveCount(1)
            ->and($matches[0]['account_head_id'])->toBe($regexHead->id);
    });

    it('prioritizes exact match over regex match', function () {
        $exactHead = AccountHead::factory()->create(['name' => 'Exact Head']);
        $regexHead = AccountHead::factory()->create(['name' => 'Regex Head']);

        // Create regex rule first
        HeadMapping::factory()->create([
            'pattern' => '/^SALARY JUNE 2024$/i',
            'match_type' => MatchType::Regex,
            'account_head_id' => $regexHead->id,
        ]);

        // Create exact rule second
        HeadMapping::factory()->create([
            'pattern' => 'SALARY JUNE 2024',
            'match_type' => MatchType::Exact,
            'account_head_id' => $exactHead->id,
        ]);

        $file = ImportedFile::factory()->create();
        Transaction::factory()->unmapped()->for($file)->create(['description' => 'SALARY JUNE 2024']);

        $matcher = new RuleBasedMatcher;
        $matches = $matcher->match($file->transactions);

        expect($matches)->toHaveCount(1)
            ->and($matches[0]['account_head_id'])->toBe($exactHead->id);
    });

    it('prioritizes bank-specific rule over any-bank rule within same match type', function () {
        $bankHead = AccountHead::factory()->create(['name' => 'Bank-specific Head']);
        $anyHead = AccountHead::factory()->create(['name' => 'Any Bank Head']);

        // Create any-bank rule first
        HeadMapping::factory()->create([
            'pattern' => 'NEFT',
            'match_type' => MatchType::Contains,
            'account_head_id' => $anyHead->id,
            'bank_name' => null,
        ]);

        // Create bank-specific rule second
        HeadMapping::factory()->forBank('HDFC')->create([
            'pattern' => 'NEFT',
            'match_type' => MatchType::Contains,
            'account_head_id' => $bankHead->id,
        ]);

        $file = ImportedFile::factory()->create();
        Transaction::factory()->unmapped()->for($file)->create(['description' => 'NEFT TRANSFER']);

        $matcher = new RuleBasedMatcher;
        $matches = $matcher->match($file->transactions, 'HDFC');

        expect($matches)->toHaveCount(1)
            ->and($matches[0]['account_head_id'])->toBe($bankHead->id);
    });

    it('prioritizes higher usage_count when same match type and bank specificity', function () {
        $popularHead = AccountHead::factory()->create(['name' => 'Popular Head']);
        $newHead = AccountHead::factory()->create(['name' => 'New Head']);

        // Create low-usage rule first
        HeadMapping::factory()->create([
            'pattern' => 'EMI',
            'match_type' => MatchType::Contains,
            'account_head_id' => $newHead->id,
            'usage_count' => 2,
        ]);

        // Create high-usage rule second
        HeadMapping::factory()->create([
            'pattern' => 'EMI',
            'match_type' => MatchType::Contains,
            'account_head_id' => $popularHead->id,
            'usage_count' => 50,
        ]);

        $file = ImportedFile::factory()->create();
        Transaction::factory()->unmapped()->for($file)->create(['description' => 'EMI PAYMENT']);

        $matcher = new RuleBasedMatcher;
        $matches = $matcher->match($file->transactions);

        expect($matches)->toHaveCount(1)
            ->and($matches[0]['account_head_id'])->toBe($popularHead->id);
    });

    it('uses manual priority column to override automatic ordering', function () {
        $manualHead = AccountHead::factory()->create(['name' => 'Manual Priority Head']);
        $exactHead = AccountHead::factory()->create(['name' => 'Exact Head']);

        // Create an exact match rule (normally highest specificity)
        HeadMapping::factory()->create([
            'pattern' => 'SALARY JUNE 2024',
            'match_type' => MatchType::Exact,
            'account_head_id' => $exactHead->id,
            'priority' => null,
        ]);

        // Create a contains match rule with manual priority override
        HeadMapping::factory()->create([
            'pattern' => 'SALARY',
            'match_type' => MatchType::Contains,
            'account_head_id' => $manualHead->id,
            'priority' => 1, // Highest priority (lower number = higher)
        ]);

        $file = ImportedFile::factory()->create();
        Transaction::factory()->unmapped()->for($file)->create(['description' => 'SALARY JUNE 2024']);

        $matcher = new RuleBasedMatcher;
        $matches = $matcher->match($file->transactions);

        expect($matches)->toHaveCount(1)
            ->and($matches[0]['account_head_id'])->toBe($manualHead->id);
    });

    it('resolves overlapping rules deterministically', function () {
        $head1 = AccountHead::factory()->create(['name' => 'Head A']);
        $head2 = AccountHead::factory()->create(['name' => 'Head B']);
        $head3 = AccountHead::factory()->create(['name' => 'Head C']);

        // Contains rule, no bank, low usage
        HeadMapping::factory()->create([
            'pattern' => 'TRANSFER',
            'match_type' => MatchType::Contains,
            'account_head_id' => $head1->id,
            'usage_count' => 5,
            'bank_name' => null,
        ]);

        // Contains rule, bank-specific, low usage
        HeadMapping::factory()->forBank('HDFC')->create([
            'pattern' => 'TRANSFER',
            'match_type' => MatchType::Contains,
            'account_head_id' => $head2->id,
            'usage_count' => 3,
        ]);

        // Exact rule, no bank, no usage
        HeadMapping::factory()->create([
            'pattern' => 'NEFT TRANSFER TO VENDOR',
            'match_type' => MatchType::Exact,
            'account_head_id' => $head3->id,
            'usage_count' => 0,
            'bank_name' => null,
        ]);

        $file = ImportedFile::factory()->create();
        Transaction::factory()->unmapped()->for($file)->create(['description' => 'NEFT TRANSFER TO VENDOR']);

        $matcher = new RuleBasedMatcher;
        $matches = $matcher->match($file->transactions, 'HDFC');

        // Exact match should win over contains despite bank specificity or usage
        expect($matches)->toHaveCount(1)
            ->and($matches[0]['account_head_id'])->toBe($head3->id);

        // Run it again to verify determinism
        $file2 = ImportedFile::factory()->create();
        Transaction::factory()->unmapped()->for($file2)->create(['description' => 'NEFT TRANSFER TO VENDOR']);

        $matches2 = $matcher->match($file2->transactions, 'HDFC');

        expect($matches2)->toHaveCount(1)
            ->and($matches2[0]['account_head_id'])->toBe($head3->id);
    });

    it('ignores rules with inactive account heads', function () {
        $head = AccountHead::factory()->inactive()->create();
        HeadMapping::factory()->create([
            'pattern' => 'SALARY',
            'match_type' => MatchType::Contains,
            'account_head_id' => $head->id,
        ]);

        $file = ImportedFile::factory()->create();
        Transaction::factory()->unmapped()->for($file)->create(['description' => 'SALARY JUNE']);

        $matcher = new RuleBasedMatcher;
        $matches = $matcher->match($file->transactions);

        expect($matches)->toBeEmpty();
    });
});

describe('RuleBasedMatcher::applyMatches()', function () {
    it('updates transactions with matched account heads', function () {
        $head = AccountHead::factory()->create();
        $file = ImportedFile::factory()->create();
        $transaction = Transaction::factory()->unmapped()->for($file)->create();

        $matcher = new RuleBasedMatcher;
        $count = $matcher->applyMatches([
            ['transaction_id' => $transaction->id, 'account_head_id' => $head->id, 'mapping_id' => 1],
        ]);

        expect($count)->toBe(1);

        $transaction->refresh();
        expect($transaction->account_head_id)->toBe($head->id)
            ->and($transaction->mapping_type)->toBe(MappingType::Auto);
    });

    it('returns 0 when no matches provided', function () {
        $matcher = new RuleBasedMatcher;
        $count = $matcher->applyMatches([]);

        expect($count)->toBe(0);
    });
});
