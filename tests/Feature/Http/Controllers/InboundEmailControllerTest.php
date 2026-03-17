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

        $img1 = UploadedFile::fake()->image('invoice_scan1.png', 100, 100);
        $img2 = UploadedFile::fake()->image('invoice_scan2.png', 200, 200);

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

        $png = UploadedFile::fake()->image('invoice_receipt.png');

        $response = $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1']),
            ['attachment-1' => $png],
        ));

        $response->assertSuccessful()
            ->assertJson(['files_processed' => 1]);
    });

    it('accepts JPEG attachments', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $jpeg = UploadedFile::fake()->image('bill_scan.jpg');

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

describe('InboundEmailController email-based classification', function () {
    it('classifies as CreditCard when email subject contains credit card', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('Credit Card Statement.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload([
                'attachment-count' => '1',
                'subject' => 'Your AXIS BANK Credit Card Statement for Feb 2026',
            ]),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        expect($importedFile->statement_type)->toBe(StatementType::CreditCard)
            ->and($importedFile->status)->toBe(ImportStatus::Pending);
    });

    it('classifies as Bank when email subject contains bank statement', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('statement89898.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload([
                'attachment-count' => '1',
                'subject' => 'Your HDFC Bank Account Statement',
            ]),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        expect($importedFile->statement_type)->toBe(StatementType::Bank)
            ->and($importedFile->status)->toBe(ImportStatus::Pending);
    });

    it('classifies as Invoice when email subject contains invoice', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload([
                'attachment-count' => '1',
                'subject' => 'Invoice #1234 from Vendor Corp',
            ]),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        expect($importedFile->statement_type)->toBe(StatementType::Invoice)
            ->and($importedFile->status)->toBe(ImportStatus::Pending);
    });

    it('classifies using email body when subject has no keywords', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload([
                'attachment-count' => '1',
                'subject' => 'Fwd: Monthly documents',
                'stripped-text' => 'Please find your ICICI Credit Card statement attached.',
            ]),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        expect($importedFile->statement_type)->toBe(StatementType::CreditCard);
    });

    it('classifies as CreditCard when forwarded email subject mentions credit card bill', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('statement.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload([
                'attachment-count' => '1',
                'subject' => 'Fwd: Your Credit Card Bill',
            ]),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        expect($importedFile->statement_type)->toBe(StatementType::CreditCard);
    });

    it('classifies as CreditCard when email body contains credit card and bill', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload([
                'attachment-count' => '1',
                'subject' => 'Monthly Documents',
                'stripped-text' => 'Please find your credit card bill attached for review.',
            ]),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        expect($importedFile->statement_type)->toBe(StatementType::CreditCard);
    });

    it('falls back to filename when email has no classification signals', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('bank_statement_jan.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload([
                'attachment-count' => '1',
                'subject' => 'Fwd: Documents',
            ]),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        expect($importedFile->statement_type)->toBe(StatementType::Bank);
    });

    it('classifies filenames with trailing numbers correctly', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('bank_statement1920090.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload([
                'attachment-count' => '1',
                'subject' => 'Fwd: Docs',
            ]),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        expect($importedFile->statement_type)->toBe(StatementType::Bank);
    });

    it('classifies credit card filenames with trailing numbers correctly', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('credit_card_statement89898.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload([
                'attachment-count' => '1',
                'subject' => 'Fwd: Docs',
            ]),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        expect($importedFile->statement_type)->toBe(StatementType::CreditCard);
    });
});

