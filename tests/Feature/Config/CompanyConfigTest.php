<?php

use App\Support\GstinValidator;

describe('GSTIN Validation', function () {
    it('accepts a valid 15-character GSTIN', function () {
        expect(GstinValidator::isValid('29AABCZ5012F1ZG'))->toBeTrue();
    });

    it('rejects a GSTIN shorter than 15 characters', function () {
        expect(GstinValidator::isValid('29AABCZ5012F1Z'))->toBeFalse();
    });

    it('rejects a GSTIN longer than 15 characters', function () {
        expect(GstinValidator::isValid('29AABCZ5012F1ZGX'))->toBeFalse();
    });

    it('rejects a GSTIN with special characters', function () {
        expect(GstinValidator::isValid('29AABCZ5012F1Z!'))->toBeFalse();
    });

    it('rejects an empty string', function () {
        expect(GstinValidator::isValid(''))->toBeFalse();
    });

    it('rejects a null value', function () {
        expect(GstinValidator::isValid(null))->toBeFalse();
    });

    it('validates state code is between 01 and 38', function () {
        expect(GstinValidator::isValid('29AABCZ5012F1ZG'))->toBeTrue()
            ->and(GstinValidator::isValid('00AABCZ5012F1ZG'))->toBeFalse()
            ->and(GstinValidator::isValid('39AABCZ5012F1ZG'))->toBeFalse();
    });
});
