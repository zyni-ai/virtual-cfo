<?php

namespace App\Http\Controllers\Api;

use App\Enums\ImportSource;
use App\Enums\ImportStatus;
use App\Enums\StatementType;
use App\Jobs\ProcessImportedFile;
use App\Models\Company;
use App\Models\ImportedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class InboundEmailController
{
    /** @var array<int, string> */
    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
    ];

    public function __invoke(Request $request): JsonResponse
    {
        $recipient = $request->input('recipient');
        $company = Company::query()->where('inbox_address', $recipient)->first();

        if (! $company) {
            return response()->json(['error' => 'Unknown recipient'], 404);
        }

        $messageId = $request->input('Message-Id');

        if ($messageId && $this->isDuplicate($messageId)) {
            return response()->json(['status' => 'ok', 'files_processed' => 0]);
        }

        $metadata = [
            'message_id' => $messageId,
            'from' => $request->input('from', $request->input('sender')),
            'subject' => $request->input('subject'),
            'received_at' => now()->toIso8601String(),
        ];

        $filesProcessed = 0;
        $attachments = $this->extractAttachments($request);

        foreach ($attachments as $attachment) {
            $importedFile = $this->storeAttachment($attachment, $company, $metadata);

            if ($importedFile) {
                $filesProcessed++;

                /** @var ImportStatus $status */
                $status = $importedFile->status;

                if ($status !== ImportStatus::Skipped) {
                    ProcessImportedFile::dispatch($importedFile);
                }
            }
        }

        return response()->json([
            'status' => 'ok',
            'files_processed' => $filesProcessed,
        ]);
    }

    private function isDuplicate(string $messageId): bool
    {
        return ImportedFile::query()
            ->where('source', ImportSource::Email)
            ->whereRaw("source_metadata IS NOT NULL AND source_metadata != ''")
            ->get()
            ->contains(function (ImportedFile $file) use ($messageId): bool {
                /** @var array<string, mixed>|null $metadata */
                $metadata = $file->source_metadata;

                return ($metadata['message_id'] ?? null) === $messageId;
            });
    }

    /**
     * @return array<int, UploadedFile>
     */
    private function extractAttachments(Request $request): array
    {
        $attachments = [];
        $count = (int) $request->input('attachment-count', 0);

        for ($i = 1; $i <= max($count, 10); $i++) {
            $file = $request->file("attachment-{$i}");

            if (! $file instanceof UploadedFile) {
                if ($count > 0) {
                    continue;
                }
                break;
            }

            if (in_array($file->getMimeType(), self::ALLOWED_MIMES, true)) {
                $attachments[] = $file;
            }
        }

        return $attachments;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function storeAttachment(
        UploadedFile $file,
        Company $company,
        array $metadata,
    ): ?ImportedFile {
        $contents = $file->getContent();
        $fileHash = hash('sha256', $contents);

        if (ImportedFile::query()->where('file_hash', $fileHash)->exists()) {
            return null;
        }

        $extension = $file->getClientOriginalExtension() ?: 'pdf';
        $storagePath = 'statements/'.uniqid('email_', true).'.'.$extension;

        Storage::disk('local')->put($storagePath, $contents);

        $filename = $file->getClientOriginalName();
        $classification = $this->classifyByFilename($filename);

        $attributes = [
            'company_id' => $company->id,
            'file_path' => $storagePath,
            'original_filename' => $filename,
            'file_hash' => $fileHash,
            'source' => ImportSource::Email,
            'source_metadata' => $metadata,
        ];

        if ($classification === null) {
            return ImportedFile::create($attributes + [
                'statement_type' => StatementType::Invoice,
                'status' => ImportStatus::Skipped,
                'error_message' => "Filename does not appear to be an invoice or statement: {$filename}",
            ]);
        }

        return ImportedFile::create($attributes + [
            'statement_type' => $classification,
            'status' => ImportStatus::Pending,
        ]);
    }

    /**
     * Classify an attachment by its filename to determine the statement type.
     * Returns null if the filename doesn't match any known invoice or statement pattern.
     */
    private function classifyByFilename(string $filename): ?StatementType
    {
        $name = strtolower(pathinfo($filename, PATHINFO_FILENAME));

        $invoicePatterns = ['inv', 'invoice', 'tax[_\-\s]?invoice', 'bill', 'debit[_\-\s]?note', 'credit[_\-\s]?note'];
        foreach ($invoicePatterns as $pattern) {
            if (preg_match('/(?:^|[\W_])'.$pattern.'(?:$|[\W_])/', $name)) {
                return StatementType::Invoice;
            }
        }

        $statementPatterns = ['statement', 'bank[_\-\s]?statement', 'account[_\-\s]?statement'];
        foreach ($statementPatterns as $pattern) {
            if (preg_match('/(?:^|[\W_])'.$pattern.'(?:$|[\W_])/', $name)) {
                return StatementType::Bank;
            }
        }

        return null;
    }
}
