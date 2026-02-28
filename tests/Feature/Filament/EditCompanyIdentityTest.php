<?php

use App\Filament\Pages\Tenancy\EditCompanySettings;

use function Pest\Livewire\livewire;

describe('EditCompanySettings identity fields', function () {
    beforeEach(function () {
        asUser();
    });

    it('renders the identity section on the settings page', function () {
        livewire(EditCompanySettings::class)
            ->assertSuccessful()
            ->assertSee('Account Holder Identity');
    });

    it('saves identity fields successfully', function () {
        livewire(EditCompanySettings::class)
            ->fillForm([
                'account_holder_name' => 'John Doe',
                'date_of_birth' => '1990-01-15',
                'pan_number' => 'ABCDE1234F',
                'mobile_number' => '9876543210',
            ])
            ->call('save')
            ->assertNotified();

        $company = tenant();
        $company->refresh();

        expect($company->account_holder_name)->toBe('John Doe')
            ->and($company->pan_number)->toBe('ABCDE1234F')
            ->and($company->mobile_number)->toBe('9876543210');
    });

    it('validates PAN number format', function () {
        livewire(EditCompanySettings::class)
            ->fillForm([
                'pan_number' => 'INVALID',
            ])
            ->call('save')
            ->assertHasFormErrors(['pan_number']);
    });

    it('allows empty identity fields', function () {
        livewire(EditCompanySettings::class)
            ->fillForm([
                'account_holder_name' => null,
                'date_of_birth' => null,
                'pan_number' => null,
                'mobile_number' => null,
            ])
            ->call('save')
            ->assertNotified();
    });
});
