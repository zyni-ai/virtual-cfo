<?php

use App\Filament\Widgets\RecentImports;
use App\Models\ImportedFile;

use function Pest\Livewire\livewire;

describe('RecentImports widget', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render', function () {
        livewire(RecentImports::class)->assertSuccessful();
    });

    it('shows recent imports', function () {
        ImportedFile::factory()->count(3)->create();

        livewire(RecentImports::class)
            ->assertSuccessful();
    });
});
