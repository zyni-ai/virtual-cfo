<?php

namespace App\Livewire;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class OnboardingTour extends Component
{
    public string $pageId;

    public bool $showTour = false;

    /** @var array<int, array{title: string, description: string, element: string|null}> */
    public array $steps = [];

    public function mount(string $pageId): void
    {
        $this->pageId = $pageId;
        $this->steps = config("tours.{$pageId}", []);

        /** @var array<string, bool> $touredPages */
        $touredPages = $this->user()->toured_pages ?? [];
        $this->showTour = ! isset($touredPages[$pageId]);
    }

    public function completeTour(): void
    {
        $user = $this->user();
        /** @var array<string, bool> $touredPages */
        $touredPages = $user->toured_pages ?? [];
        $touredPages[$this->pageId] = true;
        $user->update(['toured_pages' => $touredPages]);
        $this->showTour = false;
    }

    #[On('start-tour')]
    public function startTour(): void
    {
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
