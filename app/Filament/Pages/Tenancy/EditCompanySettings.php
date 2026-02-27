<?php

namespace App\Filament\Pages\Tenancy;

use App\Enums\ConnectorProvider;
use App\Models\Connector;
use App\Services\Connectors\ZohoInvoiceService;
use App\Support\GstinValidator;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EditCompanySettings extends EditTenantProfile
{
    /**
     * @var array<string, string>
     */
    protected $queryString = [
        'zohoStatus' => ['as' => 'zoho_status', 'except' => ''],
        'zohoError' => ['as' => 'zoho_error', 'except' => ''],
    ];

    public string $zohoStatus = '';

    public string $zohoError = '';

    public function mount(): void
    {
        parent::mount();

        if ($this->zohoStatus === 'connected') {
            Notification::make()
                ->title('Zoho Invoice connected successfully.')
                ->success()
                ->send();
        }

        if ($this->zohoError !== '') {
            Notification::make()
                ->title($this->zohoError)
                ->danger()
                ->send();
        }
    }

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

                Section::make('Integrations')
                    ->schema(fn () => $this->getIntegrationsSchema()),
            ]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('connectZoho')
                ->label('Connect Zoho Invoice')
                ->url(fn () => route('connectors.zoho.redirect', ['company' => Filament::getTenant()]))
                ->color('primary')
                ->visible(fn () => $this->getZohoConnector() === null),

            Action::make('syncZoho')
                ->label('Sync Now')
                ->color('primary')
                ->requiresConfirmation()
                ->modalDescription('This will sync invoices from Zoho Invoice. This may take a moment.')
                ->visible(fn () => $this->getZohoConnector() !== null)
                ->action(function (): void {
                    $connector = $this->getZohoConnector();

                    if (! $connector) {
                        return;
                    }

                    try {
                        $service = app(ZohoInvoiceService::class);
                        $count = $service->syncForCompany($connector);

                        Notification::make()
                            ->title("Synced {$count} invoices from Zoho.")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Sync failed: '.$e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('disconnectZoho')
                ->label('Disconnect Zoho')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Disconnect Zoho Invoice')
                ->modalDescription('This will revoke access to your Zoho Invoice account. Previously imported invoices will not be affected.')
                ->visible(fn () => $this->getZohoConnector() !== null)
                ->action(function (): void {
                    $connector = $this->getZohoConnector();

                    if (! $connector) {
                        return;
                    }

                    $connector->update(['is_active' => false]);
                    $connector->delete();

                    Notification::make()
                        ->title('Zoho Invoice disconnected.')
                        ->success()
                        ->send();
                }),
        ];
    }

    /**
     * @return array<int, \Filament\Schemas\Components\Component>
     */
    protected function getIntegrationsSchema(): array
    {
        $connector = $this->getZohoConnector();

        if (! $connector) {
            return [
                TextEntry::make('zoho_status')
                    ->label('Zoho Invoice')
                    ->state('Not connected')
                    ->badge()
                    ->color('gray'),
            ];
        }

        return [
            TextEntry::make('zoho_status')
                ->label('Zoho Invoice')
                ->state('Connected')
                ->badge()
                ->color('success'),
            TextEntry::make('zoho_last_synced')
                ->label('Last Synced')
                ->state(fn (): string => $connector->last_synced_at?->diffForHumans() ?? 'Never'),
        ];
    }

    protected function getZohoConnector(): ?Connector
    {
        /** @var \App\Models\Company|null $company */
        $company = Filament::getTenant();

        return $company
            ?->connectors()
            ->where('provider', ConnectorProvider::Zoho)
            ->where('is_active', true)
            ->first();
    }
}
