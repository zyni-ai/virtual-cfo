<?php

namespace App\Services\Reconciliation;

use App\Enums\MatchMethod;
use App\Enums\ReconciliationStatus;
use App\Models\ImportedFile;
use App\Models\ReconciliationMatch;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReconciliationService
{
    /**
     * Default tolerance for amount matching (in currency units).
     * Allows for minor rounding differences.
     */
    private const DEFAULT_AMOUNT_TOLERANCE = 1.0;

    /**
     * Default date window in days for date proximity matching.
     * Payment is expected within this many days after invoice date.
     */
    private const DEFAULT_DATE_WINDOW = 60;

    /**
     * Minimum string similarity percentage (0-100) to consider a party name match.
     */
    private const MIN_PARTY_SIMILARITY = 60;

    /**
     * Run the full reconciliation process for a bank file against an invoice file.
     */
    public function reconcile(
        ImportedFile $bankFile,
        ImportedFile $invoiceFile,
        float $tolerance = self::DEFAULT_AMOUNT_TOLERANCE,
        int $dayWindow = self::DEFAULT_DATE_WINDOW,
    ): ReconciliationResult {
        $result = new ReconciliationResult;

        $bankTransactions = $bankFile->transactions()
            ->where('reconciliation_status', ReconciliationStatus::Unreconciled)
            ->get();

        $invoiceTransactions = $invoiceFile->transactions()
            ->where('reconciliation_status', ReconciliationStatus::Unreconciled)
            ->get();

        if ($bankTransactions->isEmpty() || $invoiceTransactions->isEmpty()) {
            $this->flagUnmatched($bankFile, $invoiceFile);
            $result->flagged = $bankTransactions->count() + $invoiceTransactions->count();

            return $result;
        }

        // Track which invoices have been matched to avoid double-matching
        /** @var Collection<int, int> $matchedInvoiceIds */
        $matchedInvoiceIds = collect();

        DB::transaction(function () use (
            $bankTransactions,
            $invoiceTransactions,
            &$matchedInvoiceIds,
            &$result,
            $tolerance,
            $dayWindow,
        ) {
            foreach ($bankTransactions as $bankTxn) {
                /** @var Transaction $bankTxn */
                $availableInvoices = $invoiceTransactions->reject(
                    fn (Transaction $inv) => $matchedInvoiceIds->contains($inv->id)
                );

                if ($availableInvoices->isEmpty()) {
                    break;
                }

                $match = $this->matchByAmount($bankTxn, $availableInvoices, $tolerance);

                if (! $match) {
                    $match = $this->matchByAmountAndDate($bankTxn, $availableInvoices, $tolerance, $dayWindow);
                }

                if (! $match) {
                    $match = $this->matchByPartyName($bankTxn, $availableInvoices, $tolerance, $dayWindow);
                }

                if ($match) {
                    $matchedInvoiceIds->push($match->invoice_transaction_id);
                    $result->matched++;
                }
            }
        });

        // Flag remaining unmatched items
        $this->flagUnmatched($bankFile, $invoiceFile);

        // Count flagged items
        $result->flagged = Transaction::where(function ($query) use ($bankFile, $invoiceFile) {
            $query->where('imported_file_id', $bankFile->id)
                ->orWhere('imported_file_id', $invoiceFile->id);
        })->where('reconciliation_status', ReconciliationStatus::Flagged)->count();

        $result->unreconciled = Transaction::where(function ($query) use ($bankFile, $invoiceFile) {
            $query->where('imported_file_id', $bankFile->id)
                ->orWhere('imported_file_id', $invoiceFile->id);
        })->where('reconciliation_status', ReconciliationStatus::Unreconciled)->count();

        return $result;
    }

    /**
     * Match by exact amount (within tolerance).
     * Returns the created ReconciliationMatch or null if no match found.
     *
     * @param  Collection<int, Transaction>  $invoices
     */
    public function matchByAmount(
        Transaction $bankTxn,
        Collection $invoices,
        float $tolerance = self::DEFAULT_AMOUNT_TOLERANCE,
    ): ?ReconciliationMatch {
        $bankAmount = $bankTxn->amount;

        if ($bankAmount === null) {
            return null;
        }

        foreach ($invoices as $invoice) {
            /** @var Transaction $invoice */
            $invoiceAmount = $invoice->amount;

            if ($invoiceAmount === null) {
                continue;
            }

            if (abs($bankAmount - $invoiceAmount) <= $tolerance) {
                return $this->createMatch(
                    $bankTxn,
                    $invoice,
                    $this->calculateAmountConfidence($bankAmount, $invoiceAmount),
                    MatchMethod::Amount,
                );
            }
        }

        return null;
    }

    /**
     * Match by amount + date proximity.
     * The bank payment date should be on or after the invoice date, within the day window.
     *
     * @param  Collection<int, Transaction>  $invoices
     */
    public function matchByAmountAndDate(
        Transaction $bankTxn,
        Collection $invoices,
        float $tolerance = self::DEFAULT_AMOUNT_TOLERANCE,
        int $dayWindow = self::DEFAULT_DATE_WINDOW,
    ): ?ReconciliationMatch {
        $bankAmount = $bankTxn->amount;

        /** @var Carbon|null $bankDate */
        $bankDate = $bankTxn->date;

        if ($bankAmount === null || $bankDate === null) {
            return null;
        }

        /** @var Collection<int, array{invoice: Transaction, days_diff: int}> $candidates */
        $candidates = collect();

        foreach ($invoices as $invoice) {
            /** @var Transaction $invoice */
            $invoiceAmount = $invoice->amount;

            /** @var Carbon|null $invoiceDate */
            $invoiceDate = $invoice->date;

            if ($invoiceAmount === null || $invoiceDate === null) {
                continue;
            }

            if (abs($bankAmount - $invoiceAmount) > $tolerance) {
                continue;
            }

            // Bank payment should be on or after invoice date, within window
            if ($bankDate->lt($invoiceDate)) {
                continue;
            }

            $daysDiff = (int) abs($bankDate->diffInDays($invoiceDate));

            if ($daysDiff <= $dayWindow) {
                $candidates->push([
                    'invoice' => $invoice,
                    'days_diff' => $daysDiff,
                ]);
            }
        }

        if ($candidates->isEmpty()) {
            return null;
        }

        // Pick the closest date match
        $best = $candidates->sortBy('days_diff')->first();

        /** @var Transaction $bestInvoice */
        $bestInvoice = $best['invoice'];

        $amountConfidence = $this->calculateAmountConfidence($bankAmount, $bestInvoice->amount ?? 0.0);
        $dateConfidence = max(0.0, 1.0 - ($best['days_diff'] / $dayWindow));
        $confidence = ($amountConfidence + $dateConfidence) / 2;

        return $this->createMatch(
            $bankTxn,
            $bestInvoice,
            $confidence,
            MatchMethod::AmountDate,
        );
    }

    /**
     * Match by amount + date + party name fuzzy match.
     * Uses PHP's similar_text() for fuzzy string comparison.
     *
     * @param  Collection<int, Transaction>  $invoices
     */
    public function matchByPartyName(
        Transaction $bankTxn,
        Collection $invoices,
        float $tolerance = self::DEFAULT_AMOUNT_TOLERANCE,
        int $dayWindow = self::DEFAULT_DATE_WINDOW,
    ): ?ReconciliationMatch {
        $bankAmount = $bankTxn->amount;

        /** @var Carbon|null $bankDate */
        $bankDate = $bankTxn->date;

        /** @var string|null $bankDescription */
        $bankDescription = $bankTxn->description;

        if ($bankAmount === null || $bankDescription === null) {
            return null;
        }

        $bestMatch = null;
        $bestConfidence = 0.0;

        foreach ($invoices as $invoice) {
            /** @var Transaction $invoice */
            $invoiceAmount = $invoice->amount;

            if ($invoiceAmount === null) {
                continue;
            }

            if (abs($bankAmount - $invoiceAmount) > $tolerance) {
                continue;
            }

            // Date check (optional - if dates available)
            if ($bankDate !== null && $invoice->date !== null) {
                /** @var Carbon $invDate */
                $invDate = $invoice->date;
                if ($bankDate->lt($invDate)) {
                    continue;
                }
                $daysDiff = (int) abs($bankDate->diffInDays($invDate));
                if ($daysDiff > $dayWindow) {
                    continue;
                }
            }

            // Fuzzy match party name from bank description against invoice description
            /** @var string $invoiceDescription */
            $invoiceDescription = $invoice->description ?? '';
            $similarity = $this->calculateNameSimilarity($bankDescription, $invoiceDescription);

            // Also check against vendor_name in raw_data if available
            /** @var array<string, mixed>|null $rawData */
            $rawData = $invoice->raw_data;
            if (is_array($rawData) && isset($rawData['vendor_name'])) {
                $vendorSimilarity = $this->calculateNameSimilarity(
                    $bankDescription,
                    (string) $rawData['vendor_name']
                );
                $similarity = max($similarity, $vendorSimilarity);
            }

            if ($similarity >= self::MIN_PARTY_SIMILARITY && $similarity > $bestConfidence) {
                $bestMatch = $invoice;
                $bestConfidence = $similarity;
            }
        }

        if ($bestMatch === null) {
            return null;
        }

        $amountConfidence = $this->calculateAmountConfidence($bankAmount, $bestMatch->amount ?? 0.0);
        $nameConfidence = $bestConfidence / 100;
        $confidence = ($amountConfidence + $nameConfidence) / 2;

        return $this->createMatch(
            $bankTxn,
            $bestMatch,
            round($confidence, 4),
            MatchMethod::AmountDateParty,
        );
    }

    /**
     * Flag all unmatched transactions in both files.
     */
    public function flagUnmatched(ImportedFile $bankFile, ImportedFile $invoiceFile): void
    {
        // Flag unreconciled bank transactions
        Transaction::where('imported_file_id', $bankFile->id)
            ->where('reconciliation_status', ReconciliationStatus::Unreconciled)
            ->update(['reconciliation_status' => ReconciliationStatus::Flagged]);

        // Flag unreconciled invoice transactions
        Transaction::where('imported_file_id', $invoiceFile->id)
            ->where('reconciliation_status', ReconciliationStatus::Unreconciled)
            ->update(['reconciliation_status' => ReconciliationStatus::Flagged]);
    }

    /**
     * Enrich matched bank transactions with invoice data from their raw_data.
     */
    public function enrichMatchedTransactions(ImportedFile $bankFile): void
    {
        $matchedTransactions = Transaction::where('imported_file_id', $bankFile->id)
            ->where('reconciliation_status', ReconciliationStatus::Matched)
            ->get();

        foreach ($matchedTransactions as $bankTxn) {
            /** @var Transaction $bankTxn */
            $match = ReconciliationMatch::where('bank_transaction_id', $bankTxn->id)->first();

            if (! $match) {
                continue;
            }

            /** @var Transaction|null $invoiceTxn */
            $invoiceTxn = $match->invoiceTransaction;

            if (! $invoiceTxn) {
                continue;
            }

            /** @var array<string, mixed>|null $invoiceRawData */
            $invoiceRawData = $invoiceTxn->raw_data;

            if (! is_array($invoiceRawData)) {
                continue;
            }

            $enrichment = [
                'reconciled_invoice_id' => $invoiceTxn->id,
                'vendor_name' => $invoiceRawData['vendor_name'] ?? null,
                'vendor_gstin' => $invoiceRawData['vendor_gstin'] ?? null,
                'invoice_number' => $invoiceRawData['invoice_number'] ?? $invoiceTxn->reference_number,
                'base_amount' => $invoiceRawData['base_amount'] ?? null,
                'cgst_amount' => $invoiceRawData['cgst_amount'] ?? null,
                'sgst_amount' => $invoiceRawData['sgst_amount'] ?? null,
                'tds_amount' => $invoiceRawData['tds_amount'] ?? null,
                'line_items' => $invoiceRawData['line_items'] ?? null,
            ];

            /** @var array<string, mixed> $currentRawData */
            $currentRawData = $bankTxn->raw_data ?? [];
            $bankTxn->update([
                'raw_data' => array_merge($currentRawData, $enrichment),
            ]);
        }
    }

    /**
     * Create a reconciliation match record and update both transaction statuses.
     */
    private function createMatch(
        Transaction $bankTxn,
        Transaction $invoiceTxn,
        float $confidence,
        MatchMethod $method,
    ): ReconciliationMatch {
        return DB::transaction(function () use ($bankTxn, $invoiceTxn, $confidence, $method) {
            $match = ReconciliationMatch::create([
                'bank_transaction_id' => $bankTxn->id,
                'invoice_transaction_id' => $invoiceTxn->id,
                'confidence' => round($confidence, 4),
                'match_method' => $method,
            ]);

            $bankTxn->update(['reconciliation_status' => ReconciliationStatus::Matched]);
            $invoiceTxn->update(['reconciliation_status' => ReconciliationStatus::Matched]);

            Log::info('Reconciliation match created', [
                'bank_transaction_id' => $bankTxn->id,
                'invoice_transaction_id' => $invoiceTxn->id,
                'method' => $method->value,
                'confidence' => $confidence,
            ]);

            return $match;
        });
    }

    /**
     * Calculate confidence based on amount difference.
     * Exact match = 1.0, at tolerance boundary = 0.9.
     */
    private function calculateAmountConfidence(float $bankAmount, float $invoiceAmount): float
    {
        $diff = abs($bankAmount - $invoiceAmount);

        if ($diff === 0.0) {
            return 1.0;
        }

        // Confidence decreases linearly from 1.0 to 0.9 as diff approaches tolerance
        $maxAmount = max($bankAmount, $invoiceAmount, 1.0);

        return max(0.9, 1.0 - ($diff / $maxAmount));
    }

    /**
     * Calculate string similarity percentage between two party names.
     * Normalizes strings before comparison.
     */
    private function calculateNameSimilarity(string $bankDescription, string $invoiceDescription): float
    {
        $normalized1 = $this->normalizePartyName($bankDescription);
        $normalized2 = $this->normalizePartyName($invoiceDescription);

        if ($normalized1 === '' || $normalized2 === '') {
            return 0.0;
        }

        // Check if one contains the other (common with abbreviated names)
        if (str_contains($normalized1, $normalized2) || str_contains($normalized2, $normalized1)) {
            return 90.0;
        }

        $similarity = 0.0;
        similar_text($normalized1, $normalized2, $similarity);

        return $similarity;
    }

    /**
     * Normalize a party name for comparison.
     * Strips common prefixes, suffixes, and noise words from bank narrations.
     */
    private function normalizePartyName(string $name): string
    {
        $name = mb_strtolower(trim($name));

        // Remove common bank narration prefixes
        $prefixes = ['neft-', 'rtgs-', 'upi/', 'imps/', 'neft/', 'rtgs/'];
        foreach ($prefixes as $prefix) {
            if (str_starts_with($name, $prefix)) {
                $name = mb_substr($name, mb_strlen($prefix));
            }
        }

        // Remove common suffixes
        $suffixes = [' pvt ltd', ' private limited', ' limited', ' ltd', ' llp', ' inc'];
        foreach ($suffixes as $suffix) {
            if (str_ends_with($name, $suffix)) {
                $name = mb_substr($name, 0, mb_strlen($name) - mb_strlen($suffix));
            }
        }

        // Remove reference numbers (sequences of digits)
        $name = (string) preg_replace('/\d{4,}/', '', $name);

        // Remove extra whitespace and special characters
        $name = (string) preg_replace('/[^a-z0-9\s]/', ' ', $name);
        $name = (string) preg_replace('/\s+/', ' ', $name);

        return trim($name);
    }
}
