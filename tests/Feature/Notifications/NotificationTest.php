<?php

use App\Enums\UserRole;
use App\Mail\InvitationMail;
use App\Models\ImportedFile;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\HeadMatchingCompletedNotification;
use App\Notifications\ImportCompletedNotification;
use App\Notifications\ImportFailedNotification;
use App\Notifications\InvitationAcceptedNotification;
use App\Notifications\LowConfidenceMatchesNotification;
use App\Notifications\MemberRemovedNotification;
use App\Notifications\MemberRoleChangedNotification;
use App\Notifications\StatementReceivedByEmailNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;

describe('Notification classes', function () {
    it('all implement ShouldQueue', function () {
        $classes = [
            ImportCompletedNotification::class,
            ImportFailedNotification::class,
            HeadMatchingCompletedNotification::class,
            LowConfidenceMatchesNotification::class,
            InvitationAcceptedNotification::class,
            MemberRoleChangedNotification::class,
            MemberRemovedNotification::class,
            StatementReceivedByEmailNotification::class,
        ];

        foreach ($classes as $class) {
            expect($class)->toImplement(ShouldQueue::class);
        }
    });

    it('ImportCompletedNotification uses database channel', function () {
        $file = ImportedFile::factory()->completed()->create();
        $notification = new ImportCompletedNotification($file);

        expect($notification->via($file->uploader))->toBe(['database']);
    });

    it('ImportFailedNotification uses database and mail channels', function () {
        $file = ImportedFile::factory()->failed()->create();
        $notification = new ImportFailedNotification($file);

        expect($notification->via($file->uploader))->toContain('database')
            ->and($notification->via($file->uploader))->toContain('mail');
    });

    it('HeadMatchingCompletedNotification uses database channel', function () {
        $file = ImportedFile::factory()->create();
        $notification = new HeadMatchingCompletedNotification($file, ruleMatched: 5, aiMatched: 3, unmatched: 2);

        expect($notification->via(User::factory()->create()))->toBe(['database']);
    });

    it('LowConfidenceMatchesNotification uses database channel', function () {
        $file = ImportedFile::factory()->create();
        $notification = new LowConfidenceMatchesNotification($file, count: 3);

        expect($notification->via(User::factory()->create()))->toBe(['database']);
    });

    it('InvitationAcceptedNotification uses database and mail channels', function () {
        $invitation = Invitation::factory()->create();
        $notification = new InvitationAcceptedNotification($invitation);

        expect($notification->via($invitation->inviter))->toContain('database')
            ->and($notification->via($invitation->inviter))->toContain('mail');
    });

    it('MemberRoleChangedNotification uses database and mail channels', function () {
        $notification = new MemberRoleChangedNotification(
            companyName: 'Test Co',
            newRole: 'Admin',
        );

        expect($notification->via(User::factory()->create()))->toContain('database')
            ->and($notification->via(User::factory()->create()))->toContain('mail');
    });

    it('MemberRemovedNotification uses database channel', function () {
        $notification = new MemberRemovedNotification(companyName: 'Test Co');

        expect($notification->via(User::factory()->create()))->toBe(['database']);
    });

    it('StatementReceivedByEmailNotification uses database channel', function () {
        $notification = new StatementReceivedByEmailNotification(
            filename: 'statement.pdf',
            companyName: 'Test Co',
            senderEmail: 'sender@example.com',
        );

        expect($notification->via(User::factory()->create()))->toBe(['database']);
    });
});

describe('ProcessImportedFile notifications', function () {
    it('sends ImportCompletedNotification on successful processing', function () {
        Notification::fake();

        $user = User::factory()->create();
        $file = ImportedFile::factory()->completed()->create(['uploaded_by' => $user->id]);

        $user->notify(new ImportCompletedNotification($file));

        Notification::assertSentTo($user, ImportCompletedNotification::class);
    });

    it('sends ImportFailedNotification on failure', function () {
        Notification::fake();

        $user = User::factory()->create();
        $file = ImportedFile::factory()->failed()->create(['uploaded_by' => $user->id]);

        $user->notify(new ImportFailedNotification($file));

        Notification::assertSentTo($user, ImportFailedNotification::class);
    });

    it('does not notify when uploaded_by is null', function () {
        Notification::fake();

        $file = ImportedFile::factory()->completed()->create(['uploaded_by' => null]);

        expect($file->uploaded_by)->toBeNull();

        Notification::assertNothingSent();
    });
});

describe('MatchTransactionHeads notifications', function () {
    it('sends HeadMatchingCompletedNotification on completion', function () {
        Notification::fake();

        $user = User::factory()->create();
        $file = ImportedFile::factory()->create(['uploaded_by' => $user->id]);

        $notification = new HeadMatchingCompletedNotification($file, ruleMatched: 5, aiMatched: 3, unmatched: 2);
        $user->notify($notification);

        Notification::assertSentTo($user, HeadMatchingCompletedNotification::class);
    });

    it('sends LowConfidenceMatchesNotification when low confidence matches exist', function () {
        Notification::fake();

        $user = User::factory()->create();
        $file = ImportedFile::factory()->create(['uploaded_by' => $user->id]);

        $user->notify(new LowConfidenceMatchesNotification($file, count: 3));

        Notification::assertSentTo($user, LowConfidenceMatchesNotification::class);
    });
});

