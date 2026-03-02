<?php

use App\Enums\UserRole;
use App\Filament\Widgets\PendingInvitations;
use App\Mail\InvitationMail;
use App\Models\Company;
use App\Models\Invitation;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Mail;

use function Pest\Livewire\livewire;

describe('Pending Invitations Widget', function () {
    describe('Table display', function () {
        it('shows pending invitations for current tenant', function () {
            asUser(role: UserRole::Admin);
            $company = tenant();

            $pending = Invitation::factory()->create([
                'company_id' => $company->id,
                'invited_by' => auth()->id(),
            ]);

            livewire(PendingInvitations::class)
                ->assertCanSeeTableRecords([$pending]);
        });

        it('shows expired invitations for current tenant', function () {
            asUser(role: UserRole::Admin);
            $company = tenant();

            $expired = Invitation::factory()->expired()->create([
                'company_id' => $company->id,
                'invited_by' => auth()->id(),
            ]);

            livewire(PendingInvitations::class)
                ->assertCanSeeTableRecords([$expired]);
        });

        it('excludes accepted invitations', function () {
            asUser(role: UserRole::Admin);
            $company = tenant();

            $accepted = Invitation::factory()->accepted()->create([
                'company_id' => $company->id,
                'invited_by' => auth()->id(),
            ]);

            livewire(PendingInvitations::class)
                ->assertCanNotSeeTableRecords([$accepted]);
        });

        it('excludes invitations from other tenants', function () {
            asUser(role: UserRole::Admin);

            $otherCompany = Company::factory()->create();
            $otherInvitation = Invitation::factory()->create([
                'company_id' => $otherCompany->id,
            ]);

            livewire(PendingInvitations::class)
                ->assertCanNotSeeTableRecords([$otherInvitation]);
        });

        it('displays email, role, inviter, sent, and expires columns', function () {
            asUser(role: UserRole::Admin);
            $company = tenant();

            $invitation = Invitation::factory()->create([
                'company_id' => $company->id,
                'invited_by' => auth()->id(),
                'email' => 'test@example.com',
                'role' => UserRole::Accountant,
            ]);

            livewire(PendingInvitations::class)
                ->assertCanSeeTableRecords([$invitation])
                ->assertTableColumnExists('email')
                ->assertTableColumnExists('role')
                ->assertTableColumnExists('inviter.name')
                ->assertTableColumnExists('created_at')
                ->assertTableColumnExists('expires_at');
        });
    });

    describe('Resend action', function () {
        it('resets expiry and re-sends invitation email', function () {
            Mail::fake();
            asUser(role: UserRole::Admin);
            $company = tenant();

            $invitation = Invitation::factory()->create([
                'company_id' => $company->id,
                'invited_by' => auth()->id(),
                'expires_at' => now()->addDay(),
            ]);

            $originalExpiry = $invitation->expires_at;

            livewire(PendingInvitations::class)
                ->callAction(TestAction::make('resend')->table($invitation))
                ->assertNotified('Invitation resent');

            expect($invitation->fresh()->expires_at->gt($originalExpiry))->toBeTrue();

            Mail::assertQueued(InvitationMail::class, fn (InvitationMail $mail) => $mail->hasTo($invitation->email));
        });

        it('can resend expired invitations', function () {
            Mail::fake();
            asUser(role: UserRole::Admin);
            $company = tenant();

            $expired = Invitation::factory()->expired()->create([
                'company_id' => $company->id,
                'invited_by' => auth()->id(),
            ]);

            livewire(PendingInvitations::class)
                ->callAction(TestAction::make('resend')->table($expired))
                ->assertNotified('Invitation resent');

            expect($expired->fresh()->expires_at->isFuture())->toBeTrue();

            Mail::assertQueued(InvitationMail::class);
        });
    });

    describe('Revoke action', function () {
        it('deletes pending invitation after confirmation', function () {
            asUser(role: UserRole::Admin);
            $company = tenant();

            $invitation = Invitation::factory()->create([
                'company_id' => $company->id,
                'invited_by' => auth()->id(),
            ]);

            livewire(PendingInvitations::class)
                ->callAction(TestAction::make('revoke')->table($invitation))
                ->assertNotified('Invitation revoked');

            expect(Invitation::find($invitation->id))->toBeNull();
        });

        it('is hidden for expired invitations', function () {
            asUser(role: UserRole::Admin);
            $company = tenant();

            $expired = Invitation::factory()->expired()->create([
                'company_id' => $company->id,
                'invited_by' => auth()->id(),
            ]);

            livewire(PendingInvitations::class)
                ->assertActionHidden(TestAction::make('revoke')->table($expired));
        });
    });
});
