<?php

use App\Models\AccountHead;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\ImportedFile;
use App\Models\Transaction;
use App\Services\TallyExport\TallyExportService;

describe('TallyExportService multi-file export', function () {
    beforeEach(function () {
        $this->company = Company::factory()->knownDefaults()->create();
        $this->service = new TallyExportService;
    });

    it('resolves bank ledger name per transaction from its own imported file', function () {
        $iciciAccount = BankAccount::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'ICICI Current Account',
        ]);
        $hdfcAccount = BankAccount::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'HDFC Savings Account',
        ]);

        $iciciFile = ImportedFile::factory()->create([
            'company_id' => $this->company->id,
            'bank_account_id' => $iciciAccount->id,
        ]);
        $hdfcFile = ImportedFile::factory()->create([
            'company_id' => $this->company->id,
            'bank_account_id' => $hdfcAccount->id,
        ]);

        $head = AccountHead::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Software Expense',
        ]);

        $iciciTxn = Transaction::factory()->mapped($head)->debit(5000)->for($iciciFile)->create([
            'company_id' => $this->company->id,
            'date' => '2025-01-15',
        ]);
        $hdfcTxn = Transaction::factory()->mapped($head)->debit(3000)->for($hdfcFile)->create([
            'company_id' => $this->company->id,
            'date' => '2025-01-16',
        ]);

        $transactions = Transaction::whereIn('id', [$iciciTxn->id, $hdfcTxn->id])
            ->with(['accountHead', 'importedFile.bankAccount'])
            ->orderBy('date')
            ->get();

        $xml = $this->service->exportTransactions($transactions);

        expect($xml)
            ->toContain('<LEDGERNAME>ICICI Current Account</LEDGERNAME>')
            ->toContain('<LEDGERNAME>HDFC Savings Account</LEDGERNAME>');
    });

    it('does not bleed one file bank account name into another file transactions', function () {
        $iciciAccount = BankAccount::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'ICICI Current Account',
        ]);
        $hdfcAccount = BankAccount::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'HDFC Savings Account',
        ]);

        $iciciFile = ImportedFile::factory()->create([
            'company_id' => $this->company->id,
            'bank_account_id' => $iciciAccount->id,
        ]);
        $hdfcFile = ImportedFile::factory()->create([
            'company_id' => $this->company->id,
            'bank_account_id' => $hdfcAccount->id,
        ]);

        $head = AccountHead::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Office Expense',
        ]);

        $iciciTxn = Transaction::factory()->mapped($head)->debit(1000)->for($iciciFile)->create([
            'company_id' => $this->company->id,
            'date' => '2025-02-01',
        ]);
        $hdfcTxn = Transaction::factory()->mapped($head)->debit(2000)->for($hdfcFile)->create([
            'company_id' => $this->company->id,
            'date' => '2025-02-02',
        ]);

        $transactions = Transaction::whereIn('id', [$iciciTxn->id, $hdfcTxn->id])
            ->with(['accountHead', 'importedFile.bankAccount'])
            ->orderBy('date')
            ->get();

        $xml = $this->service->exportTransactions($transactions);

        // Expect both bank accounts to appear, and ICICI should not bleed into HDFC voucher
        expect(substr_count($xml, '<LEDGERNAME>ICICI Current Account</LEDGERNAME>'))->toBe(1);
        expect(substr_count($xml, '<LEDGERNAME>HDFC Savings Account</LEDGERNAME>'))->toBe(1);
    });

    it('returns valid XML envelope for empty collection', function () {
        $xml = $this->service->exportTransactions(collect());

        expect($xml)
            ->toContain('<ENVELOPE>')
            ->toContain('<REQUESTDATA>')
            ->not->toContain('<TALLYMESSAGE');
    });

    it('still resolves bank ledger correctly for single-file export via exportTransactions', function () {
        $bankAccount = BankAccount::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Axis Bank Current',
        ]);
        $file = ImportedFile::factory()->create([
            'company_id' => $this->company->id,
            'bank_account_id' => $bankAccount->id,
        ]);
        $head = AccountHead::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Travel Expense',
        ]);

        $txn = Transaction::factory()->mapped($head)->debit(500)->for($file)->create([
            'company_id' => $this->company->id,
            'date' => '2025-03-10',
        ]);

        $transactions = Transaction::where('id', $txn->id)
            ->with(['accountHead', 'importedFile.bankAccount'])
            ->get();

        $xml = $this->service->exportTransactions($transactions);

        expect($xml)->toContain('<LEDGERNAME>Axis Bank Current</LEDGERNAME>');
    });
});
