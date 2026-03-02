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

        if ($messageId && $this->isDuplicate($company, $messageId)) {
            return response()->json(['status' => 'ok', 'files_processed' => 0]);
        }

        $metadata = [
            'message_id' => $messageId,
            'from' => $request->input('from', $request->input('sender')),
            'subject' => $request->input('subject'),
            'received_at' => now()->toIso8601String(),
            'body_text' => $this->extractBodyText($request),
        ];

        $filesProcessed = 0;
        $attachments = $this->extractAttachments($request);

        foreach ($attachments as $attachment) {
            $importedFile = $this->storeAttachment($attachment, $company, $metadata);

            if ($importedFile) {
                $filesProcessed++;

                if ($importedFile->status !== ImportStatus::Skipped) {
                    ProcessImportedFile::dispatch($importedFile);
                }
            }
        }

        return response()->json([
            'status' => 'ok',
            'files_processed' => $filesProcessed,
        ]);
    }

    private function extractBodyText(Request $request): ?string
    {
        $body = $request->input('stripped-text') ?? $request->input('body-plain');

        if ($body === null) {
            return null;
        }

        return mb_substr((string) $body, 0, 2000);
    }

    private function isDuplicate(Company $company, string $messageId): bool
    {
        return ImportedFile::query()
            ->where('company_id', $company->id)
            ->where('message_id', $messageId)
            ->exists();
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

        if (ImportedFile::query()->where('company_id', $company->id)->where('file_hash', $fileHash)->exists()) {
            return null;
        }

        $extension = $file->getClientOriginalExtension() ?: 'pdf';
        $storagePath = 'statements/'.uniqid('email_', true).'.'.$extension;

        Storage::disk('local')->put($storagePath, $contents);

        $filename = $file->getClientOriginalName();
        $classification = $this->classifyByEmailContext($metadata) ?? $this->classifyByFilename($filename);

        $attributes = [
            'company_id' => $company->id,
            'file_path' => $storagePath,
            'original_filename' => $filename,
            'file_hash' => $fileHash,
            'source' => ImportSource::Email,
            'source_metadata' => $metadata,
            'message_id' => $metadata['message_id'] ?? null,
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
     * Classify using email subject and body text.
     * Returns null if no classification signals are found in the email context.
     *
     * @param  array<string, mixed>  $metadata
     */
    private function classifyByEmailContext(array $metadata): ?StatementType
    {
        $text = strtolower(trim(($metadata['subject'] ?? '').' '.($metadata['body_text'] ?? '')));

        if ($text === '') {
            return null;
        }

        return $this->classifyText($text);
    }

    /**
     * Classify an attachment by its filename to determine the statement type.
     * Returns null if the filename doesn't match any known pattern.
     */
    private function classifyByFilename(string $filename): ?StatementType
    {
        $name = strtolower(pathinfo($filename, PATHINFO_FILENAME));

        return $this->classifyText($name);
    }

    /**
     * Classify text (email context or filename) into a statement type.
     * Credit card patterns are checked before generic statement patterns
     * so "Credit Card Statement" matches CreditCard, not Bank.
     */
    private function classifyText(string $text): ?StatementType
    {
        $patternMap = [
            [StatementType::Invoice, ['inv', 'invoice', 'tax[_\-\s]?invoice', 'bill', 'debit[_\-\s]?note', 'credit[_\-\s]?note']],
            [StatementType::CreditCard, ['credit[_\-\s]?card', 'cc[_\-\s]?statement']],
            [StatementType::Bank, ['statement', 'bank[_\-\s]?statement', 'account[_\-\s]?statement']],
        ];

        foreach ($patternMap as [$type, $patterns]) {
            if ($this->matchesAny($text, $patterns)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $patterns
     */
    private function matchesAny(string $text, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match('/(?:^|[\W_])'.$pattern.'(?:$|[\W_\d])/', $text)) {
                return true;
            }
        }

        return false;
    }
}
