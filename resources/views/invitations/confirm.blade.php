<x-invitation-card>
    <x-slot:heading>Join {{ $invitation->company->name }}</x-slot:heading>
    <x-slot:subheading>Hi {{ $user->name }}, you've been invited to join <strong>{{ $invitation->company->name }}</strong> as a <strong>{{ $invitation->role->getLabel() }}</strong>.</x-slot:subheading>

    <form method="POST" action="{{ route('invitations.accept.existing', $invitation->token) }}" class="mt-6">
        @csrf

        <button type="submit"
            class="fi-btn fi-btn-size-md relative grid-flow-col items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 w-full">
                Accept Invitation
        </button>
    </form>
</x-invitation-card>
