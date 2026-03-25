<?php

use App\Filament\Pages\Auth\EditProfile;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

describe('EditProfile page', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render the profile page', function () {
        livewire(EditProfile::class)->assertSuccessful();
    });

    it('redirects after saving the profile', function () {
        livewire(EditProfile::class)
            ->fillForm(['name' => 'Updated Name'])
            ->call('save')
            ->assertRedirect(Filament::getHomeUrl());
    });
});
