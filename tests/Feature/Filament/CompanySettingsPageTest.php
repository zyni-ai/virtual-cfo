<?php

use App\Filament\Pages\CompanySettings;

describe('CompanySettings Page', function () {
    beforeEach(function () {
        asUser();
    });

    it('can render the company settings page', function () {
        $this->get(CompanySettings::getUrl())
            ->assertSuccessful();
    });

    it('displays the company name', function () {
        config(['company.name' => 'Test Company']);

        $this->get(CompanySettings::getUrl())
            ->assertSee('Test Company');
    });

    it('displays the GSTIN', function () {
        config(['company.gstin' => '29AABCZ5012F1ZG']);

        $this->get(CompanySettings::getUrl())
            ->assertSee('29AABCZ5012F1ZG');
    });

    it('displays all company configuration fields', function () {
        config([
            'company.name' => 'Zysk Technologies',
            'company.gstin' => '29AABCZ5012F1ZG',
            'company.state' => 'Karnataka',
            'company.gst_registration_type' => 'Regular',
            'company.financial_year' => '2025-2026',
            'company.currency' => 'INR',
        ]);

        $response = $this->get(CompanySettings::getUrl());

        $response->assertSee('Zysk Technologies')
            ->assertSee('29AABCZ5012F1ZG')
            ->assertSee('Karnataka')
            ->assertSee('Regular')
            ->assertSee('2025-2026')
            ->assertSee('INR');
    });
});
