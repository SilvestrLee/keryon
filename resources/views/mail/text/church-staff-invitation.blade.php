KERYON — CHURCH STAFF INVITATION

You have been invited to join {{ $messageData->churchName }} with these responsibilities: {{ implode(', ', $messageData->roles) }}.
@if(in_array('Care', $messageData->roles, true))

Care gives access to private prayer requests and Care Center records.
@endif

Review securely: {{ $messageData->url }}

This single-use link expires {{ \Illuminate\Support\Carbon::parse($messageData->expiresAt)->toDayDateTimeString() }}. If unexpected, do not use it.
