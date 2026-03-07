<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class OnboardingTour extends Component
{
    public bool $showTour = false;

    public function mount(): void
    {
        $this->showTour = $this->user()->toured_at === null;
    }

    public function completeTour(): void
    {
        $this->user()->update(['toured_at' => now()]);
        $this->showTour = false;
    }

    #[On('restart-tour')]
    public function restartTour(): void
    {
        $this->user()->update(['toured_at' => null]);
        $this->showTour = true;
    }

    public function render(): View
    {
        return view('livewire.onboarding-tour');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
