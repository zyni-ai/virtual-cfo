<?php

use App\Models\HeadMapping;
use App\Models\ImportedFile;
use App\Models\User;
use Filament\Panel;

describe('User relationships', function () {
    it('has many imported files', function () {
        $user = User::factory()->create();
        ImportedFile::factory()->for($user, 'uploader')->create();

        expect($user->importedFiles)->toHaveCount(1);
    });

    it('has many head mappings', function () {
        $user = User::factory()->create();
        HeadMapping::factory()->for($user, 'creator')->create();

        expect($user->headMappings)->toHaveCount(1);
    });
});

describe('User Filament access', function () {
    it('can access the admin panel', function () {
        $user = User::factory()->create();

        expect($user->canAccessPanel(app(Panel::class)))->toBeTrue();
    });
});
