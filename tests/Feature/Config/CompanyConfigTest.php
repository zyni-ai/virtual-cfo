<?php

use App\Support\GstinValidator;

describe('Company Configuration', function () {
    it('provides company name from config', function () {
        config(['company.name' => 'Zysk Technologies Private Limited - 2025 - 2026']);

        expect(config('company.name'))->toBe('Zysk Technologies Private Limited - 2025 - 2026');
    });

    it('provides company GSTIN from config', function () {
        config(['company.gstin' => '29AABCZ5012F1ZG']);

        expect(config('company.gstin'))->toBe('29AABCZ5012F1ZG');
    });

    it('provides registered state from config', function () {
        config(['company.state' => 'Karnataka']);

        expect(config('company.state'))->toBe('Karnataka');
    });

    it('provides GST registration type from config', function () {
        config(['company.gst_registration_type' => 'Regular']);

        expect(config('company.gst_registration_type'))->toBe('Regular');
    });

    it('provides financial year from config', function () {
        config(['company.financial_year' => '2025-2026']);

        expect(config('company.financial_year'))->toBe('2025-2026');
    });

    it('provides currency with INR as default', function () {
        expect(config('company.currency'))->toBe('INR');
    });

    it('has all expected config keys', function () {
        $config = config('company');

        expect($config)->toBeArray()
            ->toHaveKeys(['name', 'gstin', 'state', 'gst_registration_type', 'financial_year', 'currency']);
    });
});

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
