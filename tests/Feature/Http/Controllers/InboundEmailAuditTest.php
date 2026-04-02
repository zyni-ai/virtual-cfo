<?php

use App\Enums\InboundEmailStatus;
use App\Http\Middleware\VerifyMailgunSignature;
use App\Models\Company;
use App\Models\ImportedFile;
use App\Models\InboundEmail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Queue::fake();
    $this->withoutMiddleware(VerifyMailgunSignature::class);
});

describe('InboundEmail audit trail — record creation', function () {
    it('creates an InboundEmail record for every incoming request', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $this->postJson('/api/v1/webhooks/inbound-email', inboundPayload());

        expect(InboundEmail::count())->toBe(1);
    });

    it('creates an InboundEmail record even for unknown inbox addresses', function () {
        $this->postJson('/api/v1/webhooks/inbound-email', inboundPayload([
            'recipient' => 'unknown@inbox.example.com',
        ]));

        expect(InboundEmail::count())->toBe(1)
            ->and(InboundEmail::first()->company_id)->toBeNull();
    });

    it('stores recipient on InboundEmail', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $this->postJson('/api/v1/webhooks/inbound-email', inboundPayload([
            'recipient' => 'invoices@inbox.example.com',
        ]));

        expect(InboundEmail::first()->recipient)->toBe('invoices@inbox.example.com');
    });

    it('stores from_address, subject, and message_id on InboundEmail', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $this->postJson('/api/v1/webhooks/inbound-email', inboundPayload([
            'from' => 'Vendor Inc <vendor@example.com>',
            'subject' => 'Invoice for January',
            'Message-Id' => '<abc123@mail.example.com>',
        ]));

        $inboundEmail = InboundEmail::first();
        expect($inboundEmail->from_address)->toBe('Vendor Inc <vendor@example.com>')
            ->and($inboundEmail->subject)->toBe('Invoice for January')
            ->and($inboundEmail->message_id)->toBe('<abc123@mail.example.com>');
    });
});

describe('InboundEmail audit trail — status: rejected', function () {
    it('sets status to rejected when inbox address is unknown', function () {
        $this->postJson('/api/v1/webhooks/inbound-email', inboundPayload([
            'recipient' => 'unknown@inbox.example.com',
        ]));

        $inboundEmail = InboundEmail::first();
        expect($inboundEmail->status)->toBe(InboundEmailStatus::Rejected)
            ->and($inboundEmail->rejection_reason)->not->toBeNull();
    });

    it('still returns 404 for unknown inbox address', function () {
        $response = $this->postJson('/api/v1/webhooks/inbound-email', inboundPayload([
            'recipient' => 'unknown@inbox.example.com',
        ]));

        $response->assertNotFound();
    });
});

describe('InboundEmail audit trail — status: duplicate', function () {
    it('sets status to duplicate when message_id was already received', function () {
        $company = Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        InboundEmail::factory()->create([
            'company_id' => $company->id,
            'message_id' => '<unique-msg-id-123@mail.example.com>',
            'status' => InboundEmailStatus::Processed,
        ]);

        $this->postJson('/api/v1/webhooks/inbound-email', inboundPayload([
            'Message-Id' => '<unique-msg-id-123@mail.example.com>',
        ]));

        $duplicate = InboundEmail::where('status', InboundEmailStatus::Duplicate->value)->first();
        expect($duplicate)->not->toBeNull()
            ->and($duplicate->rejection_reason)->not->toBeNull();
    });

    it('still returns ok for duplicate message_id', function () {
        $company = Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        InboundEmail::factory()->create([
            'company_id' => $company->id,
            'message_id' => '<unique-msg-id-123@mail.example.com>',
            'status' => InboundEmailStatus::Processed,
        ]);

        $response = $this->postJson('/api/v1/webhooks/inbound-email', inboundPayload([
            'Message-Id' => '<unique-msg-id-123@mail.example.com>',
        ]));

        $response->assertSuccessful()
            ->assertJson(['status' => 'ok', 'files_processed' => 0]);
    });
});

describe('InboundEmail audit trail — status: no_attachments', function () {
    it('sets status to no_attachments when email has no valid attachments', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $this->postJson('/api/v1/webhooks/inbound-email', inboundPayload([
            'attachment-count' => '0',
        ]));

        expect(InboundEmail::first()->status)->toBe(InboundEmailStatus::NoAttachments);
    });

    it('sets status to no_attachments when all attachments are unsupported types', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $zip = UploadedFile::fake()->create('archive.zip', 100, 'application/zip');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1']),
            ['attachment-1' => $zip],
        ));

        expect(InboundEmail::first()->status)->toBe(InboundEmailStatus::NoAttachments);
    });
});

describe('InboundEmail audit trail — status: processed', function () {
    it('sets status to processed when attachments are successfully imported', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1']),
            ['attachment-1' => $pdf],
        ));

        expect(InboundEmail::first()->status)->toBe(InboundEmailStatus::Processed);
    });

    it('tracks attachment_count, processed_count, and skipped_count', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');
        $zip = UploadedFile::fake()->create('archive.zip', 100, 'application/zip');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '2']),
            ['attachment-1' => $pdf, 'attachment-2' => $zip],
        ));

        $inboundEmail = InboundEmail::first();
        expect($inboundEmail->attachment_count)->toBe(2)
            ->and($inboundEmail->processed_count)->toBe(1)
            ->and($inboundEmail->skipped_count)->toBe(1);
    });
});

describe('InboundEmail audit trail — ImportedFile linkage', function () {
    it('links ImportedFile records to InboundEmail via inbound_email_id', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1']),
            ['attachment-1' => $pdf],
        ));

        $inboundEmail = InboundEmail::first();
        $importedFile = ImportedFile::first();

        expect($importedFile->inbound_email_id)->toBe($inboundEmail->id)
            ->and($inboundEmail->importedFiles()->count())->toBe(1);
    });

    it('links multiple ImportedFile records to the same InboundEmail', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf1 = UploadedFile::fake()->create('invoice1.pdf', 100, 'application/pdf');
        $pdf2 = UploadedFile::fake()->create('invoice2.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '2']),
            ['attachment-1' => $pdf1, 'attachment-2' => $pdf2],
        ));

        $inboundEmail = InboundEmail::first();
        expect($inboundEmail->importedFiles()->count())->toBe(2);
    });

    it('existing ImportedFile records without inbound_email_id continue working', function () {
        $company = Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $existingFile = ImportedFile::factory()
            ->fromEmail()
            ->create(['company_id' => $company->id, 'inbound_email_id' => null]);

        expect($existingFile->refresh()->inbound_email_id)->toBeNull()
            ->and($existingFile->company_id)->toBe($company->id);
    });
});
