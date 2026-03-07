@auth
    @if(auth()->user()?->toured_at === null)
        @livewire('onboarding-tour')
    @endif
@endauth
