<x-invitation-card>
    <x-slot:heading>Already a Member</x-slot:heading>
    <x-slot:subheading>You are already a member of <strong>{{ $company->name }}</strong>. Please sign in to continue.</x-slot:subheading>

    <a href="/admin/login"
        class="fi-btn fi-btn-size-md mt-6 relative grid-flow-col items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 w-full block text-center">
            Go to Login
    </a>
</x-invitation-card>
