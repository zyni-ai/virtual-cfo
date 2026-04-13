<?php

use App\Enums\DuplicateConfidence;
use App\Enums\DuplicateStatus;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\DuplicateFlag;
use App\Models\ImportedFile;
use App\Models\Transaction;
use App\Services\DuplicateDetectionService;

describe('DuplicateDetectionService', function () {
    beforeEach(function () {
        asUser();
        $this->company = tenant();
        $this->bankAccount = BankAccount::factory()->for($this->company)->create();
        $this->service = app(DuplicateDetectionService::class);
    });

    describe('scanning for duplicates', function () {
        it('flags transactions with same reference number across files as high confidence', function () {
            $file1 = ImportedFile::factory()->for($this->company)->create([
                'bank_account_id' => $this->bankAccount->id,
            ]);
            $file2 = ImportedFile::factory()->for($this->company)->create([
                'bank_account_id' => $this->bankAccount->id,
            ]);

            Transaction::factory()->for($file1, 'importedFile')->create([
                'company_id' => $this->company->id,
                'date' => '2026-02-15',
                'reference_number' => 'REF123456',
                'debit' => 5000.00,
                'description' => 'NEFT-123456-Test Company',
            ]);

            Transaction::factory()->for($file2, 'importedFile')->create([
                'company_id' => $this->company->id,
                'date' => '2026-02-15',
                'reference_number' => 'REF123456',
                'debit' => 5000.00,
                'description' => 'NEFT-123456-Test Company',
            ]);

            $flags = $this->service->scanForDuplicates($file2);

            expect($flags)->toHaveCount(1)
                ->and($flags->first()->confidence)->toBe(DuplicateConfidence::High)
                ->and($flags->first()->match_reasons)->toContain('reference_number');
        });

        it('flags transactions with same amount date and similar description as medium confidence', function () {
            $file1 = ImportedFile::factory()->for($this->company)->create([
                'bank_account_id' => $this->bankAccount->id,
            ]);
            $file2 = ImportedFile::factory()->for($this->company)->create([
                'bank_account_id' => $this->bankAccount->id,
            ]);

            Transaction::factory()->for($file1, 'importedFile')->create([
                'company_id' => $this->company->id,
                'date' => '2026-02-15',
                'reference_number' => null,
                'debit' => 5000.00,
                'description' => 'NEFT Payment to Vendor ABC',
            ]);

            Transaction::factory()->for($file2, 'importedFile')->create([
                'company_id' => $this->company->id,
                'date' => '2026-02-15',
                'reference_number' => null,
                'debit' => 5000.00,
                'description' => 'NEFT Payment to Vendor ABC Corp',
            ]);

            $flags = $this->service->scanForDuplicates($file2);

            expect($flags)->toHaveCount(1)
                ->and($flags->first()->confidence)->toBe(DuplicateConfidence::Medium)
                ->and($flags->first()->match_reasons)->toContain('description_similarity');
        });

        it('does not flag transactions from the same file', function () {
            $file = ImportedFile::factory()->for($this->company)->create([
                'bank_account_id' => $this->bankAccount->id,
            ]);

            Transaction::factory()->for($file, 'importedFile')->count(2)->create([
                'company_id' => $this->company->id,
                'date' => '2026-02-15',
                'reference_number' => 'REF123456',
                'debit' => 5000.00,
                'description' => 'Same transaction',
            ]);

            $flags = $this->service->scanForDuplicates($file);

            expect($flags)->toHaveCount(0);
        });

        it('does not flag transactions from different bank accounts', function () {
            $otherBankAccount = BankAccount::factory()->for($this->company)->create();

            $file1 = ImportedFile::factory()->for($this->company)->create([
                'bank_account_id' => $this->bankAccount->id,
            ]);
            $file2 = ImportedFile::factory()->for($this->company)->create([
                'bank_account_id' => $otherBankAccount->id,
            ]);

            Transaction::factory()->for($file1, 'importedFile')->create([
                'company_id' => $this->company->id,
                'date' => '2026-02-15',
                'reference_number' => 'REF123456',
                'debit' => 5000.00,
                'description' => 'NEFT Payment',
            ]);

            Transaction::factory()->for($file2, 'importedFile')->create([
                'company_id' => $this->company->id,
                'date' => '2026-02-15',
                'reference_number' => 'REF123456',
                'debit' => 5000.00,
                'description' => 'NEFT Payment',
            ]);

            $flags = $this->service->scanForDuplicates($file2);

            expect($flags)->toHaveCount(0);
        });

        it('does not flag transactions with different amounts', function () {
            $file1 = ImportedFile::factory()->for($this->company)->create([
                'bank_account_id' => $this->bankAccount->id,
            ]);
            $file2 = ImportedFile::factory()->for($this->company)->create([
                'bank_account_id' => $this->bankAccount->id,
            ]);

            Transaction::factory()->for($file1, 'importedFile')->create([
                'company_id' => $this->company->id,
                'date' => '2026-02-15',
                'debit' => 5000.00,
                'description' => 'NEFT Payment',
            ]);

            Transaction::factory()->for($file2, 'importedFile')->create([
                'company_id' => $this->company->id,
                'date' => '2026-02-15',
                'debit' => 7500.00,
                'description' => 'NEFT Payment',
            ]);

            $flags = $this->service->scanForDuplicates($file2);

            expect($flags)->toHaveCount(0);
        });

        it('does not flag transactions with different dates', function () {
            $file1 = ImportedFile::factory()->for($this->company)->create([
                'bank_account_id' => $this->bankAccount->id,
            ]);
            $file2 = ImportedFile::factory()->for($this->company)->create([
                'bank_account_id' => $this->bankAccount->id,
            ]);

            Transaction::factory()->for($file1, 'importedFile')->create([
                'company_id' => $this->company->id,
                'date' => '2026-02-15',
                'reference_number' => 'REF123456',
                'debit' => 5000.00,
                'description' => 'NEFT Payment',
            ]);

            Transaction::factory()->for($file2, 'importedFile')->create([
                'company_id' => $this->company->id,
                'date' => '2026-02-20',
                'reference_number' => 'REF123456',
                'debit' => 5000.00,
                'description' => 'NEFT Payment',
            ]);

            $flags = $this->service->scanForDuplicates($file2);

            expect($flags)->toHaveCount(0);
        });

        it('does not flag transactions with low description similarity and no reference number', function () {
            $file1 = ImportedFile::factory()->for($this->company)->create([
                'bank_account_id' => $this->bankAccount->id,
            ]);
            $file2 = ImportedFile::factory()->for($this->company)->create([
                'bank_account_id' => $this->bankAccount->id,
            ]);

            Transaction::factory()->for($file1, 'importedFile')->create([
                'company_id' => $this->company->id,
                'date' => '2026-02-15',
                'reference_number' => null,
                'debit' => 5000.00,
                'description' => 'NEFT Payment to Company ABC',
            ]);

            Transaction::factory()->for($file2, 'importedFile')->create([
                'company_id' => $this->company->id,
                'date' => '2026-02-15',
                'reference_number' => null,
                'debit' => 5000.00,
                'description' => 'UPI Transfer XYZ Store',
            ]);

            $flags = $this->service->scanForDuplicates($file2);

            expect($flags)->toHaveCount(0);
        });

        it('does not create duplicate flags that already exist', function () {
            $file1 = ImportedFile::factory()->for($this->company)->create([
                'bank_account_id' => $this->bankAccount->id,
            ]);
            $file2 = ImportedFile::factory()->for($this->company)->create([
                'bank_account_id' => $this->bankAccount->id,
            ]);

            Transaction::factory()->for($file1, 'importedFile')->create([
                'company_id' => $this->company->id,
                'date' => '2026-02-15',
                'reference_number' => 'REF123456',
                'debit' => 5000.00,
                'description' => 'NEFT Payment',
            ]);

            Transaction::factory()->for($file2, 'importedFile')->create([
                'company_id' => $this->company->id,
                'date' => '2026-02-15',
                'reference_number' => 'REF123456',
                'debit' => 5000.00,
                'description' => 'NEFT Payment',
            ]);

            $this->service->scanForDuplicates($file2);
            $this->service->scanForDuplicates($file2);

            expect(DuplicateFlag::count())->toBe(1);
        });

        it('scopes detection to same company only', function () {
            // Create other company's data BEFORE tenant context to avoid auto-association
            $otherCompany = Company::factory()->create();
            $otherBankAccount = BankAccount::factory()->for($otherCompany)->create();
            $otherFile = ImportedFile::factory()->for($otherCompany)->create([
                'bank_account_id' => $otherBankAccount->id,
            ]);
            Transaction::factory()->for($otherFile, 'importedFile')->for($otherCompany)->create([
                'date' => '2026-02-15',
                'reference_number' => 'REF123456',
                'debit' => 5000.00,
                'description' => 'NEFT Payment',
            ]);

            $file2 = ImportedFile::factory()->for($this->company)->create([
                'bank_account_id' => $this->bankAccount->id,
            ]);

            Transaction::factory()->for($file2, 'importedFile')->create([
                'company_id' => $this->company->id,
                'date' => '2026-02-15',
                'reference_number' => 'REF123456',
                'debit' => 5000.00,
                'description' => 'NEFT Payment',
            ]);

            $flags = $this->service->scanForDuplicates($file2);

            expect($flags)->toHaveCount(0);
        });
    });
});

describe('DuplicateFlag model', function () {
    it('belongs to a transaction', function () {
        $flag = DuplicateFlag::factory()->create();

        expect($flag->transaction)->toBeInstanceOf(Transaction::class)
            ->and($flag->duplicateTransaction)->toBeInstanceOf(Transaction::class);
    });

    it('stores match reasons as array', function () {
        $flag = DuplicateFlag::factory()->create([
            'match_reasons' => ['reference_number', 'amount_date'],
        ]);

        $fresh = DuplicateFlag::find($flag->id);
        expect($fresh->match_reasons)->toBe(['reference_number', 'amount_date']);
    });

    it('casts confidence and status to enums', function () {
        $flag = DuplicateFlag::factory()->highConfidence()->create();

        expect($flag->confidence)->toBe(DuplicateConfidence::High)
            ->and($flag->status)->toBe(DuplicateStatus::Pending);
    });
});
