<?php

use App\Ai\Agents\InvoiceParser;
use App\Enums\ImportStatus;
use App\Enums\MappingType;
use App\Jobs\MatchTransactionHeads;
use App\Jobs\ProcessImportedFile;
use App\Models\ImportedFile;
use App\Models\Transaction;
use App\Services\DocumentProcessor\DocumentProcessor;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

describe('DocumentProcessor invoice routing', function () {
    beforeEach(function () {
        Storage::fake('local');
        $this->processor = new DocumentProcessor;
    });

    it('routes invoice PDFs to InvoiceParser agent', function () {
        Storage::put('statements/invoice.pdf', 'fake-pdf-content');

        InvoiceParser::fake([
            [
                'vendor_name' => 'Assetpro Solution Pvt Ltd',
                'vendor_gstin' => '29AAQCA1895C1ZD',
                'invoice_number' => 'ASPL/2439',
                'invoice_date' => '2025-03-25',
                'due_date' => '2025-04-25',
                'place_of_supply' => 'Karnataka',
                'line_items' => [
                    [
                        'description' => 'Office Assistant and Housekeeping charges',
                        'hsn_sac' => '998519',
                        'quantity' => 1,
                        'rate' => 27500.00,
                        'amount' => 27500.00,
                    ],
                ],
                'base_amount' => 27500.00,
                'cgst_rate' => 9,
                'cgst_amount' => 2475.00,
                'sgst_rate' => 9,
                'sgst_amount' => 2475.00,
                'igst_rate' => null,
                'igst_amount' => null,
                'tds_amount' => 550.00,
                'total_amount' => 31900.00,
                'amount_in_words' => 'Thirty One Thousand Nine Hundred Only',
            ],
        ]);

        $file = ImportedFile::factory()->invoice()->create([
            'file_path' => 'statements/invoice.pdf',
            'original_filename' => 'vendor_invoice.pdf',
            'status' => ImportStatus::Pending,
        ]);

        $this->processor->process($file);

        InvoiceParser::assertPrompted('Parse all data from this vendor invoice. Extract every field including line items, GST breakup, and TDS if present.');

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::Completed)
            ->and($file->total_rows)->toBe(1)
            ->and($file->mapped_rows)->toBe(0)
            ->and($file->processed_at)->not->toBeNull();
    });

    it('creates a transaction with full invoice data in raw_data', function () {
        Storage::put('statements/invoice.pdf', 'fake-pdf-content');

        InvoiceParser::fake([
            [
                'vendor_name' => 'Assetpro Solution Pvt Ltd',
                'vendor_gstin' => '29AAQCA1895C1ZD',
                'invoice_number' => 'ASPL/2439',
                'invoice_date' => '2025-03-25',
                'due_date' => '2025-04-25',
                'place_of_supply' => 'Karnataka',
                'line_items' => [
                    [
                        'description' => 'Office Assistant and Housekeeping charges',
                        'hsn_sac' => '998519',
                        'quantity' => 1,
                        'rate' => 27500.00,
                        'amount' => 27500.00,
                    ],
                ],
                'base_amount' => 27500.00,
                'cgst_rate' => 9,
                'cgst_amount' => 2475.00,
                'sgst_rate' => 9,
                'sgst_amount' => 2475.00,
                'igst_rate' => null,
                'igst_amount' => null,
                'tds_amount' => 550.00,
                'total_amount' => 31900.00,
                'amount_in_words' => 'Thirty One Thousand Nine Hundred Only',
            ],
        ]);

        $file = ImportedFile::factory()->invoice()->create([
            'file_path' => 'statements/invoice.pdf',
            'original_filename' => 'vendor_invoice.pdf',
            'status' => ImportStatus::Pending,
        ]);

        $this->processor->process($file);

        $transactions = Transaction::where('imported_file_id', $file->id)->get();
        expect($transactions)->toHaveCount(1);

        $transaction = $transactions->first();
        expect($transaction->description)->toBe('ASPL/2439 - Assetpro Solution Pvt Ltd')
            ->and($transaction->reference_number)->toBe('ASPL/2439')
            ->and($transaction->debit)->toBe('31900')
            ->and($transaction->credit)->toBeNull()
            ->and($transaction->mapping_type)->toBe(MappingType::Unmapped)
            ->and($transaction->raw_data)->toBeArray()
            ->and($transaction->raw_data['vendor_name'])->toBe('Assetpro Solution Pvt Ltd')
            ->and($transaction->raw_data['vendor_gstin'])->toBe('29AAQCA1895C1ZD')
            ->and($transaction->raw_data['cgst_amount'])->toEqual(2475.00)
            ->and($transaction->raw_data['sgst_amount'])->toEqual(2475.00)
            ->and($transaction->raw_data['tds_amount'])->toEqual(550.00)
            ->and($transaction->raw_data['line_items'])->toHaveCount(1);
    });

    it('sets transaction date from invoice_date', function () {
        Storage::put('statements/invoice.pdf', 'fake-pdf-content');

        InvoiceParser::fake([
            [
                'vendor_name' => 'Test Vendor',
                'vendor_gstin' => '29AABCT1234A1Z5',
                'invoice_number' => 'TV/001',
                'invoice_date' => '2025-06-15',
                'due_date' => null,
                'place_of_supply' => 'Karnataka',
                'line_items' => [
                    ['description' => 'Service', 'hsn_sac' => '998311', 'quantity' => 1, 'rate' => 10000.00, 'amount' => 10000.00],
                ],
                'base_amount' => 10000.00,
                'cgst_rate' => 9,
                'cgst_amount' => 900.00,
                'sgst_rate' => 9,
                'sgst_amount' => 900.00,
                'igst_rate' => null,
                'igst_amount' => null,
                'tds_amount' => null,
                'total_amount' => 11800.00,
                'amount_in_words' => 'Eleven Thousand Eight Hundred Only',
            ],
        ]);

        $file = ImportedFile::factory()->invoice()->create([
            'file_path' => 'statements/invoice.pdf',
            'status' => ImportStatus::Pending,
        ]);

        $this->processor->process($file);

        $transaction = Transaction::where('imported_file_id', $file->id)->first();
        expect($transaction->date->format('Y-m-d'))->toBe('2025-06-15');
    });

    it('handles inter-state invoice with IGST', function () {
        Storage::put('statements/invoice-igst.pdf', 'fake-pdf-content');

        InvoiceParser::fake([
            [
                'vendor_name' => 'Delhi Tech Services',
                'vendor_gstin' => '07AABCT1234A1Z5',
                'invoice_number' => 'DTS/2025/050',
                'invoice_date' => '2025-02-20',
                'due_date' => '2025-03-20',
                'place_of_supply' => 'Delhi',
                'line_items' => [
                    ['description' => 'Cloud hosting', 'hsn_sac' => '998315', 'quantity' => 1, 'rate' => 40000.00, 'amount' => 40000.00],
                ],
                'base_amount' => 40000.00,
                'cgst_rate' => null,
                'cgst_amount' => null,
                'sgst_rate' => null,
                'sgst_amount' => null,
                'igst_rate' => 18,
                'igst_amount' => 7200.00,
                'tds_amount' => null,
                'total_amount' => 47200.00,
                'amount_in_words' => 'Forty Seven Thousand Two Hundred Only',
            ],
        ]);

        $file = ImportedFile::factory()->invoice()->create([
            'file_path' => 'statements/invoice-igst.pdf',
            'status' => ImportStatus::Pending,
        ]);

        $this->processor->process($file);

        $transaction = Transaction::where('imported_file_id', $file->id)->first();
        expect($transaction->raw_data['igst_rate'])->toBe(18)
            ->and($transaction->raw_data['igst_amount'])->toEqual(7200.00)
            ->and($transaction->raw_data['cgst_amount'])->toBeNull()
            ->and($transaction->raw_data['sgst_amount'])->toBeNull()
            ->and($transaction->debit)->toBe('47200');
    });

    it('handles invoice with TDS deduction', function () {
        Storage::put('statements/invoice-tds.pdf', 'fake-pdf-content');

        InvoiceParser::fake([
            [
                'vendor_name' => 'Consulting Firm',
                'vendor_gstin' => '29AABCF1234A1Z5',
                'invoice_number' => 'CF/100',
                'invoice_date' => '2025-04-01',
                'due_date' => '2025-05-01',
                'place_of_supply' => 'Karnataka',
                'line_items' => [
                    ['description' => 'Professional consulting', 'hsn_sac' => '998311', 'quantity' => 1, 'rate' => 100000.00, 'amount' => 100000.00],
                ],
                'base_amount' => 100000.00,
                'cgst_rate' => 9,
                'cgst_amount' => 9000.00,
                'sgst_rate' => 9,
                'sgst_amount' => 9000.00,
                'igst_rate' => null,
                'igst_amount' => null,
                'tds_amount' => 10000.00,
                'total_amount' => 108000.00,
                'amount_in_words' => 'One Lakh Eight Thousand Only',
            ],
        ]);

        $file = ImportedFile::factory()->invoice()->create([
            'file_path' => 'statements/invoice-tds.pdf',
            'status' => ImportStatus::Pending,
        ]);

        $this->processor->process($file);

        $transaction = Transaction::where('imported_file_id', $file->id)->first();
        expect($transaction->raw_data['tds_amount'])->toEqual(10000.00)
            ->and($transaction->raw_data['base_amount'])->toEqual(100000.00)
            ->and($transaction->debit)->toBe('108000');
    });

    it('marks file as failed when InvoiceParser returns malformed response', function () {
        Storage::put('statements/bad-invoice.pdf', 'fake-pdf-content');

        InvoiceParser::fake([
            ['vendor_name' => 'Unknown Vendor'],
        ]);

        $file = ImportedFile::factory()->invoice()->create([
            'file_path' => 'statements/bad-invoice.pdf',
            'status' => ImportStatus::Pending,
        ]);

        Log::shouldReceive('warning')->once();

        expect(fn () => $this->processor->process($file))
            ->toThrow(\RuntimeException::class, 'InvoiceParser response missing required fields');
    });

    it('marks file as failed when invoice_number is missing', function () {
        Storage::put('statements/no-number.pdf', 'fake-pdf-content');

        InvoiceParser::fake([
            [
                'vendor_name' => 'Test Vendor',
                'vendor_gstin' => null,
                'invoice_number' => null,
                'invoice_date' => '2025-01-01',
                'due_date' => null,
                'place_of_supply' => null,
                'line_items' => [],
                'base_amount' => 1000.00,
                'cgst_rate' => null,
                'cgst_amount' => null,
                'sgst_rate' => null,
                'sgst_amount' => null,
                'igst_rate' => null,
                'igst_amount' => null,
                'tds_amount' => null,
                'total_amount' => 1000.00,
                'amount_in_words' => null,
            ],
        ]);

        $file = ImportedFile::factory()->invoice()->create([
            'file_path' => 'statements/no-number.pdf',
            'status' => ImportStatus::Pending,
        ]);

        Log::shouldReceive('warning')->once();

        expect(fn () => $this->processor->process($file))
            ->toThrow(\RuntimeException::class, 'InvoiceParser response missing required fields');
    });

    it('updates bank_name on imported file with vendor name', function () {
        Storage::put('statements/invoice.pdf', 'fake-pdf-content');

        InvoiceParser::fake([
            [
                'vendor_name' => 'Assetpro Solution Pvt Ltd',
                'vendor_gstin' => '29AAQCA1895C1ZD',
                'invoice_number' => 'ASPL/2439',
                'invoice_date' => '2025-03-25',
                'due_date' => null,
                'place_of_supply' => 'Karnataka',
                'line_items' => [
                    ['description' => 'Service', 'hsn_sac' => '998519', 'quantity' => 1, 'rate' => 1000.00, 'amount' => 1000.00],
                ],
                'base_amount' => 1000.00,
                'cgst_rate' => 9,
                'cgst_amount' => 90.00,
                'sgst_rate' => 9,
                'sgst_amount' => 90.00,
                'igst_rate' => null,
                'igst_amount' => null,
                'tds_amount' => null,
                'total_amount' => 1180.00,
                'amount_in_words' => null,
            ],
        ]);

        $file = ImportedFile::factory()->invoice()->create([
            'file_path' => 'statements/invoice.pdf',
            'status' => ImportStatus::Pending,
        ]);

        $this->processor->process($file);

        $file->refresh();
        expect($file->bank_name)->toBe('Assetpro Solution Pvt Ltd');
    });
});

