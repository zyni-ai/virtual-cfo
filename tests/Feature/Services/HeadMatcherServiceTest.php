<?php

use App\Models\AccountHead;
use App\Models\HeadMapping;
use App\Models\ImportedFile;
use App\Models\Transaction;
use App\Services\HeadMatcher\HeadMatcherService;

describe('HeadMatcherService::matchForFile()', function () {
    it('returns zeros when no unmapped transactions', function () {
        $file = ImportedFile::factory()->create();
        $head = AccountHead::factory()->create();
        Transaction::factory()->mapped($head)->for($file)->count(3)->create();

        $service = app(HeadMatcherService::class);
        $results = $service->matchForFile($file);

        expect($results)->toBe(['rule_matched' => 0, 'ai_matched' => 0, 'unmatched' => 0]);
    });

    it('matches transactions using rules first', function () {
        $head = AccountHead::factory()->create(['name' => 'Salary']);
        HeadMapping::factory()->create([
            'pattern' => 'SALARY',
            'match_type' => \App\Enums\MatchType::Contains,
            'account_head_id' => $head->id,
        ]);

        $file = ImportedFile::factory()->create();
        Transaction::factory()->unmapped()->for($file)->create(['description' => 'SALARY JUNE 2024']);

        $service = app(HeadMatcherService::class);
        $results = $service->matchForFile($file);

        expect($results['rule_matched'])->toBe(1);
    });

    it('updates mapped_rows on the file after matching', function () {
        $head = AccountHead::factory()->create();
        HeadMapping::factory()->create([
            'pattern' => 'EMI',
            'match_type' => \App\Enums\MatchType::Contains,
            'account_head_id' => $head->id,
        ]);

        $file = ImportedFile::factory()->create(['mapped_rows' => 0]);
        Transaction::factory()->unmapped()->for($file)->create(['description' => 'EMI PAYMENT']);

        $service = app(HeadMatcherService::class);
        $service->matchForFile($file);

        expect($file->fresh()->mapped_rows)->toBe(1);
    });

    it('can set confidence threshold', function () {
        $service = app(HeadMatcherService::class);
        $result = $service->setConfidenceThreshold(0.5);

        expect($result)->toBeInstanceOf(HeadMatcherService::class);
    });
});
