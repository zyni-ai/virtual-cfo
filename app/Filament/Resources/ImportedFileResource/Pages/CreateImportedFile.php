<?php

namespace App\Filament\Resources\ImportedFileResource\Pages;

use App\Enums\ImportStatus;
use App\Filament\Resources\ImportedFileResource;
use App\Jobs\ProcessImportedFile;
use App\Models\ImportedFile;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CreateImportedFile extends CreateRecord
{
    protected static string $resource = ImportedFileResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['uploaded_by'] = Auth::id();
        $data['status'] = ImportStatus::Pending;

        // Generate file hash for duplicate detection
        $filePath = $data['file_path'];
        if (Storage::disk('local')->exists($filePath)) {
            $data['file_hash'] = hash('sha256', Storage::disk('local')->get($filePath));
        }

        // Store original filename
        $data['original_filename'] = basename($filePath);

        return $data;
    }

    protected function afterCreate(): void
    {
        ProcessImportedFile::dispatch($this->record);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
