<?php

namespace App\Http\Controllers;

use App\Models\ImportedFile;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ImportedFileDownloadController
{
    use AuthorizesRequests;

    public function __invoke(ImportedFile $importedFile): StreamedResponse
    {
        $this->authorize('view', $importedFile);

        $disk = Storage::disk('local');

        if (! $disk->exists($importedFile->file_path)) {
            throw new NotFoundHttpException('File not found on disk.');
        }

        $mimeType = $disk->mimeType($importedFile->file_path) ?: 'application/octet-stream';

        return $disk->download(
            $importedFile->file_path,
            $importedFile->original_filename,
            ['Content-Type' => $mimeType]
        );
    }
}
