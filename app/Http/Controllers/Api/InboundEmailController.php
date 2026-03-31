<?php

namespace App\Http\Controllers\Api;

use App\Enums\ImportSource;
use App\Enums\ImportStatus;
use App\Enums\InboundEmailStatus;
use App\Enums\StatementType;
use App\Enums\UserRole;
use App\Jobs\ProcessImportedFile;
use App\Mail\DuplicateImportMail;
use App\Models\Company;
use App\Models\ImportedFile;
use App\Models\InboundEmail;
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
        $messageId = $request->input('Message-Id');
        $from = $request->input('from', $request->input('sender'));
        $subject = $request->input('subject');
        $bodyText = $this->extractBodyText($request);

        $company = Company::query()->where('inbox_address', $recipient)->first();

        if (! $company) {
            InboundEmail::create([
                'company_id' => null,
                'message_id' => $messageId,
                'from_address' => $from,
                'subject' => $subject,
                'body_text' => $bodyText,
                'recipient' => $recipient,
                'attachment_count' => (int) $request->input('attachment-count', 0),
                'processed_count' => 0,
                'skipped_count' => 0,
                'status' => InboundEmailStatus::Rejected,
                'rejection_reason' => 'Unknown inbox address',
                'received_at' => now(),
            ]);

            return response()->json(['error' => 'Unknown recipient'], 404);
        }

        if ($messageId && $this->isDuplicate($company, $messageId)) {
            InboundEmail::create([
                'company_id' => $company->id,
                'message_id' => $messageId,
                'from_address' => $from,
                'subject' => $subject,
                'body_text' => $bodyText,
                'recipient' => $recipient,
                'attachment_count' => (int) $request->input('attachment-count', 0),
                'processed_count' => 0,
                'skipped_count' => 0,
                'status' => InboundEmailStatus::Duplicate,
                'rejection_reason' => 'Duplicate message_id',
                'received_at' => now(),
            ]);

            $this->notifySenderOfDuplicate(
                senderFrom: $from,
                filename: null,
                company: $company,
            );

            return response()->json(['status' => 'ok', 'files_processed' => 0]);
        }

        $attachments = $this->extractAttachments($request);
        $totalAttachments = (int) $request->input('attachment-count', count($attachments));

        $inboundEmail = InboundEmail::create([
            'company_id' => $company->id,
            'message_id' => $messageId,
            'from_address' => $from,
            'subject' => $subject,
            'body_text' => $bodyText,
            'recipient' => $recipient,
            'attachment_count' => $totalAttachments,
            'processed_count' => 0,
            'skipped_count' => 0,
            'status' => InboundEmailStatus::NoAttachments,
            'rejection_reason' => null,
            'received_at' => now(),
        ]);

        $metadata = [
            'message_id' => $messageId,
            'from' => $from,
            'subject' => $subject,
            'received_at' => now()->toIso8601String(),
            'body_text' => $bodyText,
        ];

        $filesProcessed = 0;
        $filesSkipped = 0;
        $admins = null;

        foreach ($attachments as $attachment) {
            $importedFile = $this->storeAttachment($attachment, $company, $metadata, $inboundEmail);

            if ($importedFile === null) {
                $filesSkipped++;

                continue;
            }

            if ($importedFile->status !== ImportStatus::Duplicate) {
                $filesProcessed++;
                ProcessImportedFile::dispatch($importedFile);
                $admins ??= $company->users()->wherePivot('role', UserRole::Admin->value)->get();
                $this->notifyAdmins($company, $admins, $importedFile, $metadata);
            }
        }

        $skippedCount = max(0, $totalAttachments - count($attachments));

        $inboundEmail->update([
            'processed_count' => $filesProcessed,
            'skipped_count' => $skippedCount + $filesSkipped,
            'status' => $filesProcessed > 0 ? InboundEmailStatus::Processed : InboundEmailStatus::NoAttachments,
        ]);

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
        return InboundEmail::query()
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
        InboundEmail $inboundEmail,
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
                'inbound_email_id' => $inboundEmail->id,
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
                'inbound_email_id' => $inboundEmail->id,
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
