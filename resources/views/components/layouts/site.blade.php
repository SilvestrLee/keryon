<!doctype html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Keryon — Your Church\'s Digital Communications Staff Member' }}</title>
    <meta name="description" content="{{ $description ?? 'Keryon helps churches communicate clearly, care intentionally, and keep everyone informed, without the complexity of traditional church software.' }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-[100dvh] bg-surface font-sans text-ink antialiased" x-data="{ mobileNavOpen: false }">
    <x-site.nav />

    <main>
        {{ $slot }}
    </main>

    <x-site.footer />
</body>
</html>
