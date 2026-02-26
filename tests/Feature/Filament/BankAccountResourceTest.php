<?php

use App\Enums\AccountType;
use App\Filament\Resources\BankAccountResource;
use App\Filament\Resources\BankAccountResource\Pages\CreateBankAccount;
use App\Filament\Resources\BankAccountResource\Pages\EditBankAccount;
use App\Filament\Resources\BankAccountResource\Pages\ListBankAccounts;
use App\Models\BankAccount;
use App\Models\ImportedFile;

use function Pest\Livewire\livewire;

describe('BankAccountResource', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render the list page', function () {
        livewire(ListBankAccounts::class)->assertSuccessful();
    });

    it('can list bank accounts scoped to tenant', function () {
        $ownAccounts = BankAccount::factory()->count(2)->create(['company_id' => tenant()->id]);

        livewire(ListBankAccounts::class)
            ->assertCanSeeTableRecords($ownAccounts)
            ->assertCountTableRecords(2);
    });

    it('can render the create page', function () {
        livewire(CreateBankAccount::class)->assertSuccessful();
    });

    it('can create a bank account', function () {
        livewire(CreateBankAccount::class)
            ->fillForm([
                'name' => 'HDFC Bank',
                'account_number' => '50100123456789',
                'ifsc_code' => 'HDFC0001234',
                'branch' => 'MG Road, Bangalore',
                'account_type' => AccountType::Current->value,
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $account = BankAccount::where('name', 'HDFC Bank')->first();
        expect($account)->not->toBeNull()
            ->and($account->company_id)->toBe(tenant()->id)
            ->and($account->account_number)->toBe('50100123456789')
            ->and($account->ifsc_code)->toBe('HDFC0001234');
    });

    it('validates required fields on create', function () {
        livewire(CreateBankAccount::class)
            ->fillForm([
                'name' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    });

    it('can render the edit page', function () {
        $account = BankAccount::factory()->create(['company_id' => tenant()->id]);

        livewire(EditBankAccount::class, ['record' => $account->getRouteKey()])
            ->assertSuccessful();
    });

    it('can update a bank account', function () {
        $account = BankAccount::factory()->create(['company_id' => tenant()->id, 'name' => 'Old Bank']);

        livewire(EditBankAccount::class, ['record' => $account->getRouteKey()])
            ->fillForm([
                'name' => 'New Bank Name',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($account->fresh()->name)->toBe('New Bank Name');
    });

    it('can delete a bank account via soft delete', function () {
        $account = BankAccount::factory()->create(['company_id' => tenant()->id]);

        livewire(ListBankAccounts::class)
            ->callTableAction('delete', $account);

        expect(BankAccount::find($account->id))->toBeNull()
            ->and(BankAccount::withTrashed()->find($account->id))->not->toBeNull();
    });

    it('shows masked account number in table', function () {
        BankAccount::factory()->create([
            'company_id' => tenant()->id,
            'account_number' => '50100123456789',
        ]);

        livewire(ListBankAccounts::class)
            ->assertSuccessful();
    });

    it('shows import count in table', function () {
        $account = BankAccount::factory()->create(['company_id' => tenant()->id]);
        ImportedFile::factory()->count(3)->create([
            'company_id' => tenant()->id,
            'bank_account_id' => $account->id,
        ]);

        livewire(ListBankAccounts::class)
            ->assertSuccessful();
    });

    it('has correct navigation properties', function () {
        expect(BankAccountResource::getNavigationLabel())->toBe('Bank Accounts')
            ->and(BankAccountResource::getNavigationSort())->toBe(5);
    });
});
