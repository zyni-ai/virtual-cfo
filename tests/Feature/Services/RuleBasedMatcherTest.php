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

    it('uses first match wins strategy', function () {
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
