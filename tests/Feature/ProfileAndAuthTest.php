<?php

use App\Enums\UserRole;
use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Auth\Notifications\ResetPassword;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

describe('Profile page', function () {
    it('displays current user name and email', function () {
        $user = asUser();

        Livewire::test(EditProfile::class)
            ->assertSuccessful()
            ->assertFormSet([
                'name' => $user->name,
                'email' => $user->email,
            ]);
    });

    it('can update name', function () {
        $user = asUser();

        Livewire::test(EditProfile::class)
            ->fillForm([
                'name' => 'Updated Name',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($user->fresh()->name)->toBe('Updated Name');
    });

    it('does not allow email changes', function () {
        $user = asUser();

        Livewire::test(EditProfile::class)
            ->assertFormFieldIsDisabled('email');
    });

    it('can change password', function () {
        $user = User::factory()->state([
            'role' => UserRole::Admin,
            'password' => Hash::make('old-password'),
        ])->create();

        asUser($user);

        Livewire::test(EditProfile::class)
            ->fillForm([
                'name' => $user->name,
                'currentPassword' => 'old-password',
                'password' => 'new-password123',
                'passwordConfirmation' => 'new-password123',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        expect(Hash::check('new-password123', $user->fresh()->password))->toBeTrue();
    });

    it('validates name is required', function () {
        asUser();

        Livewire::test(EditProfile::class)
            ->fillForm([
                'name' => '',
            ])
            ->call('save')
            ->assertHasFormErrors(['name' => 'required']);
    });
});

describe('Password reset', function () {
    it('request page renders with email field', function () {
        Livewire::test(RequestPasswordReset::class)
            ->assertSuccessful()
            ->assertFormFieldExists('email');
    });

    it('sends password reset notification', function () {
        Notification::fake();

        $user = User::factory()->create(['email' => 'reset@example.com']);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm([
                'email' => 'reset@example.com',
            ])
            ->call('request')
            ->assertNotified();

        Notification::assertSentTo($user, ResetPassword::class);
    });
});

describe('Multi-factor authentication', function () {
    it('user model implements MFA contracts', function () {
        $user = User::factory()->create();

        expect($user)->toBeInstanceOf(HasAppAuthentication::class)
            ->and($user)->toBeInstanceOf(HasAppAuthenticationRecovery::class);
    });

    it('user has MFA columns', function () {
        $user = User::factory()->create();

        expect($user->app_authentication_secret)->toBeNull()
            ->and($user->app_authentication_recovery_codes)->toBeNull();
    });
});
