<?php

use App\Models\AccountHead;
use App\Models\BankAccount;
use App\Models\Company;
use App\Services\TallyImport\TallyMasterImportService;

beforeEach(function () {
    $this->company = Company::factory()->create(['name' => 'Old Company Name']);
    $this->service = new TallyMasterImportService;
    $this->simpleXml = file_get_contents(base_path('tests/fixtures/tally-masters-simple.xml'));
});

describe('TallyMasterImportService::import() — Groups', function () {
    it('creates groups as account heads', function () {
        $result = $this->service->import($this->simpleXml, $this->company);

        expect($result->groupsCreated)->toBe(2)
            ->and(AccountHead::where('company_id', $this->company->id)->where('name', 'Sundry Debtors')->exists())->toBeTrue()
            ->and(AccountHead::where('company_id', $this->company->id)->where('name', 'Bank Accounts')->exists())->toBeTrue();
    });

    it('assigns tally_guid to imported groups', function () {
        $this->service->import($this->simpleXml, $this->company);

        $group = AccountHead::where('company_id', $this->company->id)
            ->where('name', 'Sundry Debtors')
            ->first();

        expect($group->tally_guid)->toBe('e5f6a7b8-c9d0-1234-5678-9abcdef01234');
    });

    it('sets group_name to the group name itself for groups', function () {
        $this->service->import($this->simpleXml, $this->company);

        $group = AccountHead::where('company_id', $this->company->id)
            ->where('name', 'Sundry Debtors')
            ->first();

        expect($group->group_name)->toBe('Sundry Debtors');
    });

    it('handles parent hierarchy for child groups', function () {
        $parentHead = AccountHead::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Current Assets',
        ]);

        $this->service->import($this->simpleXml, $this->company);

        $sundryDebtors = AccountHead::where('company_id', $this->company->id)
            ->where('name', 'Sundry Debtors')
            ->first();

        expect($sundryDebtors->parent_id)->toBe($parentHead->id);
    });

    it('adds warning when parent group is not found', function () {
        $result = $this->service->import($this->simpleXml, $this->company);

        expect($result->warnings)->toContain(
            "Parent group 'Current Assets' not found for group 'Sundry Debtors' — imported without parent."
        );
    });
});

describe('TallyMasterImportService::import() — Ledgers', function () {
    it('creates ledgers as account heads', function () {
        $result = $this->service->import($this->simpleXml, $this->company);

        expect($result->ledgersCreated)->toBe(3)
            ->and(AccountHead::where('company_id', $this->company->id)->where('name', 'Acme Corp')->exists())->toBeTrue()
            ->and(AccountHead::where('company_id', $this->company->id)->where('name', 'Office Rent')->exists())->toBeTrue()
            ->and(AccountHead::where('company_id', $this->company->id)->where('name', 'ICICI Bank - Current A/c')->exists())->toBeTrue();
    });

    it('sets group_name to the parent group for ledgers', function () {
        $this->service->import($this->simpleXml, $this->company);

        $ledger = AccountHead::where('company_id', $this->company->id)
            ->where('name', 'Acme Corp')
            ->first();

        expect($ledger->group_name)->toBe('Sundry Debtors');
    });

    it('links ledger to parent group account head', function () {
        $this->service->import($this->simpleXml, $this->company);

        $parentGroup = AccountHead::where('company_id', $this->company->id)
            ->where('name', 'Sundry Debtors')
            ->first();

        $ledger = AccountHead::where('company_id', $this->company->id)
            ->where('name', 'Acme Corp')
            ->first();

        expect($ledger->parent_id)->toBe($parentGroup->id);
    });
});

