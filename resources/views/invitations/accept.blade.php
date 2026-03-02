<x-invitation-card>
    <x-slot:heading>Join {{ $invitation->company->name }}</x-slot:heading>
    <x-slot:subheading>You've been invited as a <strong>{{ $invitation->role->getLabel() }}</strong>. Create your account to get started.</x-slot:subheading>

    <form method="POST" action="{{ route('invitations.accept.new', $invitation->token) }}" class="mt-6 space-y-4">
        @csrf

        <div>
            <label for="name" class="fi-fo-field-wrp-label text-sm font-medium text-gray-950 dark:text-white">Name</label>
            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                class="fi-input mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white sm:text-sm" />
            @error('name') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="email" class="fi-fo-field-wrp-label text-sm font-medium text-gray-950 dark:text-white">Email</label>
            <input type="email" value="{{ $invitation->email }}" disabled
                class="fi-input mt-1 block w-full rounded-lg border-gray-300 bg-gray-50 shadow-sm dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 sm:text-sm" />
        </div>

        <div>
            <label for="password" class="fi-fo-field-wrp-label text-sm font-medium text-gray-950 dark:text-white">Password</label>
            <input type="password" name="password" id="password" required
                class="fi-input mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white sm:text-sm" />
            @error('password') <p class="mt-1 text-sm text-danger-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="password_confirmation" class="fi-fo-field-wrp-label text-sm font-medium text-gray-950 dark:text-white">Confirm Password</label>
            <input type="password" name="password_confirmation" id="password_confirmation" required
                class="fi-input mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white sm:text-sm" />
        </div>

        <button type="submit"
            class="fi-btn fi-btn-size-md relative grid-flow-col items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 w-full">
                Create Account
        </button>
    </form>
</x-invitation-card>
