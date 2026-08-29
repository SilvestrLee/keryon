@props([
    'inverse' => false,
    'compact' => false,
])

<span
    {{ $attributes->class([
        'keryon-lockup',
        'keryon-lockup--inverse' => $inverse,
        'keryon-lockup--compact' => $compact,
    ]) }}
    aria-label="Keryon"
    role="img"
>
    <img
        src="{{ asset($inverse ? 'branding/logo/keryon-logo-white.svg' : 'branding/logo/keryon-logo.svg') }}"
        alt=""
        class="keryon-lockup__symbol"
    />
    <span class="keryon-lockup__wordmark" aria-hidden="true">keryon</span>
</span>
