<?php

use App\Enums\UserRole;
use App\Filament\Pages\Tenancy\EditCompanySettings;
use App\Filament\Pages\Tenancy\RegisterCompany;
use App\Http\Middleware\SetTenantDatabaseContext;
use App\Models\AccountHead;
use App\Models\Company;
use App\Models\ImportedFile;
use App\Models\Transaction;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Illuminate\Http\Request;

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

    it('returns first company as default tenant when no last_used_company_id is set', function () {
        $user = User::factory()->admin()->create();
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $company1->users()->attach($user);
        $company2->users()->attach($user);

        $default = $user->getDefaultTenant(Filament::getPanel('admin'));

        expect($default)->not->toBeNull()
            ->and($default->id)->toBe($company1->id);
    });

    it('returns last used company as default tenant when last_used_company_id is set', function () {
        $user = User::factory()->admin()->create();
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $company1->users()->attach($user);
        $company2->users()->attach($user);

        $user->update(['last_used_company_id' => $company2->id]);
        $user->refresh();

        $default = $user->getDefaultTenant(Filament::getPanel('admin'));

        expect($default)->not->toBeNull()
            ->and($default->id)->toBe($company2->id);
    });

    it('falls back to first company if last_used_company_id points to a company the user no longer belongs to', function () {
        $user = User::factory()->admin()->create();
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $company1->users()->attach($user);

        // company2 is set as last used but user is not a member
        $user->update(['last_used_company_id' => $company2->id]);
        $user->refresh();

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
                'currency' => 'INR',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $company = Company::where('name', 'Acme Corp')->first();
        expect($company)->not->toBeNull()
            ->and($company->gstin)->toBe('29AABCZ5012F1ZG')
            ->and($company->users->pluck('id'))->toContain($this->user->id)
            ->and($this->user->roleForCompany($company))->toBe(UserRole::Admin);
    });

    it('generates an inbox_address on company registration', function () {
        livewire(RegisterCompany::class)
            ->fillForm([
                'name' => 'Test Inbox Corp',
                'gst_registration_type' => 'Regular',
                'currency' => 'INR',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $company = Company::where('name', 'Test Inbox Corp')->first();
        $domain = config('services.mailgun.domain');

        expect($company->inbox_address)->not->toBeNull()
            ->and($company->inbox_address)->toContain('test-inbox-corp')
            ->and($company->inbox_address)->toEndWith('@'.$domain);
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

    it('does not have financial_year field', function () {
        livewire(RegisterCompany::class)
            ->assertFormFieldDoesNotExist('financial_year');
    });

    it('has state as a free-text field', function () {
        livewire(RegisterCompany::class)
            ->assertFormFieldExists('state', function (TextInput $field): bool {
                return $field->getName() === 'state';
            });
    });

    it('renders a cancel button on the company registration page', function () {
        livewire(RegisterCompany::class)
            ->assertActionExists('cancel');
    });

    it('redirects to login when cancel is clicked on the company registration page', function () {
        livewire(RegisterCompany::class)
            ->callAction('cancel')
            ->assertRedirect(route('filament.admin.auth.login'));
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

    it('shows inbox_address as disabled field', function () {
        tenant()->update(['inbox_address' => 'test-abc123@inbox.example.com']);

        livewire(EditCompanySettings::class)
            ->assertFormFieldIsDisabled('inbox_address')
            ->assertSuccessful();
    });

    it('does not have financial_year field', function () {
        livewire(EditCompanySettings::class)
            ->assertFormFieldDoesNotExist('financial_year');
    });

    it('has state as a free-text field', function () {
        livewire(EditCompanySettings::class)
            ->assertFormFieldExists('state', function (TextInput $field): bool {
                return $field->getName() === 'state';
            });
    });

    it('can save a non-Indian state value', function () {
        livewire(EditCompanySettings::class)
            ->fillForm([
                'name' => tenant()->name,
                'state' => 'California',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        tenant()->refresh();
        expect(tenant()->state)->toBe('California');
    });
});

describe('SetTenantDatabaseContext middleware updates last_used_company_id', function () {
    beforeEach(function () {
        $this->user = User::factory()->admin()->create();
        $this->actingAs($this->user);
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::bootCurrentPanel();
    });

    it('stores the current tenant id on the user when a tenant is active', function () {
        $company = Company::factory()->create();
        $company->users()->attach($this->user, ['role' => 'admin']);

        expect($this->user->last_used_company_id)->toBeNull();

        Filament::setTenant($company);

        $request = Request::create('/admin/'.$company->id);
        $request->setUserResolver(fn () => $this->user);

        (new SetTenantDatabaseContext)->handle($request, fn ($r) => response('ok'));

        $this->user->refresh();
        expect($this->user->last_used_company_id)->toBe($company->id);
    });

    it('updates last_used_company_id when switching to a different company', function () {
        $company1 = Company::factory()->create();
        $company2 = Company::factory()->create();
        $company1->users()->attach($this->user, ['role' => 'admin']);
        $company2->users()->attach($this->user, ['role' => 'admin']);

        $this->user->update(['last_used_company_id' => $company1->id]);

        Filament::setTenant($company2);

        $request = Request::create('/admin/'.$company2->id);
        $request->setUserResolver(fn () => $this->user);

        (new SetTenantDatabaseContext)->handle($request, fn ($r) => response('ok'));

        $this->user->refresh();
        expect($this->user->last_used_company_id)->toBe($company2->id);
    });

    it('does not update last_used_company_id if company has not changed', function () {
        $company = Company::factory()->create();
        $company->users()->attach($this->user, ['role' => 'admin']);
        $this->user->update(['last_used_company_id' => $company->id]);
        $this->user->refresh();
        $originalUpdatedAt = $this->user->updated_at;

        Filament::setTenant($company);

        $request = Request::create('/admin/'.$company->id);
        $request->setUserResolver(fn () => $this->user);

        (new SetTenantDatabaseContext)->handle($request, fn ($r) => response('ok'));

        $this->user->refresh();
        expect($this->user->last_used_company_id)->toBe($company->id)
            ->and($this->user->updated_at->eq($originalUpdatedAt))->toBeTrue();
    });
});