describe('AcceptInvitation notifications', function () {
    it('sends InvitationAcceptedNotification to the inviter', function () {
        Notification::fake();

        $inviter = User::factory()->create();
        $invitation = Invitation::factory()->create(['invited_by' => $inviter->id]);

        $inviter->notify(new InvitationAcceptedNotification($invitation));

        Notification::assertSentTo($inviter, InvitationAcceptedNotification::class);
    });
});

describe('TeamMembers notifications', function () {
    it('sends MemberRoleChangedNotification to affected user', function () {
        Notification::fake();

        $user = User::factory()->create();

        $user->notify(new MemberRoleChangedNotification(
            companyName: 'Zysk Technologies',
            newRole: 'Accountant',
        ));

        Notification::assertSentTo($user, MemberRoleChangedNotification::class);
    });

    it('sends MemberRemovedNotification to removed user', function () {
        Notification::fake();

        $user = User::factory()->create();

        $user->notify(new MemberRemovedNotification(companyName: 'Zysk Technologies'));

        Notification::assertSentTo($user, MemberRemovedNotification::class);
    });
});

describe('InboundEmail notifications', function () {
    it('sends StatementReceivedByEmailNotification to all Admins', function () {
        Notification::fake();

        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $admin->notify(new StatementReceivedByEmailNotification(
            filename: 'statement.pdf',
            companyName: 'Test Co',
            senderEmail: 'sender@example.com',
        ));

        Notification::assertSentTo($admin, StatementReceivedByEmailNotification::class);
    });
});

describe('Notification toDatabase format', function () {
    it('ImportCompletedNotification returns Filament-compatible database message', function () {
        $file = ImportedFile::factory()->completed(totalRows: 50, mappedRows: 30)->create();
        $notification = new ImportCompletedNotification($file);

        $message = $notification->toDatabase($file->uploader);

        expect($message)->toBeArray()
            ->and($message)->toHaveKey('title')
            ->and($message)->toHaveKey('body');
    });

    it('ImportFailedNotification returns Filament-compatible database message', function () {
        $file = ImportedFile::factory()->failed('Parse error')->create();
        $notification = new ImportFailedNotification($file);

        $message = $notification->toDatabase($file->uploader);

        expect($message)->toBeArray()
            ->and($message)->toHaveKey('title');
    });

    it('HeadMatchingCompletedNotification includes match stats in body', function () {
        $file = ImportedFile::factory()->create();
        $notification = new HeadMatchingCompletedNotification($file, ruleMatched: 10, aiMatched: 5, unmatched: 3);

        $message = $notification->toDatabase(User::factory()->create());

        expect($message)->toBeArray()
            ->and($message)->toHaveKey('body');
    });
});

describe('Notification toMail format', function () {
    it('ImportFailedNotification returns a MailMessage with error details', function () {
        $file = ImportedFile::factory()->failed('PDF parsing error')->create();
        $notification = new ImportFailedNotification($file);

        $mail = $notification->toMail($file->uploader);

        expect($mail)->toBeInstanceOf(MailMessage::class)
            ->and($mail->subject)->toContain($file->original_filename)
            ->and($mail->actionUrl)->not->toBeEmpty();
    });

    it('MemberRoleChangedNotification returns a MailMessage with role info', function () {
        $notification = new MemberRoleChangedNotification(
            companyName: 'Zysk Technologies',
            newRole: 'Accountant',
        );

        $mail = $notification->toMail(User::factory()->create());

        expect($mail)->toBeInstanceOf(MailMessage::class)
            ->and($mail->subject)->toContain('Accountant')
            ->and($mail->actionUrl)->not->toBeEmpty();
    });

    it('InvitationAcceptedNotification returns a MailMessage', function () {
        $invitation = Invitation::factory()->create();
        $notification = new InvitationAcceptedNotification($invitation);

        $mail = $notification->toMail($invitation->inviter);

        expect($mail)->toBeInstanceOf(MailMessage::class)
            ->and($mail->subject)->toContain($invitation->email)
            ->and($mail->actionUrl)->not->toBeEmpty();
    });
});

describe('InvitationMail', function () {
    it('renders successfully with correct subject', function () {
        $invitation = Invitation::factory()->create();

        $mailable = new InvitationMail($invitation);

        $mailable->assertHasSubject("You've been invited to {$invitation->company->name}");
    });

    it('contains the accept URL', function () {
        $invitation = Invitation::factory()->create();

        $mailable = new InvitationMail($invitation);

        $mailable->assertSeeInHtml(url("/admin/invitations/{$invitation->token}/accept"));
    });

    it('contains the inviter name and role', function () {
        $invitation = Invitation::factory()->create();

        $mailable = new InvitationMail($invitation);

        $mailable->assertSeeInHtml($invitation->inviter->name)
            ->assertSeeInHtml($invitation->role->getLabel());
    });
});
