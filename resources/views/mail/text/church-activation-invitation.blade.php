KERYON — CHURCH ACTIVATION

You have been invited to become Primary Administrator for {{ $messageData->churchName }}.

Activate securely: {{ $messageData->url }}

This single-use link expires {{ \Illuminate\Support\Carbon::parse($messageData->expiresAt)->toDayDateTimeString() }}. If you did not expect this invitation, do not use it. Keryon will never ask for your password by email.
