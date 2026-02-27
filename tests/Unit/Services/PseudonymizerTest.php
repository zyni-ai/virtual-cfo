<?php

use App\Services\DataPrivacy\Pseudonymizer;

beforeEach(function () {
    $this->pseudonymizer = new Pseudonymizer;
});

describe('Pseudonymizer::mask()', function () {
    it('masks GSTIN numbers', function () {
        $text = 'Payment to 29AABCU9603R1ZM vendor';
        $masked = $this->pseudonymizer->mask($text);

        expect($masked)->not->toContain('29AABCU9603R1ZM')
            ->and($masked)->toContain('[GSTIN_1]');
    });

    it('masks PAN numbers', function () {
        $text = 'TDS deducted PAN ABCDE1234F ref';
        $masked = $this->pseudonymizer->mask($text);

        expect($masked)->not->toContain('ABCDE1234F')
            ->and($masked)->toContain('[PAN_1]');
    });

    it('masks IFSC codes', function () {
        $text = 'NEFT to HDFC0001234 branch';
        $masked = $this->pseudonymizer->mask($text);

        expect($masked)->not->toContain('HDFC0001234')
            ->and($masked)->toContain('[IFSC_1]');
    });

    it('masks email addresses', function () {
        $text = 'Invoice from vendor@example.com received';
        $masked = $this->pseudonymizer->mask($text);

        expect($masked)->not->toContain('vendor@example.com')
            ->and($masked)->toContain('[EMAIL_1]');
    });

    it('masks UPI handles', function () {
        $text = 'UPI payment to merchant@okicici done';
        $masked = $this->pseudonymizer->mask($text);

        expect($masked)->not->toContain('merchant@okicici')
            ->and($masked)->toContain('[UPI_1]');
    });

    it('masks account numbers (10-18 digits)', function () {
        $text = 'Transfer to A/C 50100123456789 completed';
        $masked = $this->pseudonymizer->mask($text);

        expect($masked)->not->toContain('50100123456789')
            ->and($masked)->toContain('[ACCT_1]');
    });

    it('masks phone numbers', function () {
        $text = 'UPI/9876543210/payment ref';
        $masked = $this->pseudonymizer->mask($text);

        expect($masked)->not->toContain('9876543210')
            ->and($masked)->toContain('[PHONE_1]');
    });

    it('does not mask short digit sequences', function () {
        $text = 'Transaction 12345 on 2024-01-15';
        $masked = $this->pseudonymizer->mask($text);

        expect($masked)->toBe($text);
    });

    it('preserves transaction keywords', function () {
        $text = 'NEFT/RTGS/UPI SALARY PAYMENT TDS GST';
        $masked = $this->pseudonymizer->mask($text);

        expect($masked)->toContain('NEFT')
            ->and($masked)->toContain('RTGS')
            ->and($masked)->toContain('UPI')
            ->and($masked)->toContain('SALARY')
            ->and($masked)->toContain('PAYMENT')
            ->and($masked)->toContain('TDS')
            ->and($masked)->toContain('GST');
    });

    it('handles multiple PII types in one string', function () {
        $text = 'UPI/9876543210@okicici A/C 50100123456789 PAN ABCDE1234F';
        $masked = $this->pseudonymizer->mask($text);

        expect($masked)->not->toContain('9876543210@okicici')
            ->and($masked)->not->toContain('50100123456789')
            ->and($masked)->not->toContain('ABCDE1234F')
            ->and($masked)->toContain('[UPI_1]')
            ->and($masked)->toContain('[ACCT_1]')
            ->and($masked)->toContain('[PAN_1]');
    });

    it('assigns stable tokens for same values', function () {
        $text = 'From 9876543210 to 9876543210 ref';
        $masked = $this->pseudonymizer->mask($text);

        expect($masked)->toBe('From [PHONE_1] to [PHONE_1] ref');
    });

    it('assigns different tokens for different values of same type', function () {
        $text = 'From 9876543210 to 8765432109 ref';
        $masked = $this->pseudonymizer->mask($text);

        expect($masked)->toContain('[PHONE_1]')
            ->and($masked)->toContain('[PHONE_2]');
    });
});

describe('Pseudonymizer::unmask()', function () {
    it('round-trips mask and unmask preserving original', function () {
        $original = 'Payment to 29AABCU9603R1ZM via UPI/9876543210@okicici A/C 50100123456789';
        $masked = $this->pseudonymizer->mask($original);
        $unmasked = $this->pseudonymizer->unmask($masked);

        expect($unmasked)->toBe($original);
    });

    it('round-trips multiple PII types', function () {
        $original = 'PAN ABCDE1234F IFSC HDFC0001234 email vendor@example.com phone 9876543210';
        $masked = $this->pseudonymizer->mask($original);
        $unmasked = $this->pseudonymizer->unmask($masked);

        expect($unmasked)->toBe($original);
    });

    it('returns text unchanged when no tokens present', function () {
        $text = 'Regular text without tokens';
        $result = $this->pseudonymizer->unmask($text);

        expect($result)->toBe($text);
    });
});

describe('Pseudonymizer::reset()', function () {
    it('clears state so tokens are reassigned', function () {
        $text = 'Phone 9876543210';
        $masked1 = $this->pseudonymizer->mask($text);

        $this->pseudonymizer->reset();

        $masked2 = $this->pseudonymizer->mask($text);

        // Token name is the same ([PHONE_1]) but the internal map was cleared and rebuilt
        expect($masked1)->toBe($masked2)
            ->and($this->pseudonymizer->unmask($masked2))->toBe($text);
    });

    it('allows new values to get token 1 after reset', function () {
        $this->pseudonymizer->mask('Phone 9876543210');
        $this->pseudonymizer->mask('Phone 8765432109');

        $this->pseudonymizer->reset();

        $masked = $this->pseudonymizer->mask('Phone 7654321098');
        expect($masked)->toBe('Phone [PHONE_1]');
    });
});
