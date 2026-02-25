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

        if (! Storage::disk('local')->exists($importedFile->file_path)) {
            throw new NotFoundHttpException('File not found on disk.');
        }

        return Storage::disk('local')->download(
            $importedFile->file_path,
            $importedFile->original_filename,
            ['Content-Type' => 'application/pdf']
        );
    }
}
