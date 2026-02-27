<?php

namespace App\Filament\Pages\Tenancy;

use App\Support\GstinValidator;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EditCompanySettings extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Company settings';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Company Details')
                    ->schema([
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
                    ]),

                Section::make('Email Forwarding')
                    ->schema([
                        TextInput::make('inbox_address')
                            ->label('Inbox Address')
                            ->helperText('Forward invoices to this email address for automatic processing.')
                            ->disabled()
                            ->placeholder('Generated on company registration'),
                    ]),
            ]);
    }
}
