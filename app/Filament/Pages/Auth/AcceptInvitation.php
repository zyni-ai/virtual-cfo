<?php

namespace App\Filament\Pages\Auth;

use App\Models\Invitation;
use App\Models\User;
use App\Notifications\InvitationAcceptedNotification;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Pages\SimplePage;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Hash;

/**
 * @property-read Schema $form
 */
class AcceptInvitation extends SimplePage
{
    /** @var array<string, mixed> | null */
    public ?array $data = [];

    public ?Invitation $invitation = null;

    public ?User $existingUser = null;

    public string $status = 'new'; // new, existing, expired, already-member

    public function mount(string $token): void
    {
        $this->invitation = Invitation::with('company')->where('token', $token)->first();

        if (! $this->invitation) {
            abort(404);
        }

        if ($this->invitation->isAccepted()) {
            redirect('/admin/login');

            return;
        }

        if ($this->invitation->isExpired()) {
            $this->status = 'expired';

            return;
        }

        $this->existingUser = User::where('email', $this->invitation->email)->first();

        if ($this->existingUser && $this->invitation->company->users()->where('user_id', $this->existingUser->id)->exists()) {
            $this->status = 'already-member';

            return;
        }

        if ($this->existingUser) {
            $this->status = 'existing';

            return;
        }

        $this->status = 'new';
        $this->form->fill();
    }

    public function getTitle(): string|Htmlable
    {
        return match ($this->status) {
            'expired' => 'Invitation Expired',
            'already-member' => 'Already a Member',
            default => 'Join '.$this->invitation->company->name,
        };
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->getTitle();
    }

    public function getSubheading(): string|Htmlable|null
    {
        return match ($this->status) {
            'new' => "You've been invited as a {$this->invitation->role->getLabel()}. Create your account to get started.",
            'existing' => "Hi {$this->existingUser->name}, you've been invited to join {$this->invitation->company->name} as a {$this->invitation->role->getLabel()}.",
            'expired' => 'This invitation has expired. Please ask the team administrator to send a new invitation.',
            'already-member' => "You are already a member of {$this->invitation->company->name}. Please sign in to continue.",
            default => null,
        };
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(255)
                    ->autofocus(),
                TextInput::make('email')
                    ->label('Email')
                    ->default($this->invitation?->email)
                    ->disabled()
                    ->dehydrated(false),
                TextInput::make('password')
                    ->label('Password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->same('passwordConfirmation')
                    ->validationAttribute('password'),
                TextInput::make('passwordConfirmation')
                    ->label('Confirm Password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->dehydrated(false),
            ]);
    }

    public function createAccount(): void
    {
        $data = $this->form->getState();

        $user = User::create([
            'name' => $data['name'],
            'email' => $this->invitation->email,
            'password' => Hash::make($data['password']),
            'role' => $this->invitation->role,
        ]);

        $this->invitation->company->users()->attach($user, ['role' => $this->invitation->role->value]);
        $this->invitation->markAccepted();

        $this->notifyInviter();

        session()->flash('status', 'Account created. Please sign in.');

        $this->redirect('/admin/login');
    }

    public function acceptInvitation(): void
    {
        $this->invitation->company->users()->syncWithoutDetaching([
            $this->existingUser->id => ['role' => $this->invitation->role->value],
        ]);

        $this->invitation->markAccepted();

        $this->notifyInviter();

        session()->flash('status', 'You have been added to the team. Please sign in.');

        $this->redirect('/admin/login');
    }

    public function content(Schema $schema): Schema
    {
        return match ($this->status) {
            'new' => $this->newUserContent($schema),
            'existing' => $this->existingUserContent($schema),
            default => $this->infoContent($schema),
        };
    }

    protected function newUserContent(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('createAccount')
                    ->footer([
                        Actions::make([
                            Action::make('createAccount')
                                ->label('Create Account')
                                ->submit('createAccount'),
                        ])->fullWidth(),
                    ]),
            ]);
    }

    protected function existingUserContent(Schema $schema): Schema
    {
        return $schema
            ->components([
                Actions::make([
                    Action::make('acceptInvitation')
                        ->label('Accept Invitation')
                        ->action('acceptInvitation')
                        ->size('lg'),
                ])->fullWidth(),
            ]);
    }

    protected function infoContent(Schema $schema): Schema
    {
        return $schema
            ->components([
                Actions::make([
                    Action::make('login')
                        ->label('Go to Login')
                        ->url('/admin/login')
                        ->size('lg'),
                ])->fullWidth(),
            ]);
    }

    private function notifyInviter(): void
    {
        $this->invitation->loadMissing('inviter');

        if ($this->invitation->inviter) {
            $this->invitation->inviter->notify(new InvitationAcceptedNotification($this->invitation));
        }
    }

    public static function getSlug(): string
    {
        return 'invitations/{token}/accept';
    }

    public static function getRouteName(): string
    {
        return 'invitations.accept';
    }
}