describe('ProcessImportedFile job with InvoiceParser', function () {
    it('processes invoice and dispatches MatchTransactionHeads on success', function () {
        Storage::fake('local');
        Storage::put('statements/invoice.pdf', 'fake-pdf-content');

        InvoiceParser::fake([
            [
                'vendor_name' => 'Test Vendor',
                'vendor_gstin' => '29AABCT1234A1Z5',
                'invoice_number' => 'TV/001',
                'invoice_date' => '2025-01-15',
                'due_date' => '2025-02-15',
                'place_of_supply' => 'Karnataka',
                'line_items' => [
                    ['description' => 'Consulting', 'hsn_sac' => '998311', 'quantity' => 1, 'rate' => 20000.00, 'amount' => 20000.00],
                ],
                'base_amount' => 20000.00,
                'cgst_rate' => 9,
                'cgst_amount' => 1800.00,
                'sgst_rate' => 9,
                'sgst_amount' => 1800.00,
                'igst_rate' => null,
                'igst_amount' => null,
                'tds_amount' => null,
                'total_amount' => 23600.00,
                'amount_in_words' => 'Twenty Three Thousand Six Hundred Only',
            ],
        ]);

        Queue::fake([MatchTransactionHeads::class]);

        $file = ImportedFile::factory()->invoice()->create([
            'status' => ImportStatus::Pending,
            'file_path' => 'statements/invoice.pdf',
        ]);

        $job = new ProcessImportedFile($file);
        $job->handle(app(DocumentProcessor::class));

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::Completed)
            ->and($file->total_rows)->toBe(1);

        Queue::assertPushed(MatchTransactionHeads::class, function ($job) use ($file) {
            return $job->importedFile->id === $file->id;
        });
    });

    it('marks invoice file as failed on malformed response', function () {
        Storage::fake('local');
        Storage::put('statements/bad.pdf', 'fake-pdf-content');

        InvoiceParser::fake([
            ['vendor_name' => 'Unknown'],
        ]);

        $file = ImportedFile::factory()->invoice()->create([
            'status' => ImportStatus::Pending,
            'file_path' => 'statements/bad.pdf',
        ]);

        Log::shouldReceive('error')->once();
        Log::shouldReceive('warning')->once();

        $job = new ProcessImportedFile($file);

        try {
            $job->handle(app(DocumentProcessor::class));
        } catch (\Throwable) {
            // Expected — missing required fields
        }

        $file->refresh();
        expect($file->status)->toBe(ImportStatus::Failed)
            ->and($file->error_message)->not->toBeNull();
    });
});
