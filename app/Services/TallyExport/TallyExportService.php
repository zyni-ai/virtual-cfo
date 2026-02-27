<?php

namespace App\Services\TallyExport;

use App\Models\Company;
use App\Models\ImportedFile;
use App\Models\Transaction;
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

        /** @var \Illuminate\Support\Collection<int, Transaction> $transactions */
        $transactions = $importedFile->transactions()
            ->whereNotNull('account_head_id')
            ->with('accountHead')
            ->orderBy('date')
            ->get();

        return $this->generateXml($transactions, $importedFile->company, $importedFile->bankAccount?->name);
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
        );
    }

    /**
     * Generate the complete Tally XML envelope.
     *
     * @param  Collection<int, Transaction>  $transactions
     */
    private function generateXml(Collection $transactions, ?Company $company, ?string $bankLedgerName): string
    {
        $this->voucherCounters = [];
        $companyName = $company?->name ?? '';
        $e = fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<ENVELOPE>'."\n";
        $xml .= '  <HEADER>'."\n";
        $xml .= '    <TALLYREQUEST>Import Data</TALLYREQUEST>'."\n";
        $xml .= '  </HEADER>'."\n";
        $xml .= '  <BODY>'."\n";
        $xml .= '    <IMPORTDATA>'."\n";
        $xml .= '      <REQUESTDESC>'."\n";
        $xml .= '        <REPORTNAME>All Masters</REPORTNAME>'."\n";
        $xml .= '        <STATICVARIABLES>'."\n";
        $xml .= '          <SVCURRENTCOMPANY>'.$e($companyName).'</SVCURRENTCOMPANY>'."\n";
        $xml .= '        </STATICVARIABLES>'."\n";
        $xml .= '      </REQUESTDESC>'."\n";
        $xml .= '      <REQUESTDATA>'."\n";

        foreach ($transactions as $transaction) {
            $xml .= $this->generateVoucher($transaction, $company, $bankLedgerName);
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
     * Generate a single Tally voucher XML element (Payment or Receipt).
     */
    private function generateVoucher(Transaction $transaction, ?Company $company, ?string $bankLedgerName): string
    {
        $isPayment = $transaction->debit !== null;
        $voucherType = $isPayment ? 'Payment' : 'Receipt';
        $amount = (float) ($isPayment ? $transaction->debit : $transaction->credit);
        /** @var \Illuminate\Support\Carbon $transactionDate */
        $transactionDate = $transaction->date;
        $date = $transactionDate->format('Ymd');
        /** @var \App\Models\AccountHead|null $accountHead */
        $accountHead = $transaction->accountHead;
        $headName = $accountHead?->name ?? 'Unknown';
        $bankName = $bankLedgerName ?? 'Bank Account';
        $voucherNumber = $this->nextVoucherNumber($voucherType);

        $e = fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = '        <TALLYMESSAGE xmlns:UDF="TallyUDF">'."\n";
        $xml .= '          <VOUCHER VCHTYPE="'.$e($voucherType).'" ACTION="Create" OBJVIEW="Accounting Voucher View">'."\n";
        $xml .= '            <DATE>'.$date.'</DATE>'."\n";
        $xml .= '            <VCHSTATUSDATE>'.$date.'</VCHSTATUSDATE>'."\n";
        $xml .= '            <NARRATION>'.$e($transaction->description ?? '').'</NARRATION>'."\n";
        $xml .= '            <VOUCHERTYPENAME>'.$e($voucherType).'</VOUCHERTYPENAME>'."\n";
        $xml .= '            <VOUCHERNUMBER>'.$voucherNumber.'</VOUCHERNUMBER>'."\n";
        $xml .= '            <PARTYLEDGERNAME>'.$e($headName).'</PARTYLEDGERNAME>'."\n";

        if ($company) {
            $xml .= '            <CMPGSTIN>'.$e($company->gstin ?? '').'</CMPGSTIN>'."\n";
            $xml .= '            <CMPGSTREGISTRATIONTYPE>'.$e($company->gst_registration_type ?? 'Regular').'</CMPGSTREGISTRATIONTYPE>'."\n";
            $xml .= '            <CMPGSTSTATE>'.$e($company->state ?? '').'</CMPGSTSTATE>'."\n";
        }

        $xml .= '            <EFFECTIVEDATE>'.$date.'</EFFECTIVEDATE>'."\n";

        // Boilerplate boolean flags
        $xml .= '            <ISDELETED>No</ISDELETED>'."\n";
        $xml .= '            <ISCANCELLED>No</ISCANCELLED>'."\n";
        $xml .= '            <ISONHOLD>No</ISONHOLD>'."\n";
        $xml .= '            <ISOPTIONAL>No</ISOPTIONAL>'."\n";
        $xml .= '            <AUDITED>No</AUDITED>'."\n";
        $xml .= '            <HASCASHFLOW>Yes</HASCASHFLOW>'."\n";

        // Ledger entries
        if ($isPayment) {
            $xml .= $this->generatePaymentLedgerEntries($headName, $bankName, $amount, $date, $e);
        } else {
            $xml .= $this->generateReceiptLedgerEntries($headName, $bankName, $amount, $date, $e);
        }

        $xml .= '          </VOUCHER>'."\n";
        $xml .= '        </TALLYMESSAGE>'."\n";

        return $xml;
    }

    /**
     * Generate ledger entries for a Payment voucher.
     * Debit: expense/party (negative amount, ISDEEMEDPOSITIVE=Yes)
     * Credit: bank (positive amount, ISDEEMEDPOSITIVE=No)
     *
     * @param  \Closure(string): string  $e
     */
    private function generatePaymentLedgerEntries(string $headName, string $bankName, float $amount, string $date, \Closure $e): string
    {
        $formattedAmount = number_format($amount, 2, '.', '');

        $xml = '';

        // Debit leg: Expense/Party
        $xml .= '            <ALLLEDGERENTRIES.LIST>'."\n";
        $xml .= '              <LEDGERNAME>'.$e($headName).'</LEDGERNAME>'."\n";
        $xml .= '              <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>'."\n";
        $xml .= '              <AMOUNT>-'.$formattedAmount.'</AMOUNT>'."\n";
        $xml .= '            </ALLLEDGERENTRIES.LIST>'."\n";

        // Credit leg: Bank
        $xml .= '            <ALLLEDGERENTRIES.LIST>'."\n";
        $xml .= '              <LEDGERNAME>'.$e($bankName).'</LEDGERNAME>'."\n";
        $xml .= '              <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>'."\n";
        $xml .= '              <AMOUNT>'.$formattedAmount.'</AMOUNT>'."\n";
        $xml .= '              <BANKALLOCATIONS.LIST>'."\n";
        $xml .= '                <DATE>'.$date.'</DATE>'."\n";
        $xml .= '                <INSTRUMENTDATE>'.$date.'</INSTRUMENTDATE>'."\n";
        $xml .= '                <BANKERSDATE>'.$date.'</BANKERSDATE>'."\n";
        $xml .= '                <TRANSACTIONTYPE>Cheque</TRANSACTIONTYPE>'."\n";
        $xml .= '                <AMOUNT>'.$formattedAmount.'</AMOUNT>'."\n";
        $xml .= '              </BANKALLOCATIONS.LIST>'."\n";
        $xml .= '            </ALLLEDGERENTRIES.LIST>'."\n";

        return $xml;
    }

    /**
     * Generate ledger entries for a Receipt voucher.
     * Debit: bank (negative amount, ISDEEMEDPOSITIVE=Yes)
     * Credit: party/income (positive amount, ISDEEMEDPOSITIVE=No)
     *
     * @param  \Closure(string): string  $e
     */
    private function generateReceiptLedgerEntries(string $headName, string $bankName, float $amount, string $date, \Closure $e): string
    {
        $formattedAmount = number_format($amount, 2, '.', '');

        $xml = '';

        // Debit leg: Bank
        $xml .= '            <ALLLEDGERENTRIES.LIST>'."\n";
        $xml .= '              <LEDGERNAME>'.$e($bankName).'</LEDGERNAME>'."\n";
        $xml .= '              <ISDEEMEDPOSITIVE>Yes</ISDEEMEDPOSITIVE>'."\n";
        $xml .= '              <AMOUNT>-'.$formattedAmount.'</AMOUNT>'."\n";
        $xml .= '              <BANKALLOCATIONS.LIST>'."\n";
        $xml .= '                <DATE>'.$date.'</DATE>'."\n";
        $xml .= '                <INSTRUMENTDATE>'.$date.'</INSTRUMENTDATE>'."\n";
        $xml .= '                <BANKERSDATE>'.$date.'</BANKERSDATE>'."\n";
        $xml .= '                <TRANSACTIONTYPE>Cheque</TRANSACTIONTYPE>'."\n";
        $xml .= '                <AMOUNT>-'.$formattedAmount.'</AMOUNT>'."\n";
        $xml .= '              </BANKALLOCATIONS.LIST>'."\n";
        $xml .= '            </ALLLEDGERENTRIES.LIST>'."\n";

        // Credit leg: Party/Income
        $xml .= '            <ALLLEDGERENTRIES.LIST>'."\n";
        $xml .= '              <LEDGERNAME>'.$e($headName).'</LEDGERNAME>'."\n";
        $xml .= '              <ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>'."\n";
        $xml .= '              <AMOUNT>'.$formattedAmount.'</AMOUNT>'."\n";
        $xml .= '            </ALLLEDGERENTRIES.LIST>'."\n";

        return $xml;
    }

    /**
     * Generate the company identity footer block.
     */
    private function generateCompanyFooter(Company $company): string
    {
        $e = fn (string $value): string => htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        $xml = '        <TALLYMESSAGE xmlns:UDF="TallyUDF">'."\n";
        $xml .= '          <COMPANY>'."\n";
        $xml .= '            <REMOTECMPINFO.LIST MERGE="Yes">'."\n";
        $xml .= '              <NAME>'.$e($company->gstin ?? '').'</NAME>'."\n";
        $xml .= '              <REMOTECMPNAME>'.$e($company->name ?? '').'</REMOTECMPNAME>'."\n";
        $xml .= '              <REMOTECMPSTATE>'.$e($company->state ?? '').'</REMOTECMPSTATE>'."\n";
        $xml .= '            </REMOTECMPINFO.LIST>'."\n";
        $xml .= '          </COMPANY>'."\n";
        $xml .= '        </TALLYMESSAGE>'."\n";

        return $xml;
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
}
