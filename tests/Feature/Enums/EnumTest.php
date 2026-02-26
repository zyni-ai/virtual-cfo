<?php

use App\Enums\ImportStatus;
use App\Enums\MappingType;
use App\Enums\MatchMethod;
use App\Enums\MatchType;
use App\Enums\ReconciliationStatus;
use App\Enums\StatementType;

describe('ImportStatus', function () {
    it('has correct labels', function () {
        expect(ImportStatus::Pending->getLabel())->toBe('Pending')
            ->and(ImportStatus::Processing->getLabel())->toBe('Processing')
            ->and(ImportStatus::Completed->getLabel())->toBe('Completed')
            ->and(ImportStatus::Failed->getLabel())->toBe('Failed');
    });

    it('has colors', function () {
        expect(ImportStatus::Pending->getColor())->toBeString()
            ->and(ImportStatus::Processing->getColor())->toBeString()
            ->and(ImportStatus::Completed->getColor())->toBeString()
            ->and(ImportStatus::Failed->getColor())->toBeString();
    });

    it('has icons', function () {
        expect(ImportStatus::Pending->getIcon())->toBeString()
            ->and(ImportStatus::Processing->getIcon())->toBeString()
            ->and(ImportStatus::Completed->getIcon())->toBeString()
            ->and(ImportStatus::Failed->getIcon())->toBeString();
    });
});

describe('MappingType', function () {
    it('has correct labels', function () {
        expect(MappingType::Unmapped->getLabel())->toBe('Unmapped')
            ->and(MappingType::Auto->getLabel())->toBe('Auto (Rule)')
            ->and(MappingType::Manual->getLabel())->toBe('Manual')
            ->and(MappingType::Ai->getLabel())->toBe('AI Matched');
    });

    it('has colors', function () {
        expect(MappingType::Unmapped->getColor())->toBeString()
            ->and(MappingType::Auto->getColor())->toBeString()
            ->and(MappingType::Manual->getColor())->toBeString()
            ->and(MappingType::Ai->getColor())->toBeString();
    });
});

describe('MatchType', function () {
    it('has correct labels', function () {
        expect(MatchType::Contains->getLabel())->toBe('Contains')
            ->and(MatchType::Exact->getLabel())->toBe('Exact Match')
            ->and(MatchType::Regex->getLabel())->toBe('Regex');
    });
});

describe('StatementType', function () {
    it('has correct labels', function () {
        expect(StatementType::Bank->getLabel())->toBe('Bank Statement')
            ->and(StatementType::CreditCard->getLabel())->toBe('Credit Card Statement')
            ->and(StatementType::Invoice->getLabel())->toBe('Invoice');
    });
});

describe('ReconciliationStatus', function () {
    it('has correct labels', function () {
        expect(ReconciliationStatus::Unreconciled->getLabel())->toBe('Unreconciled')
            ->and(ReconciliationStatus::Matched->getLabel())->toBe('Matched')
            ->and(ReconciliationStatus::PartiallyMatched->getLabel())->toBe('Partially Matched')
            ->and(ReconciliationStatus::Flagged->getLabel())->toBe('Flagged');
    });

    it('has colors', function () {
        expect(ReconciliationStatus::Unreconciled->getColor())->toBeString()
            ->and(ReconciliationStatus::Matched->getColor())->toBeString()
            ->and(ReconciliationStatus::PartiallyMatched->getColor())->toBeString()
            ->and(ReconciliationStatus::Flagged->getColor())->toBeString();
    });

    it('has icons', function () {
        expect(ReconciliationStatus::Unreconciled->getIcon())->toBeString()
            ->and(ReconciliationStatus::Matched->getIcon())->toBeString()
            ->and(ReconciliationStatus::PartiallyMatched->getIcon())->toBeString()
            ->and(ReconciliationStatus::Flagged->getIcon())->toBeString();
    });
});

describe('MatchMethod', function () {
    it('has correct labels', function () {
        expect(MatchMethod::Amount->getLabel())->toBe('Amount Match')
            ->and(MatchMethod::AmountDate->getLabel())->toBe('Amount + Date')
            ->and(MatchMethod::AmountDateParty->getLabel())->toBe('Amount + Date + Party')
            ->and(MatchMethod::Ai->getLabel())->toBe('AI Matched')
            ->and(MatchMethod::Manual->getLabel())->toBe('Manual');
    });
});
