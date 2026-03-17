<x-mail::message>
# Duplicate attachment detected

Hi,

We received your email with the attachment **{{ $filename }}** for **{{ $companyName }}**, but this file has already been imported on **{{ $originalImportedAt }}**. No action was taken.

If you intended to re-import this file, please contact your administrator.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
