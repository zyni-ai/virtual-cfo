<?php

namespace App\Mail;

use App\Models\Invitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Invitation $invitation) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "You've been invited to {$this->invitation->company->name}",
        );
    }

    public function content(): Content
    {
        $this->invitation->loadMissing(['company', 'inviter']);

        return new Content(
            markdown: 'emails.invitation',
            with: [
                'acceptUrl' => route('invitations.accept', $this->invitation->token),
                'companyName' => $this->invitation->company->name,
                'role' => $this->invitation->role->getLabel(),
                'inviterName' => $this->invitation->inviter->name,
                'expiresAt' => $this->invitation->expires_at->format('d M Y'),
            ],
        );
    }
}
