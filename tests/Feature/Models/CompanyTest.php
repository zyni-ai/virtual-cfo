<?php

use App\Models\AccountHead;
use App\Models\BankAccount;
use App\Models\Company;
use App\Models\Connector;
use App\Models\HeadMapping;
use App\Models\ImportedFile;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

describe('Company factory', function () {
    it('creates a company with valid defaults', function () {
        $company = Company::factory()->create();

        expect($company->exists)->toBeTrue()
            ->and($company->name)->toBeString()
            ->and($company->gstin)->toBeString()
            ->and($company->state)->toBeString()
            ->and($company->gst_registration_type)->toBe('Regular')
            ->and($company->currency)->toBe('INR');
    });

    it('does not have financial_year column in database', function () {
        expect(Schema::hasColumn('companies', 'financial_year'))->toBeFalse();
    });

    it('creates a zysk company with known defaults', function () {
        $company = Company::factory()->zysk()->create();

        expect($company->name)->toBe('Zysk Technologies Private Limited - 2025 - 2026')
            ->and($company->gstin)->toBe('29AABCZ5012F1ZG')
            ->and($company->state)->toBe('Karnataka');
    });
});

describe('Company relationships', function () {
    it('has many users via pivot', function () {
        $company = Company::factory()->create();
        $users = User::factory()->count(2)->create();
        $company->users()->attach($users);

        expect($company->users)->toHaveCount(2);
    });

    it('has many bank accounts', function () {
        $company = Company::factory()->create();
        BankAccount::factory()->count(3)->create(['company_id' => $company->id]);

        expect($company->bankAccounts)->toHaveCount(3);
    });

    it('has many imported files', function () {
        $company = Company::factory()->create();
        ImportedFile::factory()->count(2)->create(['company_id' => $company->id]);

        expect($company->importedFiles)->toHaveCount(2);
    });

    it('has many account heads', function () {
        $company = Company::factory()->create();
        AccountHead::factory()->count(4)->create(['company_id' => $company->id]);

        expect($company->accountHeads)->toHaveCount(4);
    });

    it('has many head mappings', function () {
        $company = Company::factory()->create();
        HeadMapping::factory()->count(2)->create(['company_id' => $company->id]);

        expect($company->headMappings)->toHaveCount(2);
    });

    it('has many transactions', function () {
        $company = Company::factory()->create();
        Transaction::factory()->count(3)->create(['company_id' => $company->id]);

        expect($company->transactions)->toHaveCount(3);
    });
});

describe('Company connectors relationship', function () {
    it('has many connectors', function () {
        $company = Company::factory()->create();
        Connector::factory()->create(['company_id' => $company->id]);

        expect($company->connectors)->toHaveCount(1);
    });
});

describe('Company inbox_address', function () {
    it('can store an inbox address', function () {
        $company = Company::factory()->create(['inbox_address' => 'acme-abc123@inbox.example.com']);

        expect($company->inbox_address)->toBe('acme-abc123@inbox.example.com');
    });

    it('defaults inbox_address to null', function () {
        $company = Company::factory()->create();

        expect($company->inbox_address)->toBeNull();
    });
});

describe('Company activity log', function () {
    it('logs creation', function () {
        $company = Company::factory()->create();

        expect($company->activities)->toHaveCount(1)
            ->and($company->activities->first()->description)->toBe('created');
    });

    it('logs updates', function () {
        $company = Company::factory()->create();
        $company->update(['name' => 'Updated Name']);

        $activities = $company->activities()->orderBy('id')->get();

        expect($activities)->toHaveCount(2)
            ->and($activities->last()->description)->toBe('updated');
    });
});
