<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DuplicateImportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $filename,
        public string $companyName,
        public string $originalImportedAt,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Duplicate attachment detected — no action taken',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.duplicate-import',
            with: [
                'filename' => $this->filename,
                'companyName' => $this->companyName,
                'originalImportedAt' => $this->originalImportedAt,
            ],
        );
    }
}
