<x-mail::message>
# You're invited to {{ $companyName }}

{{ $inviterName }} has invited you to join **{{ $companyName }}** as a **{{ $role }}** on {{ config('app.name') }}.

<x-mail::button :url="$acceptUrl">
Accept Invitation
</x-mail::button>

This invitation expires on {{ $expiresAt }}.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
