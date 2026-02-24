<?php

use App\Enums\ImportStatus;
use App\Filament\Widgets\RecentImports;
use App\Filament\Widgets\StatsOverview;
use App\Models\ImportedFile;
use App\Models\Transaction;

use function Pest\Livewire\livewire;

describe('StatsOverview widget', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render', function () {
        livewire(StatsOverview::class)->assertSuccessful();
    });

    it('shows correct stats', function () {
        ImportedFile::factory()->count(2)->create();
        Transaction::factory()->unmapped()->count(3)->create();
        Transaction::factory()->mapped()->count(2)->create();
        ImportedFile::factory()->create(['status' => ImportStatus::Processing]);

        $component = livewire(StatsOverview::class);
        $component->assertSuccessful();
    });
});

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
