<x-filament-panels::page>
    <main class="central" data-platform-plane="central">
        <header class="central-hero">
            <div>
                <p class="central-hero__label">Platform Operations</p>
                <h1>Keryon Central</h1>
                <p>This workspace establishes platform authority. Customer operations arrive in the next bounded milestone.</p>
            </div>
            <div class="central-identity">
                <span>Signed in as</span>
                <strong>{{ auth()->user()->name }}</strong>
                <small>{{ $this->platformMembership()->role->label() }}</small>
            </div>
        </header>

        <div class="central-boundary">
            <section>
                <h2>A separate authority plane</h2>
                <p>Central access comes only from an active PlatformMembership. It does not grant Church or Organization authority, and it does not expose customer operational data.</p>
            </section>
            <section class="central-local">
                <h2>Local foundation</h2>
                <p>Production access remains unavailable until platform MFA is implemented and enforced.</p>
            </section>
        </div>
    </main>
</x-filament-panels::page>
