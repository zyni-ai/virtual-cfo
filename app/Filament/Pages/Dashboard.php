<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    public function getHeading(): string
    {
        return 'Welcome, '.Auth::user()->name.'!';
    }

    public function getSubheading(): ?string
    {
        return 'Here\'s an overview of your financial data.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('page_tour')
                ->label('Page Tour')
                ->icon('heroicon-o-academic-cap')
                ->color('gray')
                ->extraAttributes([
                    'x-on:click.prevent' => "\$dispatch('start-page-tour')",
                ]),
        ];
    }

    public function getFooter(): ?View
    {
        return view('livewire.page-tour-embed', ['pageId' => 'dashboard']);
    }
}
