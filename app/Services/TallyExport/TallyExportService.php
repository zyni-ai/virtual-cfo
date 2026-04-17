<?php

namespace App\Services\TallyExport;

use App\Enums\StatementType;
use App\Models\AccountHead;
use App\Models\Company;
use App\Models\ImportedFile;
use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class TallyExportService
{
    /** @var array<string, int> */
    private array $voucherCounters = [];

    /**
     * Generate Tally-compatible XML for transactions in an imported file.
     */
    public function exportForFile(ImportedFile $importedFile): string
    {
        $importedFile->loadMissing(['company', 'bankAccount']);

        /** @var Collection<int, Transaction> $transactions */
        $transactions = $importedFile->transactions()
            ->whereNotNull('account_head_id')
            ->with('accountHead')
            ->orderBy('date')
            ->get();

        return $this->generateXml($transactions, $importedFile->company, $importedFile->bankAccount?->name, $importedFile);
    }

    /**
     * Export selected transactions to Tally XML.
     *
     * @param  Collection<int, Transaction>  $transactions
     */
    public function exportTransactions(Collection $transactions): string
    {
        /** @var Transaction|null $firstTransaction */
        $firstTransaction = $transactions->first();
        /** @var ImportedFile|null $importedFile */
        $importedFile = $firstTransaction?->importedFile;
        $importedFile?->loadMissing(['company', 'bankAccount']);

        return $this->generateXml(
            $transactions,
            $importedFile?->company,
            $importedFile?->bankAccount?->name,
            $importedFile,
        );
    }

    /**
     * Generate the complete Tally XML envelope.
     *
     * @param  Collection<int, Transaction>  $transactions
     */
    private function generateXml(Collection $transactions, ?Company $company, ?string $bankLedgerName, ?ImportedFile $importedFile = null): string
    {
        $this->voucherCounters = [];
        $companyName = $company?->name ?? '';

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<ENVELOPE>'."\n";
        $xml .= '  <HEADER>'."\n";
        $xml .= '    <TALLYREQUEST>Import Data</TALLYREQUEST>'."\n";
        $xml .= '  </HEADER>'."\n";
        $xml .= '  <BODY>'."\n";
        $xml .= '    <IMPORTDATA>'."\n";
        $xml .= '      <REQUESTDESC>'."\n";
        $reportName = $this->isAllMastersExport($transactions, $importedFile) ? 'All Masters' : 'Vouchers';
        $xml .= '        <REPORTNAME>'.$reportName.'</REPORTNAME>'."\n";
        $xml .= '        <STATICVARIABLES>'."\n";
        $xml .= '          <SVCURRENTCOMPANY>'.$this->escapeXml($companyName).'</SVCURRENTCOMPANY>'."\n";
        $xml .= '        </STATICVARIABLES>'."\n";
        $xml .= '      </REQUESTDESC>'."\n";
        $xml .= '      <REQUESTDATA>'."\n";

        foreach ($transactions as $transaction) {
            $xml .= $this->generateVoucher($transaction, $company, $bankLedgerName, $importedFile);
        }

        if ($company && $transactions->isNotEmpty()) {
            $xml .= $this->generateCompanyFooter($company);
        }

        $xml .= '      </REQUESTDATA>'."\n";
        $xml .= '    </IMPORTDATA>'."\n";
        $xml .= '  </BODY>'."\n";
        $xml .= '</ENVELOPE>';

        return $xml;
    }

    /**
     * Generate a single Tally voucher XML element (Payment, Receipt, or Journal).
     */
    private function generateVoucher(Transaction $transaction, ?Company $company, ?string $bankLedgerName, ?ImportedFile $importedFile = null): string
    {
        if ($importedFile?->statement_type === StatementType::Invoice) {
            /** @var array<string, mixed> $raw */
            $raw = $transaction->raw_data ?? [];

            if (isset($raw['buyer_name'])) {
                return $this->generateSalesVoucher($transaction, $company);
            }

            return $this->generateInvoiceJournalVoucher($transaction, $company);
        }

        $isDebit = $transaction->debit !== null;
        $amount = (float) ($isDebit ? $transaction->debit : $transaction->credit);
        /** @var Carbon $transactionDate */
        $transactionDate = $transaction->date;
        $date = $transactionDate->format('Ymd');
        /** @var AccountHead|null $accountHead */
        $accountHead = $transaction->accountHead;
        $headName = $accountHead?->name ?? 'Unknown';
        $bankName = $bankLedgerName ?? 'Bank Account';
        $voucherNumber = $this->nextVoucherNumber('Journal');

        $xml = '        <TALLYMESSAGE xmlns:UDF="TallyUDF">'."\n";
        $xml .= '          <VOUCHER VCHTYPE="Journal" ACTION="Create" OBJVIEW="Accounting Voucher View">'."\n";
        $xml .= '            <DATE>'.$date.'</DATE>'."\n";
        $xml .= '            <VCHSTATUSDATE>'.$date.'</VCHSTATUSDATE>'."\n";
        $xml .= '            <NARRATION>'.$this->escapeXml($transaction->description ?? '').'</NARRATION>'."\n";
        $xml .= '            <VOUCHERTYPENAME>Journal</VOUCHERTYPENAME>'."\n";
        $xml .= '            <VOUCHERNUMBER>'.$voucherNumber.'</VOUCHERNUMBER>'."\n";

        if ($company) {
            $xml .= '            <CMPGSTIN>'.$this->escapeXml($company->gstin ?? '').'</CMPGSTIN>'."\n";
            $xml .= '            <CMPGSTREGISTRATIONTYPE>'.$this->escapeXml($company->gst_registration_type ?? 'Regular').'</CMPGSTREGISTRATIONTYPE>'."\n";
            $xml .= '            <CMPGSTSTATE>'.$this->escapeXml($company->state ?? '').'</CMPGSTSTATE>'."\n";
        }

        $xml .= '            <EFFECTIVEDATE>'.$date.'</EFFECTIVEDATE>'."\n";
        $xml .= '            <ISDELETED>No</ISDELETED>'."\n";
        $xml .= '            <ISCANCELLED>No</ISCANCELLED>'."\n";
        $xml .= '            <ISONHOLD>No</ISONHOLD>'."\n";
        $xml .= '            <ISOPTIONAL>No</ISOPTIONAL>'."\n";
        $xml .= '            <AUDITED>No</AUDITED>'."\n";
        $xml .= '            <HASCASHFLOW>No</HASCASHFLOW>'."\n";

        $xml .= $this->generateBankJournalLedgerEntries($headName, $bankName, $amount, $isDebit);

        $xml .= '          </VOUCHER>'."\n";
        $xml .= '        </TALLYMESSAGE>'."\n";

        return $xml;
    }

    /**
     * Generate a Journal voucher for an invoice transaction with GST breakup.
     * Multi-leg: expense debit, CGST/SGST (or IGST) debits, TDS credit (optional), vendor party credit.
     */
    private function generateInvoiceJournalVoucher(Transaction $transaction, ?Company $company): string
    {
        /** @var Carbon $transactionDate */
        $transactionDate = $transaction->date;
        $date = $transactionDate->format('Ymd');
        /** @var AccountHead|null $accountHead */
        $accountHead = $transaction->accountHead;
        $headName = $accountHead?->name ?? 'Unknown';
        $voucherNumber = $this->nextVoucherNumber('Journal');

        /** @var array<string, mixed> $raw */
        $raw = $transaction->raw_data ?? [];
        $vendorName = (string) ($raw['vendor_name'] ?? 'Unknown Vendor');
        $vendorGstin = (string) ($raw['vendor_gstin'] ?? '');
        $invoiceNumber = (string) ($raw['invoice_number'] ?? '');
        $baseAmount = (float) ($raw['base_amount'] ?? 0);
        $cgstRate = $raw['cgst_rate'] ?? null;
        $cgstAmount = (float) ($raw['cgst_amount'] ?? 0);
        $sgstRate = $raw['sgst_rate'] ?? null;
        $sgstAmount = (float) ($raw['sgst_amount'] ?? 0);
        $igstRate = $raw['igst_rate'] ?? null;
        $igstAmount = (float) ($raw['igst_amount'] ?? 0);
        $tdsAmount = (float) ($raw['tds_amount'] ?? 0);
        $totalAmount = (float) ($raw['total_amount'] ?? (float) ($transaction->debit ?? 0));

        $narration = $invoiceNumber
            ? "Invoice No: {$invoiceNumber} payment towards {$vendorName}"
            : "Invoice payment towards {$vendorName}";

        $lineItemNarration = $this->buildLineItemNarration($raw);

        if ($lineItemNarration !== null) {
            $narration .= "\n{$lineItemNarration}";
        }

        $xml = '        <TALLYMESSAGE xmlns:UDF="TallyUDF">'."\n";
        $xml .= '          <VOUCHER VCHTYPE="Journal" ACTION="Create" OBJVIEW="Accounting Voucher View">'."\n";
        $xml .= '            <DATE>'.$date.'</DATE>'."\n";
        $xml .= '            <VCHSTATUSDATE>'.$date.'</VCHSTATUSDATE>'."\n";
        $xml .= '            <NARRATION>'.$this->escapeXml($narration).'</NARRATION>'."\n";
        $xml .= '            <VOUCHERTYPENAME>Journal</VOUCHERTYPENAME>'."\n";
        $xml .= '            <VOUCHERNUMBER>'.$voucherNumber.'</VOUCHERNUMBER>'."\n";
        $xml .= '            <PARTYLEDGERNAME>'.$this->escapeXml($vendorName).'</PARTYLEDGERNAME>'."\n";
        $xml .= '            <GSTREGISTRATIONTYPE>Regular</GSTREGISTRATIONTYPE>'."\n";

        if ($vendorGstin !== '') {
            $xml .= '            <PARTYGSTIN>'.$this->escapeXml($vendorGstin).'</PARTYGSTIN>'."\n";
        }

        if ($company) {
            $xml .= '            <CMPGSTIN>'.$this->escapeXml($company->gstin ?? '').'</CMPGSTIN>'."\n";
            $xml .= '            <CMPGSTREGISTRATIONTYPE>'.$this->escapeXml($company->gst_registration_type ?? 'Regular').'</CMPGSTREGISTRATIONTYPE>'."\n";
            $xml .= '            <CMPGSTSTATE>'.$this->escapeXml($company->state ?? '').'</CMPGSTSTATE>'."\n";
        }

        $xml .= '            <EFFECTIVEDATE>'.$date.'</EFFECTIVEDATE>'."\n";
        $xml .= '            <ISDELETED>No</ISDELETED>'."\n";
        $xml .= '            <ISCANCELLED>No</ISCANCELLED>'."\n";
        $xml .= '            <ISONHOLD>No</ISONHOLD>'."\n";
        $xml .= '            <ISOPTIONAL>No</ISOPTIONAL>'."\n";
        $xml .= '            <AUDITED>No</AUDITED>'."\n";

        $xml .= $this->generateExpenseLedgerEntry($headName, $baseAmount, $cgstRate, $sgstRate);
        $xml .= $this->generateGstLedgerEntries($igstRate, $igstAmount, $cgstRate, $cgstAmount, $sgstRate, $sgstAmount);

        if ($tdsAmount > 0) {
            $xml .= '            <ALLLEDGERENTRIES.LIST>'."\n";
            $xml .= '              <LEDGERNAME>TDS Payable</LEDGERNAME>'."\n";
            $xml .= '              <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>'."\n";
            $xml .= '              <AMOUNT>'.number_format($tdsAmount, 2, '.', '').'</AMOUNT>'."\n";
            $xml .= '            </ALLLEDGERENTRIES.LIST>'."\n";
        }

        $partyAmount = $tdsAmount > 0 ? $totalAmount - $tdsAmount : $totalAmount;
        $xml .= '            <ALLLEDGERENTRIES.LIST>'."\n";
        $xml .= '              <LEDGERNAME>'.$this->escapeXml($vendorName).'</LEDGERNAME>'."\n";
        $xml .= '              <ISPARTYLEDGER>Yes</ISPARTYLEDGER>'."\n";
        $xml .= '              <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>'."\n";
        $xml .= '              <AMOUNT>'.number_format($partyAmount, 2, '.', '').'</AMOUNT>'."\n";
        $xml .= '            </ALLLEDGERENTRIES.LIST>'."\n";

        $xml .= '          </VOUCHER>'."\n";
        $xml .= '        </TALLYMESSAGE>'."\n";

        return $xml;
    }

    /**
     * Generate a Sales voucher for an outward (sales) invoice.
     * Multi-leg: customer party debit, sales revenue credit, Output GST credits.
     */
    private function generateSalesVoucher(Transaction $transaction, ?Company $company): string
    {
        /** @var Carbon $transactionDate */
        $transactionDate = $transaction->date;

        /** @var array<string, mixed> $raw */
        $raw = $transaction->raw_data ?? [];
        $invoiceDateRaw = (string) ($raw['invoice_date'] ?? '');
        $date = $invoiceDateRaw !== ''
            ? Carbon::parse($invoiceDateRaw)->format('Ymd')
            : $transactionDate->format('Ymd');

        /** @var AccountHead|null $accountHead */
        $accountHead = $transaction->accountHead;
        $voucherNumber = (string) ($raw['invoice_number'] ?? $this->nextVoucherNumber('Sales'));

        $buyerName = (string) ($raw['buyer_name'] ?? 'Unknown Buyer');
        $buyerGstin = (string) ($raw['buyer_gstin'] ?? '');
        $placeOfSupply = (string) ($raw['place_of_supply'] ?? '');
        $serviceName = (string) ($raw['service_name'] ?? ($accountHead?->name ?? 'Unknown'));
        $hsnSac = (string) ($raw['hsn_sac'] ?? '');
        $narration = $this->buildLineItemNarration($raw)
            ?? (string) ($raw['description'] ?? $transaction->description ?? '');
        $baseAmount = (float) ($raw['base_amount'] ?? 0);
        $cgstRate = $raw['cgst_rate'] ?? null;
        $cgstAmount = (float) ($raw['cgst_amount'] ?? 0);
        $sgstRate = $raw['sgst_rate'] ?? null;
        $sgstAmount = (float) ($raw['sgst_amount'] ?? 0);
        $igstRate = $raw['igst_rate'] ?? null;
        $igstAmount = (float) ($raw['igst_amount'] ?? 0);

        /** @var array<int, string> $buyerAddress */
        $buyerAddress = is_array($raw['buyer_address'] ?? null) ? $raw['buyer_address'] : [];

        $hasIgst = $igstRate !== null && $igstAmount > 0;
        $partyAmount = $hasIgst
            ? $baseAmount + $igstAmount
            : $baseAmount + $cgstAmount + $sgstAmount;

        $xml = '        <TALLYMESSAGE xmlns:UDF="TallyUDF">'."\n";
        $xml .= '          <VOUCHER VCHTYPE="Sales" ACTION="Create" OBJVIEW="Invoice Voucher View">'."\n";
        $xml .= '            <DATE>'.$date.'</DATE>'."\n";
        $xml .= '            <NARRATION>'.$this->escapeXml($narration).'</NARRATION>'."\n";
        $xml .= '            <VOUCHERTYPENAME>Sales</VOUCHERTYPENAME>'."\n";
        $xml .= '            <VOUCHERNUMBER>'.$this->escapeXml($voucherNumber).'</VOUCHERNUMBER>'."\n";
        $xml .= '            <PARTYLEDGERNAME>'.$this->escapeXml($buyerName).'</PARTYLEDGERNAME>'."\n";
        $xml .= '            <PARTYMAILINGNAME>'.$this->escapeXml($buyerName).'</PARTYMAILINGNAME>'."\n";

        if ($buyerGstin !== '') {
            $xml .= '            <PARTYGSTIN>'.$this->escapeXml($buyerGstin).'</PARTYGSTIN>'."\n";
        }

        if ($company) {
            $xml .= '            <CMPGSTIN>'.$this->escapeXml($company->gstin ?? '').'</CMPGSTIN>'."\n";
            $xml .= '            <CMPGSTREGISTRATIONTYPE>'.$this->escapeXml($company->gst_registration_type ?? 'Regular').'</CMPGSTREGISTRATIONTYPE>'."\n";
            $xml .= '            <CMPGSTSTATE>'.$this->escapeXml($company->state ?? '').'</CMPGSTSTATE>'."\n";
        }

        $xml .= '            <GSTREGISTRATIONTYPE>Regular</GSTREGISTRATIONTYPE>'."\n";

        if ($placeOfSupply !== '') {
            $xml .= '            <STATENAME>'.$this->escapeXml($placeOfSupply).'</STATENAME>'."\n";
            $xml .= '            <PLACEOFSUPPLY>'.$this->escapeXml($placeOfSupply).'</PLACEOFSUPPLY>'."\n";
        }

        $xml .= '            <ISINVOICE>Yes</ISINVOICE>'."\n";
        $xml .= '            <VCHENTRYMORE>Accounting Invoice</VCHENTRYMORE>'."\n";
        $xml .= '            <NUMBERINGSTYLE>Manual</NUMBERINGSTYLE>'."\n";
        $xml .= '            <EFFECTIVEDATE>'.$date.'</EFFECTIVEDATE>'."\n";
        $xml .= '            <ISREVERSCHARGEAPPLICABLE>No</ISREVERSCHARGEAPPLICABLE>'."\n";
        $xml .= '            <ISELIGIBLEFORLITC>Yes</ISELIGIBLEFORLITC>'."\n";

        if ($buyerAddress !== []) {
            $xml .= '            <ADDRESS.LIST TYPE="String">'."\n";
            foreach ($buyerAddress as $line) {
                $xml .= '              <ADDRESS>'.$this->escapeXml($line).'</ADDRESS>'."\n";
            }
            $xml .= '            </ADDRESS.LIST>'."\n";
        }

        $xml .= '            <LEDGERENTRIES.LIST>'."\n";
        $xml .= '              <LEDGERNAME>'.$this->escapeXml($buyerName).'</LEDGERNAME>'."\n";
        $xml .= '              <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>'."\n";
        $xml .= '              <ISPARTYLEDGER>Yes</ISPARTYLEDGER>'."\n";
        $xml .= '              <AMOUNT>-'.number_format($partyAmount, 2, '.', '').'</AMOUNT>'."\n";
        $xml .= '            </LEDGERENTRIES.LIST>'."\n";

        $xml .= '            <LEDGERENTRIES.LIST>'."\n";
        $xml .= '              <LEDGERNAME>'.$this->escapeXml($serviceName).'</LEDGERNAME>'."\n";
        $xml .= '              <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>'."\n";
        $xml .= '              <ISPARTYLEDGER>No</ISPARTYLEDGER>'."\n";
        $xml .= '              <AMOUNT>'.number_format($baseAmount, 2, '.', '').'</AMOUNT>'."\n";

        if ($hsnSac !== '') {
            $xml .= '              <GSTHSNNAME>'.$this->escapeXml($hsnSac).'</GSTHSNNAME>'."\n";
        }

        $xml .= '              <GSTOVRDNTAXABILITY>Taxable</GSTOVRDNTAXABILITY>'."\n";
        $xml .= '              <GSTOVRDNTYPEOFSUPPLY>Services</GSTOVRDNTYPEOFSUPPLY>'."\n";

        if ($hasIgst) {
            $xml .= '              <RATEDETAILS.LIST>'."\n";
            $xml .= '                <GSTRATEDUTYHEAD>IGST</GSTRATEDUTYHEAD>'."\n";
            $xml .= '                <GSTRATEVALUATIONTYPE>Based on Value</GSTRATEVALUATIONTYPE>'."\n";
            $xml .= '                <GSTRATE>'.$igstRate.'</GSTRATE>'."\n";
            $xml .= '              </RATEDETAILS.LIST>'."\n";
        } elseif ($cgstRate !== null && $sgstRate !== null) {
            $xml .= '              <RATEDETAILS.LIST>'."\n";
            $xml .= '                <GSTRATEDUTYHEAD>CGST</GSTRATEDUTYHEAD>'."\n";
            $xml .= '                <GSTRATEVALUATIONTYPE>Based on Value</GSTRATEVALUATIONTYPE>'."\n";
            $xml .= '                <GSTRATE>'.$cgstRate.'</GSTRATE>'."\n";
            $xml .= '              </RATEDETAILS.LIST>'."\n";
            $xml .= '              <RATEDETAILS.LIST>'."\n";
            $xml .= '                <GSTRATEDUTYHEAD>SGST/UTGST</GSTRATEDUTYHEAD>'."\n";
            $xml .= '                <GSTRATEVALUATIONTYPE>Based on Value</GSTRATEVALUATIONTYPE>'."\n";
            $xml .= '                <GSTRATE>'.$sgstRate.'</GSTRATE>'."\n";
            $xml .= '              </RATEDETAILS.LIST>'."\n";
        }

        $xml .= '            </LEDGERENTRIES.LIST>'."\n";

        if ($hasIgst) {
            $xml .= $this->generateOutputTaxLedgerEntry("Output Igst @ {$igstRate}%", $igstRate, $igstAmount);
        } else {
            if ($cgstRate !== null && $cgstAmount > 0) {
                $xml .= $this->generateOutputTaxLedgerEntry("Output Cgst @ {$cgstRate}%", $cgstRate, $cgstAmount);
            }

            if ($sgstRate !== null && $sgstAmount > 0) {
                $xml .= $this->generateOutputTaxLedgerEntry("Output Sgst @ {$sgstRate}%", $sgstRate, $sgstAmount);
            }
        }

        $xml .= '          </VOUCHER>'."\n";
        $xml .= '        </TALLYMESSAGE>'."\n";

        return $xml;
    }

    private function generateOutputTaxLedgerEntry(string $ledgerName, float|int $rate, float $amount): string
    {
        $xml = '            <LEDGERENTRIES.LIST>'."\n";
        $xml .= '              <LEDGERNAME>'.$this->escapeXml($ledgerName).'</LEDGERNAME>'."\n";
        $xml .= '              <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>'."\n";
        $xml .= '              <ISPARTYLEDGER>No</ISPARTYLEDGER>'."\n";
        $xml .= '              <RATEOFINVOICETAX.LIST TYPE="Number">'."\n";
        $xml .= '                <RATEOFINVOICETAX>'.$rate.'</RATEOFINVOICETAX>'."\n";
        $xml .= '              </RATEOFINVOICETAX.LIST>'."\n";
        $xml .= '              <AMOUNT>'.number_format($amount, 2, '.', '').'</AMOUNT>'."\n";
        $xml .= '            </LEDGERENTRIES.LIST>'."\n";

        return $xml;
    }

    private function generateExpenseLedgerEntry(string $headName, float $baseAmount, mixed $cgstRate, mixed $sgstRate): string
    {
        $xml = '            <ALLLEDGERENTRIES.LIST>'."\n";
        $xml .= '              <LEDGERNAME>'.$this->escapeXml($headName).'</LEDGERNAME>'."\n";
        $xml .= '              <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>'."\n";
        $xml .= '              <AMOUNT>-'.number_format($baseAmount, 2, '.', '').'</AMOUNT>'."\n";

        if ($cgstRate !== null && $sgstRate !== null) {
            $xml .= '              <RATEDETAILS.LIST>'."\n";
            $xml .= '                <GSTRATEDUTYHEAD>CGST</GSTRATEDUTYHEAD>'."\n";
            $xml .= '                <GSTRATEVALUATIONTYPE>Based on Value</GSTRATEVALUATIONTYPE>'."\n";
            $xml .= '              </RATEDETAILS.LIST>'."\n";
            $xml .= '              <RATEDETAILS.LIST>'."\n";
            $xml .= '                <GSTRATEDUTYHEAD>SGST/UTGST</GSTRATEDUTYHEAD>'."\n";
            $xml .= '                <GSTRATEVALUATIONTYPE>Based on Value</GSTRATEVALUATIONTYPE>'."\n";
            $xml .= '              </RATEDETAILS.LIST>'."\n";
        }

        $xml .= '            </ALLLEDGERENTRIES.LIST>'."\n";

        return $xml;
    }

    private function generateGstLedgerEntries(mixed $igstRate, float $igstAmount, mixed $cgstRate, float $cgstAmount, mixed $sgstRate, float $sgstAmount): string
    {
        if ($igstRate !== null && $igstAmount > 0) {
            return $this->generateTaxLedgerEntry("Input Igst @ {$igstRate}%", $igstAmount);
        }

        $xml = '';

        if ($cgstRate !== null && $cgstAmount > 0) {
            $xml .= $this->generateTaxLedgerEntry("Input Cgst @ {$cgstRate}%", $cgstAmount);
        }

        if ($sgstRate !== null && $sgstAmount > 0) {
            $xml .= $this->generateTaxLedgerEntry("Input Sgst @ {$sgstRate}%", $sgstAmount);
        }

        return $xml;
    }

    private function generateTaxLedgerEntry(string $ledgerName, float $amount): string
    {
        $xml = '            <ALLLEDGERENTRIES.LIST>'."\n";
        $xml .= '              <LEDGERNAME>'.$this->escapeXml($ledgerName).'</LEDGERNAME>'."\n";
        $xml .= '              <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>'."\n";
        $xml .= '              <AMOUNT>-'.number_format($amount, 2, '.', '').'</AMOUNT>'."\n";
        $xml .= '            </ALLLEDGERENTRIES.LIST>'."\n";

        return $xml;
    }

    /**
     * Generate the two-leg journal entry for a bank/CC transaction.
     * Debit: ISDEEMEDPOSITIVE=Yes, negative amount. Credit: ISDEEMEDPOSITIVE=No, positive amount.
     * Both legs carry ISPARTYLEDGER=Yes — no BANKALLOCATIONS.LIST.
     */
    private function generateBankJournalLedgerEntries(string $headName, string $bankName, float $amount, bool $isDebit): string
    {
        $formattedAmount = number_format($amount, 2, '.', '');

        [$debitLedger, $creditLedger] = $isDebit
            ? [$headName, $bankName]
            : [$bankName, $headName];

        $xml = '';

        $xml .= '            <ALLLEDGERENTRIES.LIST>'."\n";
        $xml .= '              <LEDGERNAME>'.$this->escapeXml($debitLedger).'</LEDGERNAME>'."\n";
        $xml .= '              <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>'."\n";
        $xml .= '              <ISPARTYLEDGER>Yes</ISPARTYLEDGER>'."\n";
        $xml .= '              <AMOUNT>-'.$formattedAmount.'</AMOUNT>'."\n";
        $xml .= '            </ALLLEDGERENTRIES.LIST>'."\n";

        $xml .= '            <ALLLEDGERENTRIES.LIST>'."\n";
        $xml .= '              <LEDGERNAME>'.$this->escapeXml($creditLedger).'</LEDGERNAME>'."\n";
        $xml .= '              <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>'."\n";
        $xml .= '              <ISPARTYLEDGER>Yes</ISPARTYLEDGER>'."\n";
        $xml .= '              <AMOUNT>'.$formattedAmount.'</AMOUNT>'."\n";
        $xml .= '            </ALLLEDGERENTRIES.LIST>'."\n";

        return $xml;
    }

    /**
     * Generate the company identity footer block.
     */
    private function generateCompanyFooter(Company $company): string
    {
        $xml = '        <TALLYMESSAGE xmlns:UDF="TallyUDF">'."\n";
        $xml .= '          <COMPANY>'."\n";
        $xml .= '            <REMOTECMPINFO.LIST MERGE="Yes">'."\n";
        $xml .= '              <NAME>'.$this->escapeXml($company->gstin ?? '').'</NAME>'."\n";
        $xml .= '              <REMOTECMPNAME>'.$this->escapeXml($company->name ?? '').'</REMOTECMPNAME>'."\n";
        $xml .= '              <REMOTECMPSTATE>'.$this->escapeXml($company->state ?? '').'</REMOTECMPSTATE>'."\n";
        $xml .= '            </REMOTECMPINFO.LIST>'."\n";
        $xml .= '          </COMPANY>'."\n";
        $xml .= '        </TALLYMESSAGE>'."\n";

        return $xml;
    }

    /**
     * Only purchase invoice Journal exports use REPORTNAME=All Masters.
     * Sales invoices and bank/CC journal exports both use Vouchers.
     *
     * @param  Collection<int, Transaction>  $transactions
     */
    private function isAllMastersExport(Collection $transactions, ?ImportedFile $importedFile): bool
    {
        if ($importedFile?->statement_type !== StatementType::Invoice) {
            return false;
        }

        /** @var Transaction|null $first */
        $first = $transactions->first();

        if ($first === null) {
            return false;
        }

        /** @var array<string, mixed> $raw */
        $raw = $first->raw_data ?? [];

        return ! isset($raw['buyer_name']);
    }

    /**
     * Get the next sequential voucher number for a given type.
     */
    private function nextVoucherNumber(string $voucherType): int
    {
        if (! isset($this->voucherCounters[$voucherType])) {
            $this->voucherCounters[$voucherType] = 0;
        }

        return ++$this->voucherCounters[$voucherType];
    }

    /** @param array<string, mixed> $raw */
    private function buildLineItemNarration(array $raw): ?string
    {
        $lineItems = is_array($raw['line_items'] ?? null) ? $raw['line_items'] : [];

        if (empty($lineItems)) {
            return null;
        }

        $descriptions = array_filter(array_column($lineItems, 'description'));

        return empty($descriptions) ? null : implode("\n", $descriptions);
    }

    private function escapeXml(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
