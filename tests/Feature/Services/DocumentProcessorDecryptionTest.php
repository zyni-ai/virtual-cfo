<?php

use App\Ai\Agents\StatementParser;
use App\Enums\ImportSource;
use App\Enums\ImportStatus;
use App\Enums\StatementType;
use App\Jobs\ProcessImportedFile;
use App\Models\BankAccount;
use App\Models\CreditCard;
use App\Models\ImportedFile;
use App\Services\DocumentProcessor\DocumentProcessor;
use App\Services\DocumentProcessor\PdfDecryptionService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

/**
 * Create a mock PdfDecryptionService and bind it into the container.
 *
 * @return Mockery\MockInterface&PdfDecryptionService
 */
function mockDecryptionService(bool $qpdfAvailable = true, bool $passwordProtected = false): Mockery\MockInterface
{
    $mock = Mockery::mock(PdfDecryptionService::class);
    $mock->shouldReceive('isPasswordProtected')->andReturn($passwordProtected);
    $mock->shouldReceive('isQpdfAvailable')->andReturn($qpdfAvailable);

    app()->instance(PdfDecryptionService::class, $mock);

    return $mock;
}

/**
 * Fake the StatementParser with a single bank transaction response.
 */
function fakeStatementParser(string $bankName = 'HDFC Bank', string $description = 'SALARY', int $credit = 50000): void
{
    StatementParser::fake([
        [
            'bank_name' => $bankName,
            'transactions' => [
                ['date' => '2024-01-05', 'description' => $description, 'credit' => $credit, 'balance' => 150000],
            ],
        ],
    ]);
}

