<?php

use App\Filament\Pages\Tenancy\EditCompanySettings;
use App\Filament\Pages\Tenancy\RegisterCompany;
use App\Models\AccountHead;
use App\Models\Company;
use App\Models\ImportedFile;
use App\Models\Transaction;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

describe('User tenancy contracts', function () {
    it('implements HasTenants and returns companies', function () {
        $user = User::factory()->admin()->create();
        $company = Company::factory()->create();
        $company->users()->attach($user);

        $tenants = $user->getTenants(Filament::getPanel('admin'));

        expect($tenants)->toHaveCount(1)
            ->and($tenants->first()->id)->toBe($company->id);
    });

    it('can access tenant when attached', function () {
        $user = User::factory()->admin()->create();
        $company = Company::factory()->create();
        $company->users()->attach($user);

        expect($user->canAccessTenant($company))->toBeTrue();
    });

    it('cannot access tenant when not attached', function () {
        $user = User::factory()->admin()->create();
        $company = Company::factory()->create();

        expect($user->canAccessTenant($company))->toBeFalse();
    });

    it('returns first company as default tenant', function () {
        $user = User::factory()->admin()->create();
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $company1->users()->attach($user);
        $company2->users()->attach($user);

        $default = $user->getDefaultTenant(Filament::getPanel('admin'));

        expect($default)->not->toBeNull()
            ->and($default->id)->toBe($company1->id);
    });
});

describe('Tenant data isolation', function () {
    it('isolates imported files between companies', function () {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        ImportedFile::factory()->count(2)->create(['company_id' => $companyA->id]);
        ImportedFile::factory()->count(3)->create(['company_id' => $companyB->id]);

        expect($companyA->importedFiles()->count())->toBe(2)
            ->and($companyB->importedFiles()->count())->toBe(3);
    });

    it('isolates transactions between companies', function () {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        Transaction::factory()->count(2)->create(['company_id' => $companyA->id]);
        Transaction::factory()->count(1)->create(['company_id' => $companyB->id]);

        expect($companyA->transactions()->count())->toBe(2)
            ->and($companyB->transactions()->count())->toBe(1);
    });

    it('isolates account heads between companies', function () {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        AccountHead::factory()->count(3)->create(['company_id' => $companyA->id]);
        AccountHead::factory()->count(1)->create(['company_id' => $companyB->id]);

        expect($companyA->accountHeads()->count())->toBe(3)
            ->and($companyB->accountHeads()->count())->toBe(1);
    });

    it('auto-associates new models with current tenant via Filament', function () {
        asUser();

        $head = AccountHead::factory()->create();

        expect($head->company_id)->toBe(tenant()->id);
    });
});

describe('Register Company page', function () {
    beforeEach(function () {
        $this->user = User::factory()->admin()->create();
        $this->actingAs($this->user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    });

    it('can render the register page', function () {
        livewire(RegisterCompany::class)->assertSuccessful();
    });

    it('can register a new company', function () {
        livewire(RegisterCompany::class)
            ->fillForm([
                'name' => 'Acme Corp',
                'gstin' => '29AABCZ5012F1ZG',
                'state' => 'Karnataka',
                'gst_registration_type' => 'Regular',
                'financial_year' => '2025-2026',
                'currency' => 'INR',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $company = Company::where('name', 'Acme Corp')->first();
        expect($company)->not->toBeNull()
            ->and($company->gstin)->toBe('29AABCZ5012F1ZG')
            ->and($company->users->pluck('id'))->toContain($this->user->id);
    });

    it('requires company name', function () {
        livewire(RegisterCompany::class)
            ->fillForm([
                'name' => '',
            ])
            ->call('register')
            ->assertHasFormErrors(['name' => 'required']);
    });

    it('rejects invalid GSTIN', function () {
        livewire(RegisterCompany::class)
            ->fillForm([
                'name' => 'Test Company',
                'gstin' => 'INVALIDGSTIN',
            ])
            ->call('register')
            ->assertHasFormErrors(['gstin']);
    });
});

describe('Edit Company Settings page', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render the edit tenant profile page', function () {
        livewire(EditCompanySettings::class)
            ->assertSuccessful();
    });

    it('can update company name', function () {
        livewire(EditCompanySettings::class)
            ->fillForm([
                'name' => 'Updated Company Name',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        tenant()->refresh();
        expect(tenant()->name)->toBe('Updated Company Name');
    });

    it('rejects invalid GSTIN on edit', function () {
        livewire(EditCompanySettings::class)
            ->fillForm([
                'gstin' => 'NOT-A-VALID-GSTIN',
            ])
            ->call('save')
            ->assertHasFormErrors(['gstin']);
    });
});
