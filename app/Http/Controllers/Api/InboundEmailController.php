<?php

namespace App\Http\Controllers\Api;

use App\Enums\ImportSource;
use App\Enums\ImportStatus;
use App\Enums\StatementType;
use App\Jobs\ProcessImportedFile;
use App\Models\Company;
use App\Models\ImportedFile;
use App\Notifications\StatementReceivedByEmailNotification;
use App\Services\StatementClassifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

class InboundEmailController
{
    /** @var array<int, string> */
    private const ALLOWED_MIMES = [
        'application/pdf',
        'image/png',
        'image/jpeg',
    ];

    public function __construct(private readonly StatementClassifier $classifier) {}

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
                ProcessImportedFile::dispatch($importedFile);
                $this->notifyAdmins($company, $importedFile, $metadata);
            }
        }

        return response()->json([
            'status' => 'ok',
            'files_processed' => $filesProcessed,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function notifyAdmins(Company $company, ImportedFile $importedFile, array $metadata): void
    {
        $admins = $company->users()->wherePivot('role', 'admin')->get();

        if ($admins->isEmpty()) {
            return;
        }

        Notification::send($admins, new StatementReceivedByEmailNotification(
            filename: $importedFile->original_filename,
            companyName: $company->name,
            senderEmail: $metadata['from'] ?? null,
        ));
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
        $classification = $this->classifier->classify($metadata, $filename);

        $attributes = [
            'company_id' => $company->id,
            'file_path' => $storagePath,
            'original_filename' => $filename,
            'file_hash' => $fileHash,
            'source' => ImportSource::Email,
            'source_metadata' => $metadata,
            'message_id' => $metadata['message_id'] ?? null,
        ];

        return ImportedFile::create($attributes + [
            'statement_type' => $classification ?? StatementType::Invoice,
            'status' => ImportStatus::Pending,
        ]);
    }
}
