<?php

namespace App\Http\Controllers\Api;

use App\Enums\ImportSource;
use App\Enums\ImportStatus;
use App\Enums\StatementType;
use App\Enums\UserRole;
use App\Jobs\ProcessImportedFile;
use App\Mail\DuplicateImportMail;
use App\Models\Company;
use App\Models\ImportedFile;
use App\Models\User;
use App\Notifications\StatementReceivedByEmailNotification;
use App\Services\StatementClassifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
            $original = ImportedFile::query()
                ->where('company_id', $company->id)
                ->where('message_id', $messageId)
                ->first();

            $this->notifySenderOfDuplicate(
                senderFrom: $request->input('from', $request->input('sender')),
                filename: null,
                company: $company,
                original: $original,
            );

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
        $admins = null;

        foreach ($attachments as $attachment) {
            $importedFile = $this->storeAttachment($attachment, $company, $metadata);

            if ($importedFile === null) {
                continue;
            }

            if ($importedFile->status !== ImportStatus::Duplicate) {
                $filesProcessed++;
                ProcessImportedFile::dispatch($importedFile);
                $admins ??= $company->users()->wherePivot('role', UserRole::Admin->value)->get();
                $this->notifyAdmins($company, $admins, $importedFile, $metadata);
            }
        }

        return response()->json([
            'status' => 'ok',
            'files_processed' => $filesProcessed,
        ]);
    }

    /**
     * @param  Collection<int, User>  $admins
     * @param  array<string, mixed>  $metadata
     */
    private function notifyAdmins(Company $company, Collection $admins, ImportedFile $importedFile, array $metadata): void
    {
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

    private function notifySenderOfDuplicate(
        ?string $senderFrom,
        ?string $filename,
        Company $company,
        ?ImportedFile $original = null,
    ): void {
        $senderEmail = $this->extractEmail($senderFrom);

        if (! $senderEmail) {
            return;
        }

        Mail::to($senderEmail)->queue(new DuplicateImportMail(
            filename: $filename ?? $original?->original_filename ?? 'unknown',
            companyName: $company->name,
            originalImportedAt: $original?->created_at->toFormattedDateString() ?? now()->toFormattedDateString(),
        ));
    }

    private function extractEmail(?string $from): ?string
    {
        if (! $from) {
            return null;
        }

        if (preg_match('/<([^>]+)>/', $from, $matches)) {
            return $matches[1];
        }

        return filter_var($from, FILTER_VALIDATE_EMAIL) ? $from : null;
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

        $original = ImportedFile::query()
            ->where('company_id', $company->id)
            ->where('file_hash', $fileHash)
            ->first();

        if ($original) {
            $filename = $file->getClientOriginalName();

            $this->notifySenderOfDuplicate(
                senderFrom: $metadata['from'] ?? null,
                filename: $filename,
                company: $company,
                original: $original,
            );

            return ImportedFile::create([
                'company_id' => $company->id,
                'file_path' => $original->file_path,
                'original_filename' => $filename,
                'file_hash' => $fileHash,
                'source' => ImportSource::Email,
                'source_metadata' => $metadata,
                'message_id' => $metadata['message_id'] ?? null,
                'statement_type' => $original->statement_type,
                'status' => ImportStatus::Duplicate,
                'error_message' => "Duplicate of file imported on {$original->created_at->toDateString()}.",
            ]);
        }

        $extension = $file->getClientOriginalExtension() ?: 'pdf';
        $storagePath = 'statements/'.Str::ulid().'.'.$extension;
        $originalFilename = $file->getClientOriginalName();
        $classification = $this->classifier->classify($metadata, $originalFilename);

        try {
            $importedFile = DB::transaction(fn () => ImportedFile::create([
                'company_id' => $company->id,
                'file_path' => $storagePath,
                'original_filename' => basename($storagePath),
                'file_hash' => $fileHash,
                'source' => ImportSource::Email,
                'source_metadata' => $metadata,
                'message_id' => $metadata['message_id'] ?? null,
                'statement_type' => $classification ?? StatementType::Invoice,
                'status' => ImportStatus::Pending,
            ]));
        } catch (UniqueConstraintViolationException) {
            return null;
        }

        Storage::disk('local')->put($storagePath, $contents);

        return $importedFile;
    }
}
