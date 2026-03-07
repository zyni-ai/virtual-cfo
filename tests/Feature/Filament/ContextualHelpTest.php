<?php

use App\Filament\Resources\AccountHeadResource\Pages\CreateAccountHead;
use App\Filament\Resources\BankAccountResource\Pages\CreateBankAccount;
use App\Filament\Resources\BankAccountResource\Pages\ListBankAccounts;
use App\Filament\Resources\CreditCardResource\Pages\CreateCreditCard;
use App\Filament\Resources\CreditCardResource\Pages\ListCreditCards;
use App\Filament\Resources\HeadMappingResource\Pages\CreateHeadMapping;
use App\Filament\Resources\HeadMappingResource\Pages\ListHeadMappings;
use App\Filament\Resources\ImportedFileResource\Pages\CreateImportedFile;
use App\Filament\Resources\ImportedFileResource\Pages\ListImportedFiles;
use App\Filament\Resources\ReconciliationResource\Pages\ListReconciliation;
use App\Filament\Resources\RecurringPatternResource\Pages\ListRecurringPatterns;
use App\Filament\Resources\TransactionResource\Pages\ListTransactions;

use function Pest\Livewire\livewire;

describe('Contextual Help — Form Helper Text', function () {
    beforeEach(function () {
        asUser();
    });

    it('renders helper text on AccountHead create form', function () {
        livewire(CreateAccountHead::class)
            ->assertSeeText('The display name for this account head')
            ->assertSeeText('Select a parent to create a hierarchy');
    });

    it('renders helper text on BankAccount create form', function () {
        livewire(CreateBankAccount::class)
            ->assertSeeText('e.g., HDFC Bank, ICICI Bank')
            ->assertSeeText('Used to auto-detect the account')
            ->assertSeeText('11-character IFSC code');
    });

    it('renders helper text on CreditCard create form', function () {
        livewire(CreateCreditCard::class)
            ->assertSeeText('A descriptive name for this card')
            ->assertSeeText('Only the last 4 digits are displayed');
    });

    it('renders helper text on HeadMapping create form', function () {
        livewire(CreateHeadMapping::class)
            ->assertSeeText('Contains: partial match. Exact: full match')
            ->assertSeeText('The account head to assign when this rule matches');
    });

    it('renders helper text on ImportedFile create form', function () {
        livewire(CreateImportedFile::class)
            ->assertSeeText('Select the type of document you are uploading');
    });
});

describe('Contextual Help — Empty States', function () {
    beforeEach(function () {
        asUser();
    });

    it('shows empty state guidance on BankAccounts list', function () {
        livewire(ListBankAccounts::class)
            ->assertSeeText('No bank accounts yet');
    });

    it('shows empty state guidance on CreditCards list', function () {
        livewire(ListCreditCards::class)
            ->assertSeeText('No credit cards yet');
    });

    it('shows empty state guidance on HeadMappings list', function () {
        livewire(ListHeadMappings::class)
            ->assertSeeText('No mapping rules yet');
    });

    it('shows empty state guidance on RecurringPatterns list', function () {
        livewire(ListRecurringPatterns::class)
            ->assertSeeText('No recurring patterns yet');
    });

    it('shows empty state guidance on Transactions list', function () {
        livewire(ListTransactions::class)
            ->assertSeeText('No transactions yet');
    });

    it('shows empty state guidance on ImportedFiles list', function () {
        livewire(ListImportedFiles::class)
            ->assertSeeText('No imported files yet');
    });

    it('shows empty state guidance on Reconciliation list', function () {
        livewire(ListReconciliation::class)
            ->assertSeeText('No transactions to reconcile');
    });
});

describe('Contextual Help — Page Subheadings', function () {
    beforeEach(function () {
        asUser();
    });

    it('shows subheading on Transactions list page', function () {
        livewire(ListTransactions::class)
            ->assertSeeText('Review, map, and export your parsed transactions');
    });

    it('shows subheading on ImportedFiles list page', function () {
        livewire(ListImportedFiles::class)
            ->assertSeeText('Upload and manage bank statements, credit card statements, and invoices');
    });

    it('shows subheading on Reconciliation list page', function () {
        livewire(ListReconciliation::class)
            ->assertSeeText('Match bank transactions against invoices');
    });
});