describe('InboundEmailController filename classification', function () {
    it('classifies invoice filenames as Invoice type', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1', 'subject' => 'Fwd:']),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        expect($importedFile->statement_type)->toBe(StatementType::Invoice)
            ->and($importedFile->status)->toBe(ImportStatus::Pending);
    });

    it('classifies statement filenames as Bank type', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('bank_statement_jan.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1', 'subject' => 'Fwd:']),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        expect($importedFile->statement_type)->toBe(StatementType::Bank)
            ->and($importedFile->status)->toBe(ImportStatus::Pending);
    });

    it('imports PDFs with unrecognized filenames as Pending rather than Skipped', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('monthly_report.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1', 'subject' => 'Fwd:']),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        expect($importedFile->status)->toBe(ImportStatus::Pending);
    });

    it('recognizes bill filenames as Invoice type', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('electricity_bill_feb.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1', 'subject' => 'Fwd:']),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        expect($importedFile->statement_type)->toBe(StatementType::Invoice);
    });

    it('recognizes tax invoice filenames as Invoice type', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('tax_invoice_march.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1', 'subject' => 'Fwd:']),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        expect($importedFile->statement_type)->toBe(StatementType::Invoice);
    });

    it('recognizes debit note filenames as Invoice type', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('debit_note_001.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1', 'subject' => 'Fwd:']),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        expect($importedFile->statement_type)->toBe(StatementType::Invoice);
    });

    it('is case-insensitive for classification', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('TAX_INVOICE_2026.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1', 'subject' => 'Fwd:']),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        expect($importedFile->statement_type)->toBe(StatementType::Invoice);
    });

    it('dispatches ProcessImportedFile for PDFs with unrecognized filenames', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('balance_sheet.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1', 'subject' => 'Fwd:']),
            ['attachment-1' => $pdf],
        ));

        Queue::assertPushed(ProcessImportedFile::class);

        $importedFile = ImportedFile::first();
        expect($importedFile->status)->toBe(ImportStatus::Pending);
    });

    it('counts skipped files in files_processed', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('summary_report.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1', 'subject' => 'Fwd:']),
            ['attachment-1' => $pdf],
        ));

        $response->assertSuccessful()
            ->assertJson(['files_processed' => 1]);
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

    it('stores message_id as a column on ImportedFile', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1']),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        expect($importedFile->message_id)->toBe('<unique-msg-id-123@mail.example.com>');
    });

    it('stores null message_id when Message-Id header is absent', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload([
                'attachment-count' => '1',
                'Message-Id' => null,
            ]),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        expect($importedFile->message_id)->toBeNull();
    });
});

describe('InboundEmailController email body capture', function () {
    it('captures stripped-text as body_text in source metadata', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload([
                'attachment-count' => '1',
                'stripped-text' => 'Password: First 4 letters of PAN + DOB in DDMMYYYY',
            ]),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        $metadata = $importedFile->source_metadata;

        expect($metadata['body_text'])->toBe('Password: First 4 letters of PAN + DOB in DDMMYYYY');
    });

    it('falls back to body-plain when stripped-text is absent', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload([
                'attachment-count' => '1',
                'body-plain' => 'Please find attached statement. Password is your DOB.',
            ]),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        $metadata = $importedFile->source_metadata;

        expect($metadata['body_text'])->toBe('Please find attached statement. Password is your DOB.');
    });

    it('stores null body_text when no body fields are present', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1']),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        $metadata = $importedFile->source_metadata;

        expect($metadata['body_text'])->toBeNull();
    });

    it('truncates body_text to 2000 characters', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');
        $longBody = str_repeat('A', 3000);

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload([
                'attachment-count' => '1',
                'stripped-text' => $longBody,
            ]),
            ['attachment-1' => $pdf],
        ));

        $importedFile = ImportedFile::first();
        $metadata = $importedFile->source_metadata;

        expect(strlen($metadata['body_text']))->toBe(2000);
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
    it('dispatches ProcessImportedFile job for each classified attachment', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $img1 = UploadedFile::fake()->image('invoice_scan1.png', 100, 100);
        $img2 = UploadedFile::fake()->image('invoice_scan2.png', 200, 200);

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

describe('InboundEmailController non-standard filenames', function () {
    it('imports a PDF with a generic scanned filename as Pending', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('scan001.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1', 'subject' => 'Fwd: please see attached']),
            ['attachment-1' => $pdf],
        ));

        $response->assertSuccessful()->assertJson(['files_processed' => 1]);

        $importedFile = ImportedFile::first();
        expect($importedFile)->not->toBeNull()
            ->and($importedFile->status)->toBe(ImportStatus::Pending)
            ->and($importedFile->original_filename)->toBe('scan001.pdf');
    });

    it('imports a PDF named document.pdf as Pending', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1', 'subject' => 'Fwd: docs']),
            ['attachment-1' => $pdf],
        ));

        $response->assertSuccessful()->assertJson(['files_processed' => 1]);

        expect(ImportedFile::first()->status)->toBe(ImportStatus::Pending);
    });

    it('dispatches ProcessImportedFile for a PDF with a generic filename', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdf = UploadedFile::fake()->create('scan001.pdf', 100, 'application/pdf');

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1', 'subject' => 'Fwd: please see attached']),
            ['attachment-1' => $pdf],
        ));

        Queue::assertPushed(ProcessImportedFile::class);
    });

    it('imports a PNG image with a generic filename as Pending', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $img = UploadedFile::fake()->image('photo001.png');

        $response = $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '1', 'subject' => 'Fwd: please see attached']),
            ['attachment-1' => $img],
        ));

        $response->assertSuccessful()->assertJson(['files_processed' => 1]);

        expect(ImportedFile::first()->status)->toBe(ImportStatus::Pending);
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

