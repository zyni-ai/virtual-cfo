<?php

use App\Models\AccountHead;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\ImportedFile;
use App\Models\Transaction;
use App\Services\TallyExport\TallyExportService;

describe('TallyExportService reconciled journal vouchers', function () {
    beforeEach(function () {
        $this->company = Company::factory()->knownDefaults()->create();
        $this->bankAccount = BankAccount::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Amazon ICICI Credit Card',
        ]);
        $this->file = ImportedFile::factory()->create([
            'company_id' => $this->company->id,
            'bank_account_id' => $this->bankAccount->id,
        ]);
        $this->service = new TallyExportService;
    });

    describe('when vendor_name is present in raw_data', function () {
        beforeEach(function () {
            $head = AccountHead::factory()->create([
                'company_id' => $this->company->id,
                'name' => 'Internet Expense',
            ]);
            Transaction::factory()->mapped($head)->debit(299.00)->for($this->file)
                ->withRawData(['vendor_name' => 'M/S Reliance Jio Infocomm Limited'])
                ->create([
                    'company_id' => $this->company->id,
                    'description' => 'RELIANCE RETAIL LIMITE NOIDA IN',
                    'date' => '2026-03-17',
                ]);
        });

        it('uses vendor_name as debit ledger instead of account head', function () {
            $xml = $this->service->exportForFile($this->file);

            expect($xml)
                ->toContain('<LEDGERNAME>M/S Reliance Jio Infocomm Limited</LEDGERNAME>')
                ->not->toContain('<LEDGERNAME>Internet Expense</LEDGERNAME>');
        });

        it('sets PARTYLEDGERNAME to vendor_name', function () {
            $xml = $this->service->exportForFile($this->file);

            expect($xml)->toContain('<PARTYLEDGERNAME>M/S Reliance Jio Infocomm Limited</PARTYLEDGERNAME>');
        });

        it('still uses CC account as credit ledger', function () {
            $xml = $this->service->exportForFile($this->file);

            expect($xml)
                ->toContain('<LEDGERNAME>Amazon ICICI Credit Card</LEDGERNAME>')
                ->toContain('<ISDEEMEDPOSITIVE>No</ISDEEMEDPOSITIVE>');
        });

        it('generates correct debit and credit amounts', function () {
            $xml = $this->service->exportForFile($this->file);

            expect($xml)
                ->toContain('<AMOUNT>-299.00</AMOUNT>')
                ->toContain('<AMOUNT>299.00</AMOUNT>');
        });

        it('still exports as Journal voucher', function () {
            $xml = $this->service->exportForFile($this->file);

            expect($xml)
                ->toContain('VCHTYPE="Journal"')
                ->toContain('<VOUCHERTYPENAME>Journal</VOUCHERTYPENAME>')
                ->toContain('<HASCASHFLOW>No</HASCASHFLOW>');
        });
    });

    describe('when vendor_name is absent from raw_data', function () {
        beforeEach(function () {
            $head = AccountHead::factory()->create([
                'company_id' => $this->company->id,
                'name' => 'Internet Expense',
            ]);
            Transaction::factory()->mapped($head)->debit(299.00)->for($this->file)->create([
                'company_id' => $this->company->id,
                'date' => '2026-03-17',
            ]);
        });

        it('uses account head as debit ledger', function () {
            $xml = $this->service->exportForFile($this->file);

            expect($xml)->toContain('<LEDGERNAME>Internet Expense</LEDGERNAME>');
        });

        it('does not include PARTYLEDGERNAME', function () {
            $xml = $this->service->exportForFile($this->file);

            expect($xml)->not->toContain('<PARTYLEDGERNAME>');
        });
    });
});
