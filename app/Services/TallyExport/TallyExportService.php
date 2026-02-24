<?php

namespace App\Services\TallyExport;

use App\Models\ImportedFile;
use App\Models\Transaction;
use Illuminate\Support\Collection;

class TallyExportService
{
    /**
     * Generate Tally-compatible XML for transactions in an imported file.
     *
     * Note: XML format is a placeholder. Will be updated when Tally XML reference is provided.
     */
    public function exportForFile(ImportedFile $importedFile): string
    {
        $transactions = $importedFile->transactions()
            ->whereNotNull('account_head_id')
            ->with('accountHead')
            ->orderBy('date')
            ->get();

        return $this->generateXml($transactions, $importedFile);
    }

    /**
     * Export selected transactions to Tally XML.
     *
     * @param  Collection<int, Transaction>  $transactions
     */
    public function exportTransactions(Collection $transactions): string
    {
        $importedFile = $transactions->first()?->importedFile;

        return $this->generateXml($transactions, $importedFile);
    }

    /**
     * Generate the Tally XML structure.
     *
     * TODO: Replace with actual Tally XML format when reference file is provided.
     */
    protected function generateXml(Collection $transactions, ?ImportedFile $importedFile): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
        $xml .= '<ENVELOPE>'."\n";
        $xml .= '  <HEADER>'."\n";
        $xml .= '    <TALLYREQUEST>Import Data</TALLYREQUEST>'."\n";
        $xml .= '  </HEADER>'."\n";
        $xml .= '  <BODY>'."\n";
        $xml .= '    <IMPORTDATA>'."\n";
        $xml .= '      <REQUESTDESC>'."\n";
        $xml .= '        <REPORTNAME>Vouchers</REPORTNAME>'."\n";
        $xml .= '      </REQUESTDESC>'."\n";
        $xml .= '      <REQUESTDATA>'."\n";

        foreach ($transactions as $transaction) {
            $xml .= $this->generateVoucher($transaction);
        }

        $xml .= '      </REQUESTDATA>'."\n";
        $xml .= '    </IMPORTDATA>'."\n";
        $xml .= '  </BODY>'."\n";
        $xml .= '</ENVELOPE>';

        return $xml;
    }

    /**
     * Generate a single Tally voucher XML element.
     *
     * TODO: Replace with actual voucher format from Tally XML reference.
     */
    protected function generateVoucher(Transaction $transaction): string
    {
        $date = $transaction->date->format('Ymd');
        $headName = htmlspecialchars($transaction->accountHead?->name ?? 'Unknown', ENT_XML1);
        $amount = htmlspecialchars((string) ($transaction->debit ?? $transaction->credit ?? '0'), ENT_XML1);
        $voucherType = $transaction->debit ? 'Payment' : 'Receipt';
        $description = htmlspecialchars($transaction->description ?? '', ENT_XML1);

        $xml = '        <TALLYMESSAGE xmlns:UDF="TallyUDF">'."\n";
        $xml .= '          <VOUCHER VCHTYPE="'.$voucherType.'" ACTION="Create">'."\n";
        $xml .= '            <DATE>'.$date.'</DATE>'."\n";
        $xml .= '            <NARRATION>'.$description.'</NARRATION>'."\n";
        $xml .= '            <VOUCHERTYPENAME>'.$voucherType.'</VOUCHERTYPENAME>'."\n";
        $xml .= '            <PARTYLEDGERNAME>'.$headName.'</PARTYLEDGERNAME>'."\n";
        $xml .= '            <ALLLEDGERENTRIES.LIST>'."\n";
        $xml .= '              <LEDGERNAME>'.$headName.'</LEDGERNAME>'."\n";
        $xml .= '              <AMOUNT>'.$amount.'</AMOUNT>'."\n";
        $xml .= '            </ALLLEDGERENTRIES.LIST>'."\n";
        $xml .= '          </VOUCHER>'."\n";
        $xml .= '        </TALLYMESSAGE>'."\n";

        return $xml;
    }
}
