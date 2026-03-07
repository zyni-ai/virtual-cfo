<x-mail::message>
# You're invited to {{ $companyName }}

Hi there,

**{{ $inviterName }}** has invited you to join **{{ $companyName }}** on {{ config('app.name') }} as a **{{ $role }}**.

{{ config('app.name') }} helps teams automate bank statement processing, account head mapping, and Tally exports — so your finance workflow runs smoother.

<x-mail::button :url="$acceptUrl">
Accept Invitation
</x-mail::button>

This invitation expires on **{{ $expiresAt }}**. If you weren't expecting this, you can safely ignore this email.

Cheers,<br>
The {{ config('app.name') }} Team
</x-mail::message>
