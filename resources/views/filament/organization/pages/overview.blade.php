<x-filament-panels::page>
    @php($dashboard = $this->dashboard())
    @include('filament.organization.partials.context')

    <header class="org-hero org-hero--compact">
        <p class="org-eyebrow">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ str(auth()->user()->name)->before(' ') }}</p>
        <h1>{{ $dashboard->organizationName }}</h1>
        <p>Here is what needs attention across the part of the organization you are responsible for.</p>
    </header>

    <section class="org-attention {{ $dashboard->attentionCount === 0 ? 'org-attention--clear' : '' }}" aria-labelledby="attention-heading">
        <div class="org-section__header">
            <div class="org-attention__heading">
                @if ($dashboard->attentionCount === 0)
                    <span class="org-state-mark org-state-mark--clear" aria-hidden="true">✓</span>
                @endif
                <div>
                    <p class="org-eyebrow">Attention</p>
                    <h2 id="attention-heading">{{ $dashboard->attentionCount === 0 ? 'No immediate website or setup issues' : $dashboard->attentionCount.' '.str('Church')->plural($dashboard->attentionCount).' '.($dashboard->attentionCount === 1 ? 'needs' : 'need').' attention' }}</h2>
                    <p>{{ $dashboard->attentionCount === 0 ? 'This area is ready. New onboarding, publishing and domain conditions will appear here.' : 'Factual setup, publishing and domain conditions inside your authorized scope.' }}</p>
                </div>
            </div>
            <a class="org-text-link" href="{{ \App\Filament\Organization\Pages\OrganizationChurches::getUrl(['attention' => 'needs_attention'], panel: 'organization') }}" wire:navigate>Review Churches <span aria-hidden="true">→</span></a>
        </div>

        @if ($dashboard->attentionItems !== [])
            <div class="org-attention__list">
                @foreach ($dashboard->attentionItems as $church)
                    <a class="org-attention__item" href="{{ \App\Filament\Organization\Pages\OrganizationChurchDetail::getUrl(['record' => $church->id], panel: 'organization') }}" wire:navigate>
                        <span>
                            <strong>{{ $church->name }}</strong>
                            <small>{{ $church->unitName }}</small>
                        </span>
                        <span class="org-attention__reasons">{{ implode(' · ', $church->attention) }}</span>
                        <span aria-hidden="true">→</span>
                    </a>
                @endforeach
            </div>
        @endif
    </section>

    <section class="org-overview" aria-labelledby="overview-heading">
        <div class="org-section__header org-section__header--compact">
            <div>
                <p class="org-eyebrow">Organization overview</p>
                <h2 id="overview-heading">Your authorized scope at a glance</h2>
            </div>
        </div>
        <div class="org-metrics">
            <section class="org-metric">
                <div class="org-metric__value">{{ $dashboard->churchCount }}</div>
                <div class="org-metric__label">Churches</div>
                <div class="org-metric__detail">{{ $dashboard->activeChurchCount }} active</div>
            </section>
            <section class="org-metric">
                <div class="org-metric__value">{{ $dashboard->unitCount }}</div>
                <div class="org-metric__label">Units</div>
                <div class="org-metric__detail">Below the Organization root</div>
            </section>
            <section class="org-metric">
                <div class="org-metric__value">{{ $dashboard->websiteLiveCount }}</div>
                <div class="org-metric__label">Websites live</div>
                <div class="org-metric__detail">{{ $dashboard->websiteAttentionCount }} not published</div>
            </section>
            <section class="org-metric">
                <div class="org-metric__value">{{ $dashboard->domainConnectedCount }}</div>
                <div class="org-metric__label">Custom domains active</div>
                <div class="org-metric__detail">{{ $dashboard->keryonAddressCount }} using a Keryon address</div>
            </section>
        </div>
    </section>

    <section class="org-section" aria-labelledby="network-heading">
        <div class="org-section__header">
            <div>
                <p class="org-eyebrow">Churches &amp; Units</p>
                <h2 id="network-heading">Organization network</h2>
                <p>The operating structure within your current responsibility.</p>
            </div>
        </div>
        <div class="org-network-grid">
            <section class="org-network-lane" aria-labelledby="church-preview-heading">
                <div class="org-lane-heading">
                    <div>
                        <h3 id="church-preview-heading">Churches</h3>
                        <p>{{ $dashboard->churchCount }} currently visible</p>
                    </div>
                    <a class="org-text-link" href="{{ \App\Filament\Organization\Pages\OrganizationChurches::getUrl(panel: 'organization') }}" wire:navigate>View directory <span aria-hidden="true">→</span></a>
                </div>
                <div class="org-lane-list">
                    @forelse ($dashboard->churchPreview as $church)
                        <a class="org-lane-row" href="{{ \App\Filament\Organization\Pages\OrganizationChurchDetail::getUrl(['record' => $church->id], panel: 'organization') }}" wire:navigate>
                            <span><strong>{{ $church->name }}</strong><small>{{ $church->unitName }}</small></span>
                            <span class="org-badge {{ $church->needsAttention() ? 'org-badge--attention' : 'org-badge--current' }}">{{ $church->needsAttention() ? 'Needs attention' : 'Current' }}</span>
                        </a>
                    @empty
                        <div class="org-zero-state">
                            <span class="org-zero-state__mark" aria-hidden="true">⌂</span>
                            <div><strong>No Churches are assigned yet</strong><p>Accepted Church relationships will populate this directory and all scoped readiness summaries.</p></div>
                        </div>
                    @endforelse
                </div>
            </section>

            <section class="org-network-lane" aria-labelledby="unit-preview-heading">
                <div class="org-lane-heading">
                    <div>
                        <h3 id="unit-preview-heading">Structure</h3>
                        <p>{{ $dashboard->unitCount }} {{ str('Unit')->plural($dashboard->unitCount) }} below the root</p>
                    </div>
                    <a class="org-text-link" href="{{ \App\Filament\Organization\Pages\OrganizationUnits::getUrl(panel: 'organization') }}" wire:navigate>Explore structure <span aria-hidden="true">→</span></a>
                </div>
                <div class="org-lane-list">
                    @forelse ($dashboard->unitPreview as $unit)
                        <a class="org-lane-row" href="{{ \App\Filament\Organization\Pages\OrganizationUnitDetail::getUrl(['record' => $unit->id], panel: 'organization') }}" wire:navigate>
                            <span><strong>{{ $unit->name }}</strong><small>{{ $unit->type }} · {{ $unit->parentName }}</small></span>
                            <span class="org-lane-count">{{ $unit->directChurchCount }} {{ str('Church')->plural($unit->directChurchCount) }}</span>
                        </a>
                    @empty
                        <div class="org-zero-state">
                            <span class="org-zero-state__mark" aria-hidden="true">⌘</span>
                            <div><strong>No Units exist below the root</strong><p>Regions, provinces, dioceses, associations and other approved structures will appear here.</p></div>
                        </div>
                    @endforelse
                </div>
            </section>
        </div>
    </section>

    <div class="org-dashboard-grid">
        <section class="org-section" aria-labelledby="website-readiness-heading">
            <div class="org-section__header">
                <div>
                    <p class="org-eyebrow">Website readiness</p>
                    <h2 id="website-readiness-heading">Publishing state</h2>
                    <p>{{ $dashboard->churchCount === 0 ? 'Readiness will appear as Churches complete setup.' : $dashboard->websiteLiveCount.' live; '.$dashboard->websiteAttentionCount.' not published.' }}</p>
                </div>
            </div>
            <div class="org-distribution {{ $dashboard->churchCount === 0 ? 'org-distribution--empty' : '' }}" role="img" aria-label="Website readiness: {{ $dashboard->websiteLiveCount }} live and {{ $dashboard->websiteAttentionCount }} not published">
                @if ($dashboard->churchCount > 0)
                    <span class="org-distribution__current" style="width: {{ ($dashboard->websiteLiveCount / $dashboard->churchCount) * 100 }}%"></span>
                    <span class="org-distribution__attention" style="width: {{ ($dashboard->websiteAttentionCount / $dashboard->churchCount) * 100 }}%"></span>
                @endif
            </div>
            <dl class="org-legend">
                <div><dt><span class="org-dot org-dot--current"></span> Live</dt><dd>{{ $dashboard->websiteLiveCount }}</dd></div>
                <div><dt><span class="org-dot org-dot--attention"></span> Not published</dt><dd>{{ $dashboard->websiteAttentionCount }}</dd></div>
            </dl>
        </section>

        <section class="org-section" aria-labelledby="domain-readiness-heading">
            <div class="org-section__header">
                <div>
                    <p class="org-eyebrow">Domain state</p>
                    <h2 id="domain-readiness-heading">Church addresses</h2>
                    <p>{{ $dashboard->churchCount === 0 ? 'Domain readiness will appear after Churches are assigned.' : 'Bounded operational state only; infrastructure controls remain in Keryon Central.' }}</p>
                </div>
            </div>
            <dl class="org-state-list">
                <div><dt>Custom domain active</dt><dd>{{ $dashboard->domainConnectedCount }}</dd></div>
                <div><dt>Domain needs attention</dt><dd>{{ $dashboard->domainAttentionCount }}</dd></div>
                <div><dt>Keryon address</dt><dd>{{ $dashboard->keryonAddressCount }}</dd></div>
            </dl>
        </section>
    </div>

    <section class="org-section" aria-labelledby="coordination-heading">
        <div class="org-section__header">
            <div>
                <p class="org-eyebrow">Coordination</p>
                <h2 id="coordination-heading">Organization communication</h2>
                <p>The workspace is prepared for approved Organization-owned communication and campaign activity without exposing Church-private content.</p>
            </div>
        </div>
        <div class="org-coordination-grid">
            <section class="org-coordination-lane" aria-labelledby="communications-heading">
                <div class="org-lane-heading">
                    <div><h3 id="communications-heading">Communications</h3><p>Organization-wide updates and packages</p></div>
                    <span class="org-readiness-label">Awaiting capability</span>
                </div>
                @forelse ($dashboard->communicationItems as $item)
                    <article class="org-coordination-item"><strong>{{ $item['title'] }}</strong><p>{{ $item['detail'] }}</p><span>{{ $item['status'] }}</span></article>
                @empty
                    <div class="org-zero-state org-zero-state--lane">
                        <span class="org-zero-state__mark" aria-hidden="true">↗</span>
                        <div><strong>No Organization communications are active</strong><p>Approved updates, schedules and communication packages will populate this lane when the capability is introduced.</p></div>
                    </div>
                @endforelse
            </section>
            <section class="org-coordination-lane" aria-labelledby="campaigns-heading">
                <div class="org-lane-heading">
                    <div><h3 id="campaigns-heading">Campaigns</h3><p>Participation and upcoming deadlines</p></div>
                    <span class="org-readiness-label">Awaiting capability</span>
                </div>
                @forelse ($dashboard->campaignItems as $item)
                    <article class="org-coordination-item"><strong>{{ $item['title'] }}</strong><p>{{ $item['detail'] }}</p><span>{{ $item['status'] }}</span></article>
                @empty
                    <div class="org-zero-state org-zero-state--lane">
                        <span class="org-zero-state__mark" aria-hidden="true">◫</span>
                        <div><strong>No Organization campaigns are in progress</strong><p>Participation, local-response state and approved deadlines will appear here without reusing private Church Campaign records.</p></div>
                    </div>
                @endforelse
            </section>
        </div>
    </section>

    <section class="org-section" aria-labelledby="scope-heading">
        <div class="org-section__header">
            <div>
                <p class="org-eyebrow">Your responsibility</p>
                <h2 id="scope-heading">Authorized organization scope</h2>
                <p>Every count, status and future coordination item is limited to these assigned scopes and permitted descendants.</p>
            </div>
            <a class="org-text-link" href="{{ \App\Filament\Organization\Pages\OrganizationUnits::getUrl(panel: 'organization') }}" wire:navigate>Explore structure <span aria-hidden="true">→</span></a>
        </div>
        <div class="org-scope-list">
            @forelse ($dashboard->scopes as $scope)
                <div class="org-scope-card">
                    <span>Viewing</span>
                    <strong>{{ $scope['path'] }}</strong>
                    <small>{{ implode(' · ', $scope['responsibilities']) }}</small>
                </div>
            @empty
                <div class="org-zero-state"><span class="org-zero-state__mark" aria-hidden="true">—</span><div><strong>No active responsibility scope</strong><p>An Organization administrator must assign a governed Unit scope before operational information can appear.</p></div></div>
            @endforelse
        </div>
    </section>

    <div class="org-dashboard-grid">
        @if ($dashboard->peopleCount !== null)
            <section class="org-section" aria-labelledby="people-heading">
                <div class="org-section__header">
                    <div>
                        <p class="org-eyebrow">People &amp; access</p>
                        <h2 id="people-heading">{{ $dashboard->peopleCount }} active {{ str('member')->plural($dashboard->peopleCount) }}</h2>
                        <p>Organization membership and scoped responsibilities remain independent from Church membership.</p>
                    </div>
                    <a class="org-text-link" href="{{ \App\Filament\Organization\Pages\OrganizationStaff::getUrl(panel: 'organization') }}" wire:navigate>Review access <span aria-hidden="true">→</span></a>
                </div>
            </section>
        @endif

        <section class="org-section" aria-labelledby="activity-heading">
            <div class="org-section__header">
                <div>
                    <p class="org-eyebrow">Recent activity</p>
                    <h2 id="activity-heading">Organization governance</h2>
                    <p>Bounded structural and access changes. No Church operational content is included.</p>
                </div>
            </div>
            @if ($dashboard->recentActivity !== [])
                <div class="org-list">
                    @foreach ($dashboard->recentActivity as $event)
                        <div class="org-row org-row--activity">
                            <div><div class="org-row__title">{{ $event['label'] }}</div><div class="org-row__meta">{{ \Illuminate\Support\Carbon::parse($event['occurred_at'])->format('j M Y, H:i') }}</div></div>
                            <span class="org-badge">Recorded</span>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="org-zero-state org-zero-state--section"><span class="org-zero-state__mark" aria-hidden="true">↻</span><div><strong>No recent governance activity</strong><p>Unit, Church-assignment and Organization access changes will be recorded here.</p></div></div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
