<x-public-website.proclaim-layout :$church :$brand :$logo :$mark :$serviceTimes :$socialLinks :$palette :$page :$seo :$preview title="Leadership" :description="'Meet the leaders serving '.$church->name">
    <section class="pw-page-hero">
        <div class="pw-shell">
            <p class="pw-kicker">Leadership</p>
            <h1>People who serve with care.</h1>
        </div>
    </section>

    <section class="pw-section">
        <div class="pw-shell">
            @forelse ($profiles->groupBy(fn ($profile) => $profile->category->label()) as $category => $group)
                <section class="pw-collection" aria-labelledby="leadership-{{ Str::slug($category) }}">
                    <h2 id="leadership-{{ Str::slug($category) }}">{{ Str::plural($category) }}</h2>
                    <div class="pw-people-grid">
                        @foreach ($group as $profile)
                            <article class="pw-person">
                                @if ($profile->publicImage)
                                    <img src="{{ $profile->publicImage['url'] }}" alt="{{ $profile->publicImage['alt'] }}" width="{{ $profile->publicImage['width'] ?: 700 }}" height="{{ $profile->publicImage['height'] ?: 850 }}" loading="lazy">
                                @else
                                    <div class="pw-image-fallback" aria-hidden="true"><span>{{ Str::substr($profile->name, 0, 1) }}</span></div>
                                @endif
                                <div class="pw-person-copy">
                                    <h3>{{ $profile->name }}</h3>
                                    @if ($profile->role_title)<p class="pw-role">{{ $profile->role_title }}</p>@endif
                                    @if ($profile->bio)<div class="pw-prose">{!! nl2br(e($profile->bio)) !!}</div>@endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @empty
                <div class="pw-empty"><h2>Leadership information is coming soon.</h2><p>Please check back for updates from {{ $church->name }}.</p></div>
            @endforelse
        </div>
    </section>
</x-public-website.proclaim-layout>
