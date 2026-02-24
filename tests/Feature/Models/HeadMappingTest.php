<?php

use App\Enums\MatchType;
use App\Models\HeadMapping;

describe('HeadMapping::matches()', function () {
    it('matches with contains type (case-insensitive)', function () {
        $mapping = HeadMapping::factory()->create(['pattern' => 'SALARY', 'match_type' => MatchType::Contains]);

        expect($mapping->matches('SALARY JUNE 2024'))->toBeTrue()
            ->and($mapping->matches('salary june 2024'))->toBeTrue()
            ->and($mapping->matches('EMI PAYMENT'))->toBeFalse();
    });

    it('matches with exact type (case-insensitive)', function () {
        $mapping = HeadMapping::factory()->exact()->create(['pattern' => 'CC PAYMENT']);

        expect($mapping->matches('CC PAYMENT'))->toBeTrue()
            ->and($mapping->matches('cc payment'))->toBeTrue()
            ->and($mapping->matches('CC PAYMENT EXTRA'))->toBeFalse();
    });

    it('matches with regex type', function () {
        $mapping = HeadMapping::factory()->regex()->create(['pattern' => '/NEFT[-\/]\d+/i']);

        expect($mapping->matches('NEFT-123456-Company'))->toBeTrue()
            ->and($mapping->matches('neft/789'))->toBeTrue()
            ->and($mapping->matches('UPI TRANSFER'))->toBeFalse();
    });

    it('returns false for invalid regex pattern', function () {
        $mapping = HeadMapping::factory()->create([
            'pattern' => '/invalid[regex',
            'match_type' => MatchType::Regex,
        ]);

        expect($mapping->matches('anything'))->toBeFalse();
    });
});

describe('HeadMapping::isValidRegex()', function () {
    it('validates correct regex patterns', function () {
        expect(HeadMapping::isValidRegex('/NEFT[-\/]\d+/i'))->toBeTrue()
            ->and(HeadMapping::isValidRegex('/^EMI\s+\w+$/i'))->toBeTrue();
    });

    it('rejects invalid regex patterns', function () {
        expect(HeadMapping::isValidRegex('/invalid[regex'))->toBeFalse();
    });
});

describe('HeadMapping relationships', function () {
    it('belongs to an account head', function () {
        $mapping = HeadMapping::factory()->create();

        expect($mapping->accountHead)->not->toBeNull()
            ->and($mapping->accountHead->id)->toBe($mapping->account_head_id);
    });

    it('belongs to a creator', function () {
        $mapping = HeadMapping::factory()->create();

        expect($mapping->creator)->not->toBeNull()
            ->and($mapping->creator->id)->toBe($mapping->created_by);
    });
});
