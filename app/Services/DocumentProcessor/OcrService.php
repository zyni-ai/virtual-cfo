<?php

namespace App\Services\DocumentProcessor;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class OcrService
{
    /**
     * Extract text from a PDF file using Mistral's OCR API.
     *
     * @return string Extracted markdown text from all pages
     */
    public function extractText(string $storagePath): string
    {
        $fileContent = Storage::disk('local')->get($storagePath);

        if ($fileContent === null) {
            throw new RuntimeException("File not found: {$storagePath}");
        }

        $base64 = base64_encode($fileContent);
        $mimeType = $this->detectMimeType($storagePath);

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.config('ai.providers.mistral.key'),
            'Content-Type' => 'application/json',
        ])->timeout(120)->post('https://api.mistral.ai/v1/ocr', [
            'model' => config('ai.models.ocr', 'mistral-ocr-latest'),
            'document' => [
                'type' => 'base64',
                'base64' => $base64,
                'mime_type' => $mimeType,
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Mistral OCR failed: '.$response->body()
            );
        }

        $pages = $response->json('pages', []);

        if (empty($pages)) {
            throw new RuntimeException('OCR returned no pages.');
        }

        /** @var array<int, array{markdown?: string}> $pages */
        return collect($pages)
            ->pluck('markdown')
            ->filter()
            ->join("\n\n");
    }

    protected function detectMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'application/pdf',
        };
    }
}
