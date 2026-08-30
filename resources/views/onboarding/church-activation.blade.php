<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Activate {{ $activation->church->name }} — Keryon</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-stone-50 text-gray-900">
<main class="mx-auto flex min-h-screen max-w-xl items-center px-6 py-12">
    <section class="w-full rounded-2xl border border-gray-200 bg-white p-8">
        <p class="text-sm font-semibold text-amber-700">Keryon Church activation</p>
        <h1 class="mt-2 text-2xl font-bold">Activate {{ $activation->church->name }}</h1>
        <p class="mt-3 text-sm text-gray-600">You are accepting Primary Administrator responsibility and starting the Church's 21-day Keryon trial.</p>
        @if ($errors->any())
            <p class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">{{ $errors->first() }}</p>
        @endif
        <form method="POST" class="mt-6 space-y-4">
            @csrf
            <input type="hidden" name="terms_version" value="{{ config('onboarding.legal.terms_version') }}">
            <input type="hidden" name="privacy_version" value="{{ config('onboarding.legal.privacy_version') }}">
            <input type="hidden" name="acceptance_idempotency_key" value="{{ (string) Illuminate\Support\Str::uuid() }}">
            <label class="flex gap-3 text-sm text-gray-700">
                <input type="checkbox" name="legal_acceptance" value="1" required>
                <span>I accept the identified Terms and Privacy policy versions for this activation.</span>
            </label>
            <button class="w-full rounded-xl bg-[#1E5631] px-4 py-3 text-sm font-semibold text-white">Activate Church workspace</button>
        </form>
    </section>
</main>
</body>
</html>
