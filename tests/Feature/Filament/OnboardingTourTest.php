<?php

use App\Livewire\OnboardingTour;
use Livewire\Livewire;

describe('Onboarding Tour', function () {
    it('has toured_at column on users table', function () {
        $user = asUser();

        expect($user->toured_at)->toBeNull();
    });

    it('renders tour component for new users', function () {
        $user = asUser();

        Livewire::actingAs($user)
            ->test(OnboardingTour::class)
            ->assertSet('showTour', true);
    });

    it('does not show tour for users who completed it', function () {
        $user = asUser();
        $user->update(['toured_at' => now()]);

        Livewire::actingAs($user)
            ->test(OnboardingTour::class)
            ->assertSet('showTour', false);
    });

    it('can complete the tour', function () {
        $user = asUser();

        Livewire::actingAs($user)
            ->test(OnboardingTour::class)
            ->call('completeTour');

        expect($user->fresh()->toured_at)->not->toBeNull();
    });

    it('can restart the tour', function () {
        $user = asUser();
        $user->update(['toured_at' => now()]);

        Livewire::actingAs($user)
            ->test(OnboardingTour::class)
            ->call('restartTour')
            ->assertSet('showTour', true);

        expect($user->fresh()->toured_at)->toBeNull();
    });
});
