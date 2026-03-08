<?php

use App\Filament\Resources\ImportedFileResource;
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

    it('shows only filename and status columns', function () {
        ImportedFile::factory()->create();

        $widget = livewire(RecentImports::class);

        $columns = collect($widget->instance()->getTable()->getColumns())
            ->keys()
            ->all();

        expect($columns)->toBe(['original_filename', 'status']);

        $widget
            ->assertCanRenderTableColumn('original_filename')
            ->assertCanRenderTableColumn('status');
    });

    it('makes rows clickable to the import detail page', function () {
        $import = ImportedFile::factory()->create();

        $expectedUrl = ImportedFileResource::getUrl('view', ['record' => $import]);

        livewire(RecentImports::class)
            ->assertSeeHtml($expectedUrl);
    });
});
