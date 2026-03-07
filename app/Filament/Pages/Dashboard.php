<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
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
}