describe('TallyMasterImportService::import() — Bank Accounts', function () {
    it('creates bank account for bank-type ledger', function () {
        $result = $this->service->import($this->simpleXml, $this->company);

        expect($result->bankAccountsCreated)->toBe(1);

        $bank = BankAccount::where('company_id', $this->company->id)
            ->where('name', 'ICICI Bank - Current A/c')
            ->first();

        expect($bank)->not->toBeNull()
            ->and($bank->account_number)->toBe('012345678901')
            ->and($bank->ifsc_code)->toBe('ICIC0001234')
            ->and($bank->branch)->toBe('MG Road, Bangalore');
    });

    it('does not create bank account for non-bank ledgers', function () {
        $this->service->import($this->simpleXml, $this->company);

        expect(BankAccount::where('company_id', $this->company->id)->count())->toBe(1);
    });
});

describe('TallyMasterImportService::import() — Company Info', function () {
    it('updates company name from SVCURRENTCOMPANY', function () {
        $result = $this->service->import($this->simpleXml, $this->company);

        expect($result->companyUpdated)->toBeTrue()
            ->and($this->company->fresh()->name)->toBe('Zysk Technologies Private Limited');
    });

    it('does not update company if name already matches', function () {
        $this->company->update(['name' => 'Zysk Technologies Private Limited']);

        $result = $this->service->import($this->simpleXml, $this->company);

        expect($result->companyUpdated)->toBeFalse();
    });
});

describe('TallyMasterImportService::import() — Idempotency', function () {
    it('does not create duplicates on re-import', function () {
        $this->service->import($this->simpleXml, $this->company);
        $firstCount = AccountHead::where('company_id', $this->company->id)->count();

        $result = $this->service->import($this->simpleXml, $this->company);

        expect(AccountHead::where('company_id', $this->company->id)->count())->toBe($firstCount)
            ->and($result->groupsUpdated)->toBe(2)
            ->and($result->ledgersUpdated)->toBe(3);
    });

    it('matches existing records by tally_guid', function () {
        $existing = AccountHead::factory()->create([
            'company_id' => $this->company->id,
            'name' => 'Renamed Acme',
            'tally_guid' => '11111111-1111-1111-1111-111111111111',
        ]);

        $this->service->import($this->simpleXml, $this->company);

        expect($existing->fresh()->name)->toBe('Acme Corp');
    });

    it('updates bank account on re-import', function () {
        $this->service->import($this->simpleXml, $this->company);
        $result = $this->service->import($this->simpleXml, $this->company);

        expect($result->bankAccountsUpdated)->toBe(1)
            ->and($result->bankAccountsCreated)->toBe(0)
            ->and(BankAccount::where('company_id', $this->company->id)->count())->toBe(1);
    });
});

describe('TallyMasterImportService::import() — Edge Cases', function () {
    it('returns error for invalid XML', function () {
        $result = $this->service->import('not xml at all', $this->company);

        expect($result->hasErrors())->toBeTrue()
            ->and($result->errors[0])->toContain('Invalid XML');
    });

    it('returns zero counts for empty XML', function () {
        $emptyXml = file_get_contents(base_path('tests/fixtures/tally-masters-empty.xml'));

        $result = $this->service->import($emptyXml, $this->company);

        expect($result->totalCreated())->toBe(0)
            ->and($result->totalUpdated())->toBe(0)
            ->and($result->hasErrors())->toBeFalse();
    });

    it('handles UTF-16LE encoded file', function () {
        $utf16Content = file_get_contents(base_path('tests/fixtures/tally-masters-utf16le.xml'));

        $result = $this->service->import($utf16Content, $this->company);

        expect($result->groupsCreated)->toBe(2)
            ->and($result->ledgersCreated)->toBe(3)
            ->and($result->bankAccountsCreated)->toBe(1);
    });

    it('scopes all records to the given company', function () {
        $otherCompany = Company::factory()->create();
        AccountHead::factory()->create([
            'company_id' => $otherCompany->id,
            'name' => 'Sundry Debtors',
            'tally_guid' => 'different-guid',
        ]);

        $this->service->import($this->simpleXml, $this->company);

        expect(AccountHead::where('company_id', $this->company->id)->where('name', 'Sundry Debtors')->count())->toBe(1)
            ->and(AccountHead::where('company_id', $otherCompany->id)->where('name', 'Sundry Debtors')->count())->toBe(1);
    });
});
