<?php

namespace App\Services\DocumentProcessor;

use App\Ai\Agents\InvoiceParser;
use App\Ai\Agents\StatementParser;
use App\Enums\ImportStatus;
use App\Enums\MappingType;
use App\Enums\StatementType;
use App\Models\ImportedFile;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class DocumentProcessor
{
    public function __construct(
        protected OcrService $ocrService = new OcrService,
    ) {}

    /**
     * Process an imported file by detecting its format and routing to the appropriate parser.
     */
    public function process(ImportedFile $file): void
    {
        $file->update(['status' => ImportStatus::Processing]);

        $format = $this->detectFormat($file);

        match ($format) {
            'csv', 'xlsx' => $this->parseStructured($file),
            'pdf' => $this->parsePdf($file),
            default => throw new \RuntimeException("Unsupported file format: {$format}"),
        };
    }

    /**
     * Detect file format from the original filename extension.
     */
    public function detectFormat(ImportedFile $file): string
    {
        $extension = strtolower(pathinfo($file->original_filename, PATHINFO_EXTENSION));

        $supportedFormats = ['pdf', 'csv', 'xlsx'];

        if (! in_array($extension, $supportedFormats)) {
            throw new \RuntimeException(
                "Unsupported file extension: .{$extension}. Supported: ".implode(', ', $supportedFormats)
            );
        }

        return $extension;
    }

    /**
     * Parse structured files (CSV/XLSX) programmatically via Maatwebsite Excel.
     */
    protected function parseStructured(ImportedFile $file): void
    {
        $import = new StructuredFileImport;

        Excel::import($import, $file->file_path, 'local');

        $rows = $import->getRows();

        if (empty($rows)) {
            $file->update([
                'status' => ImportStatus::Failed,
                'error_message' => 'No data rows found in the file.',
            ]);

            return;
        }

        DB::transaction(function () use ($file, $rows) {
            foreach ($rows as $row) {
                $normalized = $this->normalizeStructuredRow($row);

                Transaction::create([
                    'company_id' => $file->company_id,
                    'imported_file_id' => $file->id,
                    'date' => $normalized['date'],
                    'description' => $normalized['description'] ?? '',
                    'reference_number' => $normalized['reference'] ?? null,
                    'debit' => $normalized['debit'],
                    'credit' => $normalized['credit'],
                    'balance' => $normalized['balance'],
                    'mapping_type' => MappingType::Unmapped,
                    'raw_data' => $row,
                    'bank_format' => $file->bank_name,
                ]);
            }

            $file->update([
                'status' => ImportStatus::Completed,
                'total_rows' => count($rows),
                'mapped_rows' => 0,
                'processed_at' => now(),
            ]);
        });
    }

    /**
     * Normalize a structured row from CSV/XLSX to the expected transaction format.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function normalizeStructuredRow(array $row): array
    {
        $normalized = [];
        foreach ($row as $key => $value) {
            $normalized[strtolower(trim((string) $key))] = $value;
        }

        return [
            'date' => $this->extractField($normalized, ['date', 'transaction_date', 'txn_date', 'value_date', 'posting_date']),
            'description' => $this->extractField($normalized, ['description', 'narration', 'particulars', 'details', 'transaction_description']),
            'reference' => $this->extractField($normalized, ['reference', 'ref', 'reference_number', 'ref_no', 'cheque_no', 'chq_no']),
            'debit' => $this->extractNumericField($normalized, ['debit', 'debit_amount', 'withdrawal', 'withdrawals', 'dr']),
            'credit' => $this->extractNumericField($normalized, ['credit', 'credit_amount', 'deposit', 'deposits', 'cr']),
            'balance' => $this->extractNumericField($normalized, ['balance', 'closing_balance', 'running_balance', 'available_balance']),
        ];
    }

    /**
     * Extract a field value by trying multiple possible column names.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $possibleKeys
     */
    protected function extractField(array $row, array $possibleKeys): mixed
    {
        foreach ($possibleKeys as $key) {
            if (isset($row[$key]) && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return null;
    }

    /**
     * Extract a numeric field, cleaning currency formatting.
     *
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $possibleKeys
     */
    protected function extractNumericField(array $row, array $possibleKeys): ?string
    {
        $value = $this->extractField($row, $possibleKeys);

        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value === 0.0) {
            return null;
        }

        $cleaned = preg_replace('/[^0-9.\-]/', '', (string) $value);

        return $cleaned !== '' && $cleaned !== '0' ? $cleaned : null;
    }

    /**
     * Parse PDF files by routing to the appropriate AI agent based on statement type.
     */
    protected function parsePdf(ImportedFile $file): void
    {
        /** @var StatementType $statementType */
        $statementType = $file->statement_type;

        match ($statementType) {
            StatementType::Bank, StatementType::CreditCard => $this->parsePdfStatement($file),
            StatementType::Invoice => $this->parsePdfInvoice($file),
        };
    }

    /**
     * Parse a PDF bank/credit card statement via OCR + StatementParser agent.
     */
    protected function parsePdfStatement(ImportedFile $file): void
    {
        $extractedText = $this->ocrService->extractText($file->file_path);

        $response = (new StatementParser)->prompt(
            "Parse all transactions from this bank statement. Extract every single transaction row.\n\n--- STATEMENT TEXT ---\n{$extractedText}"
        );

        if (! isset($response['transactions']) || ! is_array($response['transactions'])) {
            Log::warning('StatementParser returned invalid response', [
                'file_id' => $file->id,
                'response' => $response,
            ]);

            throw new \RuntimeException(
                'StatementParser response missing valid transactions array.'
            );
        }

        $bankName = $response['bank_name'] ?? null;
        $accountNumber = $response['account_number'] ?? null;
        $transactions = $response['transactions'];

        if (empty($transactions)) {
            $file->update([
                'status' => ImportStatus::Failed,
                'error_message' => 'No transactions found in the statement.',
            ]);

            return;
        }

        DB::transaction(function () use ($file, $bankName, $accountNumber, $transactions) {
            if ($bankName) {
                $file->update(['bank_name' => $bankName]);
            }

            if ($accountNumber) {
                $file->update(['account_number' => $accountNumber]);
            }

            foreach ($transactions as $row) {
                Transaction::create([
                    'company_id' => $file->company_id,
                    'imported_file_id' => $file->id,
                    'date' => Carbon::parse($row['date']),
                    'description' => $row['description'] ?? '',
                    'reference_number' => $row['reference'] ?? null,
                    'debit' => isset($row['debit']) ? (string) $row['debit'] : null,
                    'credit' => isset($row['credit']) ? (string) $row['credit'] : null,
                    'balance' => isset($row['balance']) ? (string) $row['balance'] : null,
                    'mapping_type' => MappingType::Unmapped,
                    'raw_data' => $row,
                    'bank_format' => $bankName,
                ]);
            }

            $file->update([
                'status' => ImportStatus::Completed,
                'total_rows' => count($transactions),
                'mapped_rows' => 0,
                'processed_at' => now(),
            ]);
        });
    }

    /**
     * Parse a PDF invoice via OCR + InvoiceParser agent.
     */
    protected function parsePdfInvoice(ImportedFile $file): void
    {
        $extractedText = $this->ocrService->extractText($file->file_path);

        $response = (new InvoiceParser)->prompt(
            "Parse all data from this vendor invoice. Extract every field including line items, GST breakup, and TDS if present.\n\n--- INVOICE TEXT ---\n{$extractedText}"
        );

        if (! isset($response['invoice_number']) || ! isset($response['vendor_name'])
            || ! $response['invoice_number'] || ! $response['vendor_name']) {
            Log::warning('InvoiceParser returned invalid response', [
                'file_id' => $file->id,
                'response' => $response,
            ]);

            throw new \RuntimeException(
                'InvoiceParser response missing required fields (vendor_name, invoice_number).'
            );
        }

        $vendorName = $response['vendor_name'];
        $invoiceNumber = $response['invoice_number'];
        $invoiceDate = $response['invoice_date'] ?? null;
        $totalAmount = $response['total_amount'] ?? null;

        /** @var \Laravel\Ai\Responses\StructuredAgentResponse $response */
        $rawData = $response->toArray();

        DB::transaction(function () use ($file, $rawData, $vendorName, $invoiceNumber, $invoiceDate, $totalAmount) {
            $file->update(['bank_name' => $vendorName]);

            Transaction::create([
                'company_id' => $file->company_id,
                'imported_file_id' => $file->id,
                'date' => $invoiceDate ? Carbon::parse($invoiceDate) : now(),
                'description' => $invoiceNumber.' - '.$vendorName,
                'reference_number' => $invoiceNumber,
                'debit' => $totalAmount !== null ? (string) (int) $totalAmount : null,
                'credit' => null,
                'balance' => null,
                'mapping_type' => MappingType::Unmapped,
                'raw_data' => $rawData,
                'bank_format' => $vendorName,
            ]);

            $file->update([
                'status' => ImportStatus::Completed,
                'total_rows' => 1,
                'mapped_rows' => 0,
                'processed_at' => now(),
            ]);
        });
    }
}
