<?php

use App\Services\DocumentProcessor\PdfDecryptionService;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->service = new PdfDecryptionService;
});

describe('PdfDecryptionService isQpdfAvailable', function () {
    it('returns true when qpdf is installed', function () {
        Process::fake([
            'qpdf --version' => Process::result(output: 'qpdf version 11.9.0'),
        ]);

        expect($this->service->isQpdfAvailable())->toBeTrue();
    });

    it('returns false when qpdf is not installed', function () {
        Process::fake([
            'qpdf --version' => Process::result(exitCode: 1, errorOutput: 'command not found'),
        ]);

        expect($this->service->isQpdfAvailable())->toBeFalse();
    });
});

describe('PdfDecryptionService isPasswordProtected', function () {
    it('returns true when PDF contains /Encrypt marker', function () {
        Storage::put('statements/test.pdf', '%PDF-1.4 some content /Encrypt some more content');

        expect($this->service->isPasswordProtected('statements/test.pdf'))->toBeTrue();
    });

    it('returns false when PDF does not contain /Encrypt marker', function () {
        Storage::put('statements/test.pdf', '%PDF-1.4 normal unencrypted content');

        expect($this->service->isPasswordProtected('statements/test.pdf'))->toBeFalse();
    });

    it('returns false when file does not exist', function () {
        expect($this->service->isPasswordProtected('statements/nonexistent.pdf'))->toBeFalse();
    });
});

describe('PdfDecryptionService decrypt', function () {
    it('returns the decrypted file path on success', function () {
        Storage::put('statements/test.pdf', 'encrypted-pdf-content');

        Process::fake([
            '*qpdf --password=*--decrypt*' => Process::result(exitCode: 0),
        ]);

        $result = $this->service->decrypt('statements/test.pdf', 'secret123');

        expect($result)->toBe('statements/test_decrypted.pdf');

        Process::assertRan(function ($process) {
            return str_contains($process->command, 'secret123')
                && str_contains($process->command, '--decrypt');
        });
    });

    it('succeeds with warnings when qpdf returns exit code 3', function () {
        Storage::put('statements/test.pdf', 'encrypted-pdf-content');

        Process::fake([
            '*qpdf --password=*--decrypt*' => Process::result(
                exitCode: 3,
                errorOutput: '/Perms field in encryption dictionary doesn\'t match expected value',
            ),
        ]);

        $result = $this->service->decrypt('statements/test.pdf', 'secret123');

        expect($result)->toBe('statements/test_decrypted.pdf');
    });

    it('throws RuntimeException on wrong password', function () {
        Storage::put('statements/test.pdf', 'encrypted-pdf-content');

        Process::fake([
            '*qpdf --password=*--decrypt*' => Process::result(
                exitCode: 2,
                errorOutput: 'invalid password',
            ),
        ]);

        $this->service->decrypt('statements/test.pdf', 'wrong');
    })->throws(RuntimeException::class, 'Failed to decrypt PDF');

    it('preserves original file after decryption', function () {
        Storage::put('statements/test.pdf', 'original-content');

        Process::fake([
            '*qpdf --password=*--decrypt*' => Process::result(exitCode: 0),
        ]);

        $this->service->decrypt('statements/test.pdf', 'secret123');

        Storage::disk('local')->assertExists('statements/test.pdf');
    });
});
