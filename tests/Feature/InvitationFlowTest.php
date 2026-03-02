<?php

use App\Enums\UserRole;
use App\Filament\Pages\TeamMembers;
use App\Mail\InvitationMail;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Livewire\livewire;

describe('Invitation Flow', function () {
    describe('Sending invitations', function () {
        it('admin can invite a new user via TeamMembers page', function () {
            Mail::fake();
            asUser(role: UserRole::Admin);
            $company = tenant();

            livewire(TeamMembers::class)
                ->callAction('invite', [
                    'email' => 'newuser@example.com',
                    'role' => UserRole::Viewer->value,
                ])
                ->assertNotified('Invitation sent');

            $invitation = Invitation::where('email', 'newuser@example.com')->first();
            expect($invitation)->not->toBeNull()
                ->and($invitation->company_id)->toBe($company->id)
                ->and($invitation->role)->toBe(UserRole::Viewer)
                ->and($invitation->expires_at->isFuture())->toBeTrue();

            Mail::assertQueued(InvitationMail::class, fn (InvitationMail $mail) => $mail->hasTo('newuser@example.com'));
        });

        it('warns when user is already a member', function () {
            Mail::fake();
            asUser(role: UserRole::Admin);
            $company = tenant();

            $existing = User::factory()->viewer()->create(['email' => 'member@example.com']);
            $company->users()->attach($existing, ['role' => UserRole::Viewer->value]);

            livewire(TeamMembers::class)
                ->callAction('invite', [
                    'email' => 'member@example.com',
                    'role' => UserRole::Viewer->value,
                ])
                ->assertNotified('Already a member');

            Mail::assertNothingSent();
        });

        it('resends invitation when duplicate pending invite exists', function () {
            Mail::fake();
            asUser(role: UserRole::Admin);
            $company = tenant();

            $invitation = Invitation::factory()->create([
                'company_id' => $company->id,
                'email' => 'pending@example.com',
                'invited_by' => auth()->id(),
                'expires_at' => now()->addDay(),
            ]);

            livewire(TeamMembers::class)
                ->callAction('invite', [
                    'email' => 'pending@example.com',
                    'role' => UserRole::Accountant->value,
                ])
                ->assertNotified('Invitation resent');

            expect($invitation->fresh()->role)->toBe(UserRole::Accountant)
                ->and($invitation->fresh()->expires_at->gt($invitation->expires_at))->toBeTrue();

            Mail::assertQueued(InvitationMail::class);
        });

        it('rate limits invitations to 10 per company per hour', function () {
            Mail::fake();
            asUser(role: UserRole::Admin);
            $company = tenant();

            $rateLimitKey = "invitations:{$company->id}";
            for ($i = 0; $i < 10; $i++) {
                RateLimiter::hit($rateLimitKey, 3600);
            }

            livewire(TeamMembers::class)
                ->callAction('invite', [
                    'email' => 'blocked@example.com',
                    'role' => UserRole::Viewer->value,
                ])
                ->assertNotified('Too many invitations');

            Mail::assertNothingSent();
            expect(Invitation::where('email', 'blocked@example.com')->exists())->toBeFalse();
        });
    });

    describe('Accepting invitations - new user', function () {
        it('shows registration form for new user', function () {
            $invitation = Invitation::factory()->create();

            $this->get(route('invitations.accept', $invitation->token))
                ->assertSuccessful()
                ->assertViewIs('invitations.accept')
                ->assertSee('Create Account');
        });

        it('creates account and attaches to company', function () {
            $invitation = Invitation::factory()->create([
                'role' => UserRole::Accountant,
            ]);

            $this->post(route('invitations.accept.new', $invitation->token), [
                'name' => 'New User',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])->assertRedirect('/admin/login');

            $user = User::where('email', $invitation->email)->first();
            expect($user)->not->toBeNull()
                ->and($user->name)->toBe('New User')
                ->and($invitation->fresh()->isAccepted())->toBeTrue();

            expect($invitation->company->users()->where('user_id', $user->id)->exists())->toBeTrue();
        });

        it('validates required fields', function () {
            $invitation = Invitation::factory()->create();

            $this->post(route('invitations.accept.new', $invitation->token), [])
                ->assertSessionHasErrors(['name', 'password']);
        });

        it('validates password confirmation', function () {
            $invitation = Invitation::factory()->create();

            $this->post(route('invitations.accept.new', $invitation->token), [
                'name' => 'Test',
                'password' => 'password123',
                'password_confirmation' => 'wrong',
            ])->assertSessionHasErrors(['password']);
        });
    });

    describe('Accepting invitations - existing user', function () {
        it('shows confirmation for existing user', function () {
            $user = User::factory()->create(['email' => 'existing@example.com']);
            $invitation = Invitation::factory()->create(['email' => 'existing@example.com']);

            $this->get(route('invitations.accept', $invitation->token))
                ->assertSuccessful()
                ->assertViewIs('invitations.confirm')
                ->assertSee('Accept Invitation');
        });

        it('attaches existing user to company', function () {
            $user = User::factory()->admin()->create(['email' => 'existing@example.com']);
            $invitation = Invitation::factory()->create([
                'email' => 'existing@example.com',
                'role' => UserRole::Accountant,
            ]);

            $this->post(route('invitations.accept.existing', $invitation->token))
                ->assertRedirect('/admin/login');

            expect($invitation->fresh()->isAccepted())->toBeTrue();
            expect($invitation->company->users()->where('user_id', $user->id)->exists())->toBeTrue();
        });
    });

    describe('Acceptance notifications', function () {
        it('notifies inviter when new user accepts', function () {
            $inviter = User::factory()->admin()->create();
            $invitation = Invitation::factory()->create([
                'invited_by' => $inviter->id,
                'role' => UserRole::Viewer,
            ]);

            $this->post(route('invitations.accept.new', $invitation->token), [
                'name' => 'New User',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ]);

            expect(DatabaseNotification::where('notifiable_id', $inviter->id)->count())->toBe(1);

            $notification = DatabaseNotification::where('notifiable_id', $inviter->id)->first();
            expect($notification->data['title'])->toContain('accepted');
        });

        it('notifies inviter when existing user accepts', function () {
            $inviter = User::factory()->admin()->create();
            $user = User::factory()->create(['email' => 'existing@example.com']);
            $invitation = Invitation::factory()->create([
                'email' => 'existing@example.com',
                'invited_by' => $inviter->id,
                'role' => UserRole::Accountant,
            ]);

            $this->post(route('invitations.accept.existing', $invitation->token));

            expect(DatabaseNotification::where('notifiable_id', $inviter->id)->count())->toBe(1);

            $notification = DatabaseNotification::where('notifiable_id', $inviter->id)->first();
            expect($notification->data['title'])->toContain('accepted');
        });
    });

    describe('Edge cases', function () {
        it('shows expired view for expired token', function () {
            $invitation = Invitation::factory()->expired()->create();

            $this->get(route('invitations.accept', $invitation->token))
                ->assertSuccessful()
                ->assertViewIs('invitations.expired')
                ->assertSee('Invitation Expired');
        });

        it('redirects to login for already-accepted token', function () {
            $invitation = Invitation::factory()->accepted()->create();

            $this->get(route('invitations.accept', $invitation->token))
                ->assertRedirect('/admin/login');
        });

        it('shows already-member view when user is already in company', function () {
            $user = User::factory()->admin()->create(['email' => 'member@test.com']);
            $invitation = Invitation::factory()->create(['email' => 'member@test.com']);
            $invitation->company->users()->attach($user, ['role' => UserRole::Admin->value]);

            $this->get(route('invitations.accept', $invitation->token))
                ->assertSuccessful()
                ->assertViewIs('invitations.already-member')
                ->assertSee('Already a Member');
        });

        it('prevents accepting expired invitation via POST', function () {
            $invitation = Invitation::factory()->expired()->create();

            $this->post(route('invitations.accept.new', $invitation->token), [
                'name' => 'Test',
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])->assertRedirect('/admin/login');

            expect(User::where('email', $invitation->email)->exists())->toBeFalse();
        });

        it('returns 404 for invalid token', function () {
            $this->get(route('invitations.accept', 'invalid-token'))
                ->assertNotFound();
        });
    });
});
