<?php

namespace App\Http\Controllers;

use App\Http\Requests\AcceptInvitationRequest;
use App\Models\Invitation;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AcceptInvitationController
{
    public function show(string $token): View|RedirectResponse
    {
        $invitation = Invitation::with('company')->where('token', $token)->first();

        if (! $invitation) {
            abort(404);
        }

        if ($invitation->isAccepted()) {
            return redirect('/admin/login');
        }

        if ($invitation->isExpired()) {
            return view('invitations.expired');
        }

        $existingUser = User::where('email', $invitation->email)->first();

        if ($existingUser && $invitation->company->users()->where('user_id', $existingUser->id)->exists()) {
            return view('invitations.already-member', [
                'company' => $invitation->company,
            ]);
        }

        if ($existingUser) {
            return view('invitations.confirm', [
                'invitation' => $invitation,
                'user' => $existingUser,
            ]);
        }

        return view('invitations.accept', [
            'invitation' => $invitation,
        ]);
    }

    public function storeNewUser(AcceptInvitationRequest $request, string $token): RedirectResponse
    {
        $invitation = Invitation::with(['company', 'inviter'])->where('token', $token)->firstOrFail();

        if ($invitation->isAccepted() || $invitation->isExpired()) {
            return redirect('/admin/login');
        }

        $user = User::create([
            'name' => $request->validated('name'),
            'email' => $invitation->email,
            'password' => Hash::make($request->validated('password')),
            'role' => $invitation->role,
        ]);

        $invitation->company->users()->attach($user, ['role' => $invitation->role->value]);
        $invitation->markAccepted();

        $this->notifyInviter($invitation);

        return redirect('/admin/login')->with('status', 'Account created. Please sign in.');
    }

    public function storeExistingUser(string $token): RedirectResponse
    {
        $invitation = Invitation::with(['company', 'inviter'])->where('token', $token)->firstOrFail();

        if ($invitation->isAccepted() || $invitation->isExpired()) {
            return redirect('/admin/login');
        }

        $user = User::where('email', $invitation->email)->firstOrFail();

        if (! $invitation->company->users()->where('user_id', $user->id)->exists()) {
            $invitation->company->users()->attach($user, ['role' => $invitation->role->value]);
        }

        $invitation->markAccepted();

        $this->notifyInviter($invitation);

        return redirect('/admin/login')->with('status', 'You have been added to the team. Please sign in.');
    }

    private function notifyInviter(Invitation $invitation): void
    {
        if (! $invitation->inviter) {
            return;
        }

        $invitation->loadMissing('company');

        Notification::make()
            ->title("{$invitation->email} accepted your invitation")
            ->body("They joined {$invitation->company->name} as {$invitation->role->getLabel()}.")
            ->success()
            ->sendToDatabase($invitation->inviter);
    }
}
