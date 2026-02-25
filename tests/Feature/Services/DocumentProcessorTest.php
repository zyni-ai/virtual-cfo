<?php

use App\Ai\Agents\StatementParser;
use App\Enums\ImportStatus;
use App\Enums\MappingType;
use App\Enums\StatementType;
use App\Models\ImportedFile;
use App\Models\Transaction;
use App\Services\DocumentProcessor\DocumentProcessor;
use Illuminate\Support\Facades\Storage;

describe('DocumentProcessor', function () {
    beforeEach(function () {
        Storage::fake('local');
        $this->processor = new DocumentProcessor;
    });

    describe('detectFormat', function () {
        it('detects PDF format from filename', function () {
            $file = ImportedFile::factory()->create([
                'original_filename' => 'statement.pdf',
            ]);

            expect($this->processor->detectFormat($file))->toBe('pdf');
        });

        it('detects CSV format from filename', function () {
            $file = ImportedFile::factory()->csv()->create();

            expect($this->processor->detectFormat($file))->toBe('csv');
        });

        it('detects XLSX format from filename', function () {
            $file = ImportedFile::factory()->xlsx()->create();

            expect($this->processor->detectFormat($file))->toBe('xlsx');
        });

        it('throws for unsupported file extensions', function () {
            $file = ImportedFile::factory()->create([
                'original_filename' => 'document.docx',
            ]);

            $this->processor->detectFormat($file);
        })->throws(\RuntimeException::class, 'Unsupported file extension: .docx');

        it('is case-insensitive for extensions', function () {
            $file = ImportedFile::factory()->create([
                'original_filename' => 'STATEMENT.PDF',
            ]);

            expect($this->processor->detectFormat($file))->toBe('pdf');
        });
    });

    describe('CSV parsing', function () {
        it('parses a CSV file and creates transactions', function () {
            $csvContent = "Date,Description,Debit,Credit,Balance\n";
            $csvContent .= "2024-01-05,SALARY JAN 2024,,50000,150000\n";
            $csvContent .= "2024-01-10,RENT PAYMENT,15000,,135000\n";
            $csvContent .= "2024-01-15,EMI HDFC,8500,,126500\n";

            Storage::put('statements/test.csv', $csvContent);

            $file = ImportedFile::factory()->csv()->create([
                'file_path' => 'statements/test.csv',
                'original_filename' => 'HDFC_statement.csv',
                'status' => ImportStatus::Pending,
            ]);

            $this->processor->process($file);

            $file->refresh();
            expect($file->status)->toBe(ImportStatus::Completed)
                ->and($file->total_rows)->toBe(3)
                ->and($file->mapped_rows)->toBe(0)
                ->and($file->processed_at)->not->toBeNull();

            $transactions = Transaction::where('imported_file_id', $file->id)->get();
            expect($transactions)->toHaveCount(3);

            $first = $transactions->first();
            expect($first->description)->toBe('SALARY JAN 2024')
                ->and($first->mapping_type)->toBe(MappingType::Unmapped);
        });

        it('handles CSV with alternative column names', function () {
            $csvContent = "Txn Date,Narration,Withdrawal,Deposit,Closing Balance\n";
            $csvContent .= "2024-02-01,UPI PAYMENT,500,,9500\n";

            Storage::put('statements/alt.csv', $csvContent);

            $file = ImportedFile::factory()->csv()->create([
                'file_path' => 'statements/alt.csv',
                'original_filename' => 'bank_export.csv',
                'status' => ImportStatus::Pending,
            ]);

            $this->processor->process($file);

            $file->refresh();
            expect($file->status)->toBe(ImportStatus::Completed)
                ->and($file->total_rows)->toBe(1);
        });

        it('marks file as failed when CSV has no data rows', function () {
            $csvContent = "Date,Description,Debit,Credit,Balance\n";

            Storage::put('statements/empty.csv', $csvContent);

            $file = ImportedFile::factory()->csv()->create([
                'file_path' => 'statements/empty.csv',
                'original_filename' => 'empty.csv',
                'status' => ImportStatus::Pending,
            ]);

            $this->processor->process($file);

            $file->refresh();
            expect($file->status)->toBe(ImportStatus::Failed)
                ->and($file->error_message)->toContain('No data rows found');
        });

        it('skips completely empty rows in CSV', function () {
            $csvContent = "Date,Description,Debit,Credit,Balance\n";
            $csvContent .= "2024-01-05,SALARY,,50000,150000\n";
            $csvContent .= ",,,,\n";
            $csvContent .= "2024-01-10,RENT,15000,,135000\n";

            Storage::put('statements/gaps.csv', $csvContent);

            $file = ImportedFile::factory()->csv()->create([
                'file_path' => 'statements/gaps.csv',
                'original_filename' => 'gaps.csv',
                'status' => ImportStatus::Pending,
            ]);

            $this->processor->process($file);

            $file->refresh();
            expect($file->total_rows)->toBe(2);
        });

        it('cleans currency formatting from numeric fields', function () {
            $csvContent = "Date,Description,Debit,Credit,Balance\n";
            $csvContent .= "2024-01-05,TRANSFER,\"1,500.00\",,\"98,500.00\"\n";

            Storage::put('statements/currency.csv', $csvContent);

            $file = ImportedFile::factory()->csv()->create([
                'file_path' => 'statements/currency.csv',
                'original_filename' => 'currency.csv',
                'status' => ImportStatus::Pending,
            ]);

            $this->processor->process($file);

            $transaction = Transaction::where('imported_file_id', $file->id)->first();
            expect($transaction->debit)->toBe('1500.00')
                ->and($transaction->balance)->toBe('98500.00');
        });

        it('stores original row data in raw_data field', function () {
            $csvContent = "Date,Description,Debit,Credit,Balance\n";
            $csvContent .= "2024-01-05,SALARY,,50000,150000\n";

            Storage::put('statements/raw.csv', $csvContent);

            $file = ImportedFile::factory()->csv()->create([
                'file_path' => 'statements/raw.csv',
                'original_filename' => 'raw.csv',
                'status' => ImportStatus::Pending,
            ]);

            $this->processor->process($file);

            $transaction = Transaction::where('imported_file_id', $file->id)->first();
            expect($transaction->raw_data)->toBeArray()
                ->and($transaction->raw_data)->toHaveKey('description');
        });

        it('sets status to processing before parsing', function () {
            $csvContent = "Date,Description,Debit,Credit,Balance\n";
            $csvContent .= "2024-01-05,TEST,,100,100\n";

            Storage::put('statements/proc.csv', $csvContent);

            $file = ImportedFile::factory()->csv()->create([
                'file_path' => 'statements/proc.csv',
                'original_filename' => 'proc.csv',
                'status' => ImportStatus::Pending,
            ]);

            // After process completes, it should be Completed (went through Processing)
            $this->processor->process($file);

            $file->refresh();
            expect($file->status)->toBe(ImportStatus::Completed);
        });
    });

    describe('PDF routing', function () {
        it('routes bank statement PDFs to StatementParser agent', function () {
            Storage::put('statements/bank.pdf', 'fake-pdf-content');

            StatementParser::fake([
                [
                    'bank_name' => 'HDFC Bank',
                    'account_number' => '1234567890',
                    'statement_period' => '2024-01-01 to 2024-01-31',
                    'transactions' => [
                        ['date' => '2024-01-05', 'description' => 'SALARY', 'credit' => 50000, 'balance' => 150000],
                    ],
                ],
            ]);

            $file = ImportedFile::factory()->create([
                'file_path' => 'statements/bank.pdf',
                'original_filename' => 'bank_statement.pdf',
                'statement_type' => StatementType::Bank,
                'status' => ImportStatus::Pending,
            ]);

            $this->processor->process($file);

            StatementParser::assertPrompted('Parse all transactions from this bank statement. Extract every single transaction row.');

            $file->refresh();
            expect($file->status)->toBe(ImportStatus::Completed)
                ->and($file->bank_name)->toBe('HDFC Bank')
                ->and($file->total_rows)->toBe(1);
        });

        it('routes credit card statement PDFs to StatementParser agent', function () {
            Storage::put('statements/cc.pdf', 'fake-pdf-content');

            StatementParser::fake([
                [
                    'bank_name' => 'ICICI CC',
                    'transactions' => [
                        ['date' => '2024-01-05', 'description' => 'AMAZON', 'debit' => 2000, 'balance' => 2000],
                    ],
                ],
            ]);

            $file = ImportedFile::factory()->creditCard()->create([
                'file_path' => 'statements/cc.pdf',
                'original_filename' => 'cc_statement.pdf',
                'status' => ImportStatus::Pending,
            ]);

            $this->processor->process($file);

            StatementParser::assertPrompted('Parse all transactions from this bank statement. Extract every single transaction row.');

            $file->refresh();
            expect($file->status)->toBe(ImportStatus::Completed);
        });

        it('throws for invoice PDFs since InvoiceParser is not yet implemented', function () {
            Storage::put('statements/invoice.pdf', 'fake-pdf-content');

            $file = ImportedFile::factory()->invoice()->create([
                'file_path' => 'statements/invoice.pdf',
                'original_filename' => 'vendor_invoice.pdf',
                'status' => ImportStatus::Pending,
            ]);

            $this->processor->process($file);
        })->throws(\RuntimeException::class, 'Invoice parsing is not yet implemented');

        it('marks file as failed when PDF has no transactions', function () {
            Storage::put('statements/empty.pdf', 'fake-pdf-content');

            StatementParser::fake([
                [
                    'bank_name' => 'SBI',
                    'transactions' => [],
                ],
            ]);

            $file = ImportedFile::factory()->create([
                'file_path' => 'statements/empty.pdf',
                'original_filename' => 'empty.pdf',
                'statement_type' => StatementType::Bank,
                'status' => ImportStatus::Pending,
            ]);

            $this->processor->process($file);

            $file->refresh();
            expect($file->status)->toBe(ImportStatus::Failed)
                ->and($file->error_message)->toContain('No transactions found');
        });
    });

    describe('unsupported formats', function () {
        it('throws for unsupported file extensions', function () {
            $file = ImportedFile::factory()->create([
                'original_filename' => 'document.txt',
            ]);

            $this->processor->process($file);
        })->throws(\RuntimeException::class, 'Unsupported file extension');
    });
});