describe('DocumentProcessor PDF decryption', function () {
    it('passes unprotected PDFs through without decryption', function () {
        Storage::put('statements/bank.pdf', 'fake-pdf-content');

        $mock = mockDecryptionService(passwordProtected: false);
        $mock->shouldNotReceive('decrypt');

        fakeStatementParser();

        $file = ImportedFile::factory()->create([
            'file_path' => 'statements/bank.pdf',
            'original_filename' => 'bank_statement.pdf',
            'statement_type' => StatementType::Bank,
            'status' => ImportStatus::Pending,
        ]);

        app(DocumentProcessor::class)->process($file);

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::Completed);
    });

    it('decrypts protected PDF using manual password from source_metadata', function () {
        Storage::put('statements/bank.pdf', 'encrypted-pdf-content');
        Storage::put('statements/bank_decrypted.pdf', 'decrypted-pdf-content');

        $mock = mockDecryptionService(passwordProtected: true);
        $mock->shouldReceive('decrypt')
            ->with('statements/bank.pdf', 'manual123')
            ->andReturn('statements/bank_decrypted.pdf');

        fakeStatementParser();

        $file = ImportedFile::factory()->create([
            'file_path' => 'statements/bank.pdf',
            'original_filename' => 'bank_statement.pdf',
            'statement_type' => StatementType::Bank,
            'status' => ImportStatus::Pending,
            'source' => ImportSource::ManualUpload,
            'source_metadata' => ['manual_password' => 'manual123'],
        ]);

        app(DocumentProcessor::class)->process($file);

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::Completed);
    });

    it('decrypts protected PDF using stored BankAccount password', function () {
        Storage::put('statements/bank.pdf', 'encrypted-pdf-content');
        Storage::put('statements/bank_decrypted.pdf', 'decrypted-pdf-content');

        $mock = mockDecryptionService(passwordProtected: true);
        $mock->shouldReceive('decrypt')
            ->with('statements/bank.pdf', 'bankpass123')
            ->andReturn('statements/bank_decrypted.pdf');

        fakeStatementParser();

        $bankAccount = BankAccount::factory()->withPassword('bankpass123')->create();

        $file = ImportedFile::factory()->create([
            'company_id' => $bankAccount->company_id,
            'bank_account_id' => $bankAccount->id,
            'file_path' => 'statements/bank.pdf',
            'original_filename' => 'bank_statement.pdf',
            'statement_type' => StatementType::Bank,
            'status' => ImportStatus::Pending,
        ]);

        app(DocumentProcessor::class)->process($file);

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::Completed);
    });

    it('decrypts protected PDF using stored CreditCard password', function () {
        Storage::put('statements/cc.pdf', 'encrypted-pdf-content');
        Storage::put('statements/cc_decrypted.pdf', 'decrypted-pdf-content');

        $mock = mockDecryptionService(passwordProtected: true);
        $mock->shouldReceive('decrypt')
            ->with('statements/cc.pdf', 'ccpass456')
            ->andReturn('statements/cc_decrypted.pdf');

        fakeStatementParser('HDFC Credit Card', 'AMAZON');

        $creditCard = CreditCard::factory()->withPassword('ccpass456')->create();

        $file = ImportedFile::factory()->create([
            'company_id' => $creditCard->company_id,
            'credit_card_id' => $creditCard->id,
            'file_path' => 'statements/cc.pdf',
            'original_filename' => 'cc_statement.pdf',
            'statement_type' => StatementType::CreditCard,
            'status' => ImportStatus::Pending,
        ]);

        app(DocumentProcessor::class)->process($file);

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::Completed);
    });

    it('matches bank account from email context and uses stored password', function () {
        Storage::put('statements/bank.pdf', 'encrypted-pdf-content');
        Storage::put('statements/bank_decrypted.pdf', 'decrypted-pdf-content');

        $mock = mockDecryptionService(passwordProtected: true);
        $mock->shouldReceive('decrypt')
            ->with('statements/bank.pdf', 'hdfc_pass')
            ->andReturn('statements/bank_decrypted.pdf');

        fakeStatementParser();

        $bankAccount = BankAccount::factory()->withPassword('hdfc_pass')->create([
            'name' => 'HDFC',
        ]);

        $file = ImportedFile::factory()->create([
            'company_id' => $bankAccount->company_id,
            'file_path' => 'statements/bank.pdf',
            'original_filename' => 'bank_statement.pdf',
            'statement_type' => StatementType::Bank,
            'status' => ImportStatus::Pending,
            'source' => ImportSource::Email,
            'source_metadata' => [
                'subject' => 'Your HDFC Bank Statement',
                'body_text' => 'Please find your HDFC bank statement attached.',
            ],
        ]);

        app(DocumentProcessor::class)->process($file);

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::Completed)
            ->and($file->bank_account_id)->toBe($bankAccount->id);
    });

    it('matches credit card from email context and uses stored password', function () {
        Storage::put('statements/cc.pdf', 'encrypted-pdf-content');
        Storage::put('statements/cc_decrypted.pdf', 'decrypted-pdf-content');

        $mock = mockDecryptionService(passwordProtected: true);
        $mock->shouldReceive('decrypt')
            ->with('statements/cc.pdf', 'icici_cc_pass')
            ->andReturn('statements/cc_decrypted.pdf');

        fakeStatementParser('ICICI Credit Card', 'FLIPKART');

        $creditCard = CreditCard::factory()->withPassword('icici_cc_pass')->create([
            'name' => 'ICICI Credit Card',
        ]);

        $file = ImportedFile::factory()->create([
            'company_id' => $creditCard->company_id,
            'file_path' => 'statements/cc.pdf',
            'original_filename' => 'cc_statement.pdf',
            'statement_type' => StatementType::CreditCard,
            'status' => ImportStatus::Pending,
            'source' => ImportSource::Email,
            'source_metadata' => [
                'subject' => 'ICICI Credit Card Statement',
                'body_text' => 'Your ICICI Credit Card statement is ready.',
            ],
        ]);

        app(DocumentProcessor::class)->process($file);

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::Completed)
            ->and($file->credit_card_id)->toBe($creditCard->id);
    });

    it('sets NeedsPassword status when all passwords fail', function () {
        Storage::put('statements/bank.pdf', 'encrypted-pdf-content');

        $mock = mockDecryptionService(passwordProtected: true);
        $mock->shouldReceive('decrypt')
            ->andThrow(new RuntimeException('Failed to decrypt PDF: wrong password'));

        $file = ImportedFile::factory()->create([
            'file_path' => 'statements/bank.pdf',
            'original_filename' => 'bank_statement.pdf',
            'statement_type' => StatementType::Bank,
            'status' => ImportStatus::Pending,
        ]);

        app(DocumentProcessor::class)->process($file);

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::NeedsPassword)
            ->and($file->error_message)->toContain('password-protected');
    });

    it('sets NeedsPassword when no stored password exists for linked account', function () {
        Storage::put('statements/bank.pdf', 'encrypted-pdf-content');

        $mock = mockDecryptionService(passwordProtected: true);
        $mock->shouldNotReceive('decrypt');

        $bankAccount = BankAccount::factory()->create();

        $file = ImportedFile::factory()->create([
            'company_id' => $bankAccount->company_id,
            'bank_account_id' => $bankAccount->id,
            'file_path' => 'statements/bank.pdf',
            'original_filename' => 'bank_statement.pdf',
            'statement_type' => StatementType::Bank,
            'status' => ImportStatus::Pending,
        ]);

        app(DocumentProcessor::class)->process($file);

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::NeedsPassword);
    });

    it('sets NeedsPassword when qpdf is not available for encrypted PDF', function () {
        Storage::put('statements/bank.pdf', 'encrypted-pdf-content');

        mockDecryptionService(qpdfAvailable: false, passwordProtected: true);

        $file = ImportedFile::factory()->create([
            'file_path' => 'statements/bank.pdf',
            'original_filename' => 'bank_statement.pdf',
            'statement_type' => StatementType::Bank,
            'status' => ImportStatus::Pending,
        ]);

        app(DocumentProcessor::class)->process($file);

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::NeedsPassword)
            ->and($file->error_message)->toContain('qpdf');
    });

    it('prefers manual password over stored password', function () {
        Storage::put('statements/bank.pdf', 'encrypted-pdf-content');
        Storage::put('statements/bank_decrypted.pdf', 'decrypted-pdf-content');

        $mock = mockDecryptionService(passwordProtected: true);
        $mock->shouldReceive('decrypt')
            ->with('statements/bank.pdf', 'manual_override')
            ->once()
            ->andReturn('statements/bank_decrypted.pdf');

        fakeStatementParser();

        $bankAccount = BankAccount::factory()->withPassword('stored_pass')->create();

        $file = ImportedFile::factory()->create([
            'company_id' => $bankAccount->company_id,
            'bank_account_id' => $bankAccount->id,
            'file_path' => 'statements/bank.pdf',
            'original_filename' => 'bank_statement.pdf',
            'statement_type' => StatementType::Bank,
            'status' => ImportStatus::Pending,
            'source' => ImportSource::ManualUpload,
            'source_metadata' => ['manual_password' => 'manual_override'],
        ]);

        app(DocumentProcessor::class)->process($file);

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::Completed);
    });

    it('dispatches reprocess when bank account pdf_password is set', function () {
        Queue::fake();

        $bankAccount = BankAccount::factory()->create();

        $needsPasswordFile = ImportedFile::factory()->create([
            'company_id' => $bankAccount->company_id,
            'bank_account_id' => $bankAccount->id,
            'status' => ImportStatus::NeedsPassword,
        ]);

        $completedFile = ImportedFile::factory()->create([
            'company_id' => $bankAccount->company_id,
            'bank_account_id' => $bankAccount->id,
            'status' => ImportStatus::Completed,
        ]);

        $bankAccount->update(['pdf_password' => 'newpass']);

        Queue::assertPushed(ProcessImportedFile::class, fn ($job) => $job->importedFile->id === $needsPasswordFile->id);
        Queue::assertPushed(ProcessImportedFile::class, 1);
    });

    it('dispatches reprocess when credit card pdf_password is set', function () {
        Queue::fake();

        $creditCard = CreditCard::factory()->create();

        $needsPasswordFile = ImportedFile::factory()->create([
            'company_id' => $creditCard->company_id,
            'credit_card_id' => $creditCard->id,
            'status' => ImportStatus::NeedsPassword,
        ]);

        $creditCard->update(['pdf_password' => 'newccpass']);

        Queue::assertPushed(ProcessImportedFile::class, fn ($job) => $job->importedFile->id === $needsPasswordFile->id);
        Queue::assertPushed(ProcessImportedFile::class, 1);
    });

    it('does not dispatch reprocess when pdf_password is cleared', function () {
        Queue::fake();

        $bankAccount = BankAccount::factory()->withPassword('oldpass')->create();

        ImportedFile::factory()->create([
            'company_id' => $bankAccount->company_id,
            'bank_account_id' => $bankAccount->id,
            'status' => ImportStatus::NeedsPassword,
        ]);

        $bankAccount->update(['pdf_password' => null]);

        Queue::assertNotPushed(ProcessImportedFile::class);
    });

    it('auto-matches credit card when statement type is credit_card', function () {
        Storage::put('statements/cc.pdf', 'fake-pdf-content');

        mockDecryptionService(passwordProtected: false);

        $creditCard = CreditCard::factory()->create(['name' => 'HDFC Credit Card']);

        fakeStatementParser('HDFC Credit Card', 'AMAZON');

        $file = ImportedFile::factory()->create([
            'company_id' => $creditCard->company_id,
            'file_path' => 'statements/cc.pdf',
            'original_filename' => 'cc_statement.pdf',
            'statement_type' => StatementType::CreditCard,
            'status' => ImportStatus::Pending,
        ]);

        app(DocumentProcessor::class)->process($file);

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::Completed)
            ->and($file->credit_card_id)->toBe($creditCard->id);
    });

    it('auto-creates credit card when no matching one exists', function () {
        Storage::put('statements/cc.pdf', 'fake-pdf-content');

        mockDecryptionService(passwordProtected: false);

        fakeStatementParser('SBI Credit Card', 'AMAZON');

        $file = ImportedFile::factory()->create([
            'file_path' => 'statements/cc.pdf',
            'original_filename' => 'cc_statement.pdf',
            'statement_type' => StatementType::CreditCard,
            'status' => ImportStatus::Pending,
        ]);

        app(DocumentProcessor::class)->process($file);

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::Completed)
            ->and($file->credit_card_id)->not->toBeNull();

        $creditCard = CreditCard::find($file->credit_card_id);
        expect($creditCard->name)->toBe('SBI Credit Card')
            ->and($creditCard->company_id)->toBe($file->company_id);
    });
});
