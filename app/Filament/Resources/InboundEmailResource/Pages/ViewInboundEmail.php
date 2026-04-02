<?php

namespace App\Filament\Resources\InboundEmailResource\Pages;

use App\Filament\Resources\InboundEmailResource;
use App\Models\InboundEmail;
use Filament\Infolists;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** @property InboundEmail $record */
class ViewInboundEmail extends ViewRecord
{
    protected static string $resource = InboundEmailResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Email Details')
                    ->schema([
                        Infolists\Components\TextEntry::make('received_at')
                            ->label('Received At')
                            ->dateTime('d M Y H:i:s'),

                        Infolists\Components\TextEntry::make('from_address')
                            ->label('From'),

                        Infolists\Components\TextEntry::make('subject')
                            ->label('Subject'),

                        Infolists\Components\TextEntry::make('recipient')
                            ->label('Recipient'),

                        Infolists\Components\TextEntry::make('status')
                            ->label('Status')
                            ->badge(),

                        Infolists\Components\TextEntry::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->placeholder('—'),
                    ])
                    ->columns(2),

                Section::make('Attachment Summary')
                    ->schema([
                        Infolists\Components\TextEntry::make('attachment_count')
                            ->label('Total Attachments'),

                        Infolists\Components\TextEntry::make('processed_count')
                            ->label('Processed'),

                        Infolists\Components\TextEntry::make('skipped_count')
                            ->label('Skipped'),
                    ])
                    ->columns(3),

                Section::make('Email Body')
                    ->schema([
                        Infolists\Components\TextEntry::make('body_text')
                            ->label('Body Text')
                            ->placeholder('No body text captured')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
