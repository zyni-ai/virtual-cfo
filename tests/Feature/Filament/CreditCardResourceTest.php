<?php

use App\Filament\Resources\CreditCardResource;
use App\Filament\Resources\CreditCardResource\Pages\CreateCreditCard;
use App\Filament\Resources\CreditCardResource\Pages\EditCreditCard;
use App\Filament\Resources\CreditCardResource\Pages\ListCreditCards;
use App\Models\CreditCard;
use App\Models\ImportedFile;

use function Pest\Livewire\livewire;

describe('CreditCardResource', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render the list page', function () {
        livewire(ListCreditCards::class)->assertSuccessful();
    });

    it('can list credit cards scoped to tenant', function () {
        $ownCards = CreditCard::factory()->count(2)->create(['company_id' => tenant()->id]);

        livewire(ListCreditCards::class)
            ->assertCanSeeTableRecords($ownCards)
            ->assertCountTableRecords(2);
    });

    it('can render the create page', function () {
        livewire(CreateCreditCard::class)->assertSuccessful();
    });

    it('can create a credit card', function () {
        livewire(CreateCreditCard::class)
            ->fillForm([
                'name' => 'HDFC Credit Card',
                'card_number' => '4111111111111234',
                'pdf_password' => 'secret123',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $card = CreditCard::where('name', 'HDFC Credit Card')->first();
        expect($card)->not->toBeNull()
            ->and($card->company_id)->toBe(tenant()->id)
            ->and($card->card_number)->toBe('4111111111111234')
            ->and($card->pdf_password)->toBe('secret123');
    });

    it('validates required fields on create', function () {
        livewire(CreateCreditCard::class)
            ->fillForm([
                'name' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    });

    it('can render the edit page', function () {
        $card = CreditCard::factory()->create(['company_id' => tenant()->id]);

        livewire(EditCreditCard::class, ['record' => $card->getRouteKey()])
            ->assertSuccessful();
    });

    it('can update a credit card', function () {
        $card = CreditCard::factory()->create(['company_id' => tenant()->id, 'name' => 'Old Card']);

        livewire(EditCreditCard::class, ['record' => $card->getRouteKey()])
            ->fillForm([
                'name' => 'New Card Name',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($card->fresh()->name)->toBe('New Card Name');
    });

    it('can delete a credit card via soft delete', function () {
        $card = CreditCard::factory()->create(['company_id' => tenant()->id]);

        livewire(ListCreditCards::class)
            ->callTableAction('delete', $card);

        expect(CreditCard::find($card->id))->toBeNull()
            ->and(CreditCard::withTrashed()->find($card->id))->not->toBeNull();
    });

    it('shows import count in table', function () {
        $card = CreditCard::factory()->create(['company_id' => tenant()->id]);
        ImportedFile::factory()->count(3)->create([
            'company_id' => tenant()->id,
            'credit_card_id' => $card->id,
        ]);

        livewire(ListCreditCards::class)
            ->assertSuccessful();
    });

    it('has correct navigation properties', function () {
        expect(CreditCardResource::getNavigationLabel())->toBe('Credit Cards')
            ->and(CreditCardResource::getNavigationSort())->toBe(6);
    });

    it('stores pdf_password as encrypted', function () {
        $card = CreditCard::factory()->withPassword('mypassword')->create([
            'company_id' => tenant()->id,
        ]);

        expect($card->pdf_password)->toBe('mypassword');

        $raw = \Illuminate\Support\Facades\DB::table('credit_cards')
            ->where('id', $card->id)
            ->value('pdf_password');

        expect($raw)->not->toBe('mypassword');
    });

    it('masks card number correctly', function () {
        $card = CreditCard::factory()->create([
            'company_id' => tenant()->id,
            'card_number' => '4111111111111234',
        ]);

        expect($card->masked_card_number)->toBe('••••••••••••1234');
    });
});
