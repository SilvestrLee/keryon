{{--
    K-FAITHFLOW-001F-R2 — the reusable Keryon Forms composition primitive.
    Three related organic silhouettes (one visual family — the same
    smooth-bezier construction technique, varied proportions), never
    identical, never centered. `$variant` selects which combination
    appears; `$breathe` toggles the slow idle-motion treatment (used only
    for the generation/progress moment, per §14/§15 — everywhere else the
    forms are static atmosphere, not attention-seeking motion).
--}}
@php
    $variant ??= 'full';
    $breathe ??= false;
    $breatheClass = $breathe ? ' ff-form--breathe' : '';
@endphp

<div class="ff-forms" aria-hidden="true">
    <div class="ff-form ff-form--rise{{ $breatheClass }}">
        <svg viewBox="0 0 200 220" preserveAspectRatio="xMidYMid meet">
            <path
                class="ff-form-fill-teal"
                d="M100,20 C130,15 155,45 158,85 C161,130 145,175 105,190 C65,205 25,175 18,130 C12,90 30,50 65,30 C77,23 88,18 100,20 Z"
            />
        </svg>
    </div>

    @if ($variant !== 'minimal')
        <div class="ff-form ff-form--unfold">
            <svg viewBox="0 0 200 200" preserveAspectRatio="xMidYMid meet">
                <path
                    class="ff-form-fill-amber"
                    d="M100,10 C140,5 185,35 190,80 C195,130 165,175 115,188 C65,200 15,170 10,120 C6,75 40,25 90,12 C93,11 97,10 100,10 Z"
                />
            </svg>
        </div>
    @endif

    @if ($variant === 'full')
        <div class="ff-form ff-form--spark{{ $breatheClass }}">
            <svg viewBox="0 0 100 100" preserveAspectRatio="xMidYMid meet">
                <path
                    class="ff-form-fill-teal"
                    d="M50,5 C68,3 92,20 95,48 C98,75 80,95 52,97 C25,99 5,80 3,53 C1,28 20,8 42,6 C45,5 47,5 50,5 Z"
                />
            </svg>
        </div>
    @endif
</div>
