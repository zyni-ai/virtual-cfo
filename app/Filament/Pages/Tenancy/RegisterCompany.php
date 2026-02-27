<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Company;
use App\Support\GstinValidator;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\RegisterTenant;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class RegisterCompany extends RegisterTenant
{
    public static function getLabel(): string
    {
        return 'Register company';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('gstin')
                    ->label('GSTIN')
                    ->rules(['nullable', fn () => function (string $attribute, mixed $value, \Closure $fail) {
                        if ($value && ! GstinValidator::isValid($value)) {
                            $fail('The GSTIN format is invalid.');
                        }
                    }]),
                Select::make('state')
                    ->options([
                        'Andhra Pradesh' => 'Andhra Pradesh',
                        'Arunachal Pradesh' => 'Arunachal Pradesh',
                        'Assam' => 'Assam',
                        'Bihar' => 'Bihar',
                        'Chhattisgarh' => 'Chhattisgarh',
                        'Delhi' => 'Delhi',
                        'Goa' => 'Goa',
                        'Gujarat' => 'Gujarat',
                        'Haryana' => 'Haryana',
                        'Himachal Pradesh' => 'Himachal Pradesh',
                        'Jharkhand' => 'Jharkhand',
                        'Karnataka' => 'Karnataka',
                        'Kerala' => 'Kerala',
                        'Madhya Pradesh' => 'Madhya Pradesh',
                        'Maharashtra' => 'Maharashtra',
                        'Manipur' => 'Manipur',
                        'Meghalaya' => 'Meghalaya',
                        'Mizoram' => 'Mizoram',
                        'Nagaland' => 'Nagaland',
                        'Odisha' => 'Odisha',
                        'Punjab' => 'Punjab',
                        'Rajasthan' => 'Rajasthan',
                        'Sikkim' => 'Sikkim',
                        'Tamil Nadu' => 'Tamil Nadu',
                        'Telangana' => 'Telangana',
                        'Tripura' => 'Tripura',
                        'Uttar Pradesh' => 'Uttar Pradesh',
                        'Uttarakhand' => 'Uttarakhand',
                        'West Bengal' => 'West Bengal',
                    ])
                    ->searchable(),
                Select::make('gst_registration_type')
                    ->label('GST Registration Type')
                    ->options([
                        'Regular' => 'Regular',
                        'Composition' => 'Composition',
                        'Unregistered' => 'Unregistered',
                    ])
                    ->default('Regular'),
                TextInput::make('financial_year')
                    ->placeholder('2025-2026'),
                Select::make('currency')
                    ->options([
                        'INR' => 'INR',
                        'USD' => 'USD',
                        'EUR' => 'EUR',
                        'GBP' => 'GBP',
                    ])
                    ->default('INR'),
            ]);
    }

    protected function handleRegistration(array $data): Company
    {
        $company = Company::create($data);

        $company->users()->attach(auth()->user());

        $company->update([
            'inbox_address' => $this->generateInboxAddress($company),
        ]);

        return $company;
    }

    protected function generateInboxAddress(Company $company): string
    {
        $slug = Str::slug($company->name);
        $hash = substr(hash_hmac('sha256', (string) $company->id, config('app.key')), 0, 6);
        $domain = config('services.mailgun.inbox_domain');

        return "{$slug}-{$hash}@{$domain}";
    }
}
