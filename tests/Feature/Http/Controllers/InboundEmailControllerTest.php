<?php

use App\Enums\ImportSource;
use App\Enums\ImportStatus;
use App\Enums\StatementType;
use App\Http\Middleware\VerifyMailgunSignature;
use App\Jobs\ProcessImportedFile;
use App\Models\Company;
use App\Models\ImportedFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    $this->withoutMiddleware(VerifyMailgunSignature::class);
});

/**
 * Build a Mailgun webhook request payload with optional overrides.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function inboundPayload(array $overrides = []): array
{
    return array_merge([
        'recipient' => 'invoices@inbox.example.com',
        'sender' => 'vendor@example.com',
        'from' => 'Vendor Inc <vendor@example.com>',
        'subject' => 'Invoice for January 2026',
        'Message-Id' => '<unique-msg-id-123@mail.example.com>',
        'attachment-count' => '0',
    ], $overrides);
}

describe('InboundEmailController tenant resolution', function () {
    it('resolves a company by inbox_address', function () {
        $company = Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $response = $this->postJson('/api/v1/webhooks/inbound-email', inboundPayload());

        $response->assertSuccessful()
            ->assertJson(['status' => 'ok']);
    });

    it('returns 404 for an unknown inbox_address', function () {
        $response = $this->postJson('/api/v1/webhooks/inbound-email', inboundPayload([
            'recipient' => 'unknown@inbox.example.com',
        ]));

        $response->assertNotFound()
            ->assertJson(['error' => 'Unknown recipient']);
    });
});

describe('InboundEmailController attachment processing', function () {
    it('creates ImportedFile records for PDF attachments', function () {
        $company = Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1']),
            ['attachment-1' => $pdf],
        ));

        $response->assertSuccessful()
            ->assertJson(['status' => 'ok', 'files_processed' => 1]);

        $importedFile = ImportedFile::first();
        expect($importedFile)->not->toBeNull()
            ->and($importedFile->company_id)->toBe($company->id)
            ->and($importedFile->original_filename)->toBe('invoice.pdf')
            ->and($importedFile->source)->toBe(ImportSource::Email)
            ->and($importedFile->status)->toBe(ImportStatus::Pending);
    });

    it('creates ImportedFile records for multiple attachments', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $img1 = UploadedFile::fake()->image('receipt1.png', 100, 100);
        $img2 = UploadedFile::fake()->image('receipt2.png', 200, 200);

        $response = $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '2']),
            ['attachment-1' => $img1, 'attachment-2' => $img2],
        ));

        $response->assertSuccessful()
            ->assertJson(['files_processed' => 2]);

        expect(ImportedFile::count())->toBe(2);
    });

    it('accepts PNG attachments', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $png = UploadedFile::fake()->image('receipt.png');

        $response = $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1']),
            ['attachment-1' => $png],
        ));

        $response->assertSuccessful()
            ->assertJson(['files_processed' => 1]);
    });

    it('accepts JPEG attachments', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $jpeg = UploadedFile::fake()->image('receipt.jpg');

        $response = $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1']),
            ['attachment-1' => $jpeg],
        ));

        $response->assertSuccessful()
            ->assertJson(['files_processed' => 1]);
    });

    it('ignores non-PDF/image attachments', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $zip = UploadedFile::fake()->create('archive.zip', 100, 'application/zip');

        $response = $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1']),
            ['attachment-1' => $zip],
        ));

        $response->assertSuccessful()
            ->assertJson(['files_processed' => 0]);

        expect(ImportedFile::count())->toBe(0);
    });

    it('returns files_processed 0 when there are no attachments', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $response = $this->postJson('/api/v1/webhooks/inbound-email', inboundPayload());

        $response->assertSuccessful()
            ->assertJson(['files_processed' => 0]);
    });

    it('stores files to the local disk', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1']),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        Storage::disk('local')->assertExists($importedFile->file_path);
    });
});

describe('InboundEmailController source metadata', function () {
    it('stores email metadata on the ImportedFile', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1']),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        $metadata = $importedFile->source_metadata;

        expect($metadata)->toBeArray()
            ->and($metadata['message_id'])->toBe('<unique-msg-id-123@mail.example.com>')
            ->and($metadata['from'])->toBe('Vendor Inc <vendor@example.com>')
            ->and($metadata['subject'])->toBe('Invoice for January 2026')
            ->and($metadata['received_at'])->not->toBeNull();
    });

    it('defaults statement_type to Invoice for email source', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1']),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        expect($importedFile->statement_type)->toBe(StatementType::Invoice);
    });
});

describe('InboundEmailController deduplication', function () {
    it('skips processing when message_id already exists', function () {
        $company = Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        ImportedFile::factory()->for($company)->fromEmail('<dup-msg@example.com>')->create();

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload([
                'Message-Id' => '<dup-msg@example.com>',
                'attachment-count' => '1',
            ]),
            ['attachment-1' => $pdf],
        ));

        $response->assertSuccessful()
            ->assertJson(['files_processed' => 0]);

        expect(ImportedFile::count())->toBe(1);
    });

    it('allows processing when message_id is new', function () {
        $company = Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        ImportedFile::factory()->for($company)->fromEmail('<old-msg@example.com>')->create();

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload([
                'Message-Id' => '<new-msg@example.com>',
                'attachment-count' => '1',
            ]),
            ['attachment-1' => $pdf],
        ));

        $response->assertSuccessful()
            ->assertJson(['files_processed' => 1]);

        expect(ImportedFile::count())->toBe(2);
    });
});

describe('InboundEmailController job dispatch', function () {
    it('dispatches ProcessImportedFile job for each attachment', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $img1 = UploadedFile::fake()->image('receipt1.png', 100, 100);
        $img2 = UploadedFile::fake()->image('receipt2.png', 200, 200);

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '2']),
            ['attachment-1' => $img1, 'attachment-2' => $img2],
        ));

        Queue::assertPushed(ProcessImportedFile::class, 2);
    });

    it('does not dispatch jobs when there are no valid attachments', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $this->postJson('/api/v1/webhooks/inbound-email', inboundPayload());

        Queue::assertNotPushed(ProcessImportedFile::class);
    });

    it('dispatches jobs only for valid attachment types', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');
        $zip = UploadedFile::fake()->create('archive.zip', 100, 'application/zip');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '2']),
            ['attachment-1' => $pdf, 'attachment-2' => $zip],
        ));

        Queue::assertPushed(ProcessImportedFile::class, 1);
    });
});

describe('InboundEmailController file hash', function () {
    it('generates a sha256 hash for stored files', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1']),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        expect($importedFile->file_hash)->not->toBeNull()
            ->and(strlen($importedFile->file_hash))->toBe(64);
    });
});