describe('InboundEmailController multi-tenant isolation', function () {
    it('allows the same file to be uploaded by different companies', function () {
        $companyA = Company::factory()->create(['inbox_address' => 'a@inbox.example.com']);
        $companyB = Company::factory()->create(['inbox_address' => 'b@inbox.example.com']);

        $pdfContent = 'identical-pdf-content-for-both';
        $pdfA = UploadedFile::fake()->createWithContent('statement.pdf', $pdfContent);
        $pdfB = UploadedFile::fake()->createWithContent('statement.pdf', $pdfContent);

        $responseA = $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload([
                'recipient' => 'a@inbox.example.com',
                'attachment-count' => '1',
                'Message-Id' => '<msg-a@example.com>',
            ]),
            ['attachment-1' => $pdfA],
        ));

        $responseB = $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload([
                'recipient' => 'b@inbox.example.com',
                'attachment-count' => '1',
                'Message-Id' => '<msg-b@example.com>',
            ]),
            ['attachment-1' => $pdfB],
        ));

        $responseA->assertSuccessful()->assertJson(['files_processed' => 1]);
        $responseB->assertSuccessful()->assertJson(['files_processed' => 1]);

        expect(ImportedFile::where('company_id', $companyA->id)->count())->toBe(1)
            ->and(ImportedFile::where('company_id', $companyB->id)->count())->toBe(1);
    });

    it('blocks duplicate file_hash within the same company', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $pdfContent = 'identical-pdf-content';
        $pdf1 = UploadedFile::fake()->createWithContent('statement.pdf', $pdfContent);
        $pdf2 = UploadedFile::fake()->createWithContent('statement_copy.pdf', $pdfContent);

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload([
                'attachment-count' => '1',
                'Message-Id' => '<first@example.com>',
            ]),
            ['attachment-1' => $pdf1],
        ));

        $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload([
                'attachment-count' => '1',
                'Message-Id' => '<second@example.com>',
            ]),
            ['attachment-1' => $pdf2],
        ));

        expect(ImportedFile::count())->toBe(1);
    });

    it('allows multiple attachments from the same email', function () {
        Company::factory()->create(['inbox_address' => 'invoices@inbox.example.com']);

        $img1 = UploadedFile::fake()->image('invoice_scan1.png', 100, 100);
        $img2 = UploadedFile::fake()->image('invoice_scan2.png', 200, 200);

        $response = $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload(['attachment-count' => '2']),
            ['attachment-1' => $img1, 'attachment-2' => $img2],
        ));

        $response->assertSuccessful()->assertJson(['files_processed' => 2]);

        $messageIds = ImportedFile::pluck('message_id')->all();
        expect($messageIds)->each->toBe('<unique-msg-id-123@mail.example.com>');
    });

    it('scopes message_id deduplication to the receiving company', function () {
        $companyA = Company::factory()->create(['inbox_address' => 'a@inbox.example.com']);
        Company::factory()->create(['inbox_address' => 'b@inbox.example.com']);

        ImportedFile::factory()->for($companyA)->fromEmail('<shared-msg@example.com>')->create();

        $pdf = UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf');

        $response = $this->postJson('/api/v1/webhooks/inbound-email', array_merge(
            inboundPayload([
                'recipient' => 'b@inbox.example.com',
                'Message-Id' => '<shared-msg@example.com>',
                'attachment-count' => '1',
            ]),
            ['attachment-1' => $pdf],
        ));

        $response->assertSuccessful()->assertJson(['files_processed' => 1]);
    });
});
