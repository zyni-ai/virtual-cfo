<?php

namespace App\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class OnboardingTour extends Component
{
    public bool $showTour = false;

    public function mount(): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $this->showTour = $user->toured_at === null;
    }

    public function completeTour(): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $user->update(['toured_at' => now()]);
        $this->showTour = false;
    }

    #[On('restart-tour')]
    public function restartTour(): void
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $user->update(['toured_at' => null]);
        $this->showTour = true;
    }

    public function render(): View
    {
        return view('livewire.onboarding-tour');
    }
}
