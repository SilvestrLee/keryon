<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Join {{ $invitation->church->name }} | Keryon</title>@vite(['resources/css/app.css'])</head>
<body class="min-h-screen bg-stone-50 text-stone-950 antialiased">
<main class="mx-auto flex min-h-screen max-w-2xl items-center px-5 py-12">
    <section class="w-full rounded-2xl border border-stone-200 bg-white p-6 shadow-sm sm:p-10" aria-labelledby="invitation-title">
        <x-keryon-logo class="h-9 w-auto" />
        <p class="mt-10 text-sm font-semibold text-amber-700">Church staff invitation</p>
        <h1 id="invitation-title" class="mt-2 text-3xl font-semibold tracking-tight">Join {{ $invitation->church->name }}</h1>
        <p class="mt-3 text-base leading-7 text-stone-600">You have been invited to work in this Church workspace with the roles below.</p>
        <ul class="mt-6 flex flex-wrap gap-2" aria-label="Invited roles">@foreach($invitation->roles as $role)<li class="rounded-full bg-stone-100 px-3 py-1.5 text-sm font-semibold">{{ $role->role->label() }}</li>@endforeach</ul>
        @if($invitation->roles->contains(fn($role) => $role->role === \App\Enums\ChurchRole::CARE))<div class="mt-6 rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm leading-6 text-amber-950"><strong>Care access:</strong> includes private prayer requests and Care Center records.</div>@endif
        @if($errors->any())<div role="alert" class="mt-6 rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-900">{{ $errors->first() }}</div>@endif
        <form method="post" action="{{ route('church-staff-invitations.accept', ['token' => $token]) }}" class="mt-8 space-y-5">@csrf
            <input type="hidden" name="acceptance_idempotency_key" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
            <label class="flex items-start gap-3 text-sm leading-6"><input type="checkbox" name="legal_acceptance" value="1" required class="mt-1 rounded border-stone-400 text-amber-700 focus:ring-amber-600"><span>I accept the applicable Keryon Terms and Privacy Policy for my individual account.</span></label>
            <button class="w-full rounded-xl bg-stone-950 px-5 py-3 font-semibold text-white transition hover:bg-stone-800 focus:outline-none focus:ring-2 focus:ring-amber-600 focus:ring-offset-2 active:scale-[.99]">Accept invitation</button>
        </form>
    </section>
</main></body></html>
