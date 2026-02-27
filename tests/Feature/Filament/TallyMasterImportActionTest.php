<?php

use App\Filament\Resources\AccountHeadResource\Pages\ListAccountHeads;
use App\Models\AccountHead;
use App\Models\BankAccount;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;

use function Pest\Livewire\livewire;

beforeEach(function () {
    asUser();
});

describe('Tally Master Import Action', function () {
    it('renders the import action on the list page', function () {
        livewire(ListAccountHeads::class)
            ->assertActionExists(TestAction::make('import_tally')->table());
    });

    it('opens a modal with file upload form', function () {
        livewire(ListAccountHeads::class)
            ->assertActionVisible(TestAction::make('import_tally')->table());
    });

    it('validates that file is required', function () {
        livewire(ListAccountHeads::class)
            ->callAction(TestAction::make('import_tally')->table(), data: [
                'xml_file' => null,
            ])
            ->assertHasActionErrors(['xml_file' => 'required']);
    });

    it('imports account heads from uploaded XML file', function () {
        $xmlContent = file_get_contents(base_path('tests/fixtures/tally-masters-simple.xml'));
        $file = UploadedFile::fake()->createWithContent('masters.xml', $xmlContent);

        livewire(ListAccountHeads::class)
            ->callAction(TestAction::make('import_tally')->table(), data: [
                'xml_file' => [$file],
            ])
            ->assertHasNoActionErrors()
            ->assertNotified();

        $company = tenant();

        expect(AccountHead::where('company_id', $company->id)->where('name', 'Sundry Debtors')->exists())->toBeTrue()
            ->and(AccountHead::where('company_id', $company->id)->where('name', 'Acme Corp')->exists())->toBeTrue()
            ->and(AccountHead::where('company_id', $company->id)->where('name', 'ICICI Bank - Current A/c')->exists())->toBeTrue();
    });

    it('creates bank accounts from bank-type ledgers', function () {
        $xmlContent = file_get_contents(base_path('tests/fixtures/tally-masters-simple.xml'));
        $file = UploadedFile::fake()->createWithContent('masters.xml', $xmlContent);

        livewire(ListAccountHeads::class)
            ->callAction(TestAction::make('import_tally')->table(), data: [
                'xml_file' => [$file],
            ]);

        $company = tenant();

        expect(BankAccount::where('company_id', $company->id)->where('name', 'ICICI Bank - Current A/c')->exists())->toBeTrue();
    });

    it('shows error notification for invalid XML', function () {
        $file = UploadedFile::fake()->createWithContent('invalid.xml', 'not xml');

        livewire(ListAccountHeads::class)
            ->callAction(TestAction::make('import_tally')->table(), data: [
                'xml_file' => [$file],
            ])
            ->assertNotified();
    });
});
