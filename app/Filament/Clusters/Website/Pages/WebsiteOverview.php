<?php

namespace App\Filament\Clusters\Website\Pages;

use App\Enums\Capability;
use App\Enums\WebsitePageType;
use App\Filament\Clusters\Website;
use App\Filament\Clusters\Website\Resources\WebsiteLeadershipResource;
use App\Filament\Clusters\Website\Resources\WebsiteMinistryResource;
use App\Models\ChurchBrandProfile;
use App\Models\WebsiteAboutContent;
use App\Models\WebsiteContactContent;
use App\Models\WebsiteContentProvenance;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteLeadershipProfile;
use App\Models\WebsiteMinistry;
use App\Models\WebsitePublication;
use App\Models\WebsiteSettings;
use App\PublicWebsite\Themes\ThemeRegistry;
use App\PublicWebsite\WebsitePublicationStatus;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
use App\Website\WebsiteDomainSummaryBuilder;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * K-CHURCHWEB-001C §13/§14 — the Website landing experience. Every
 * figure shown here is a real query against this Church's own data —
 * no fabricated analytics, SEO scores, visitor counts, or completion
 * percentages, and no "last published" claim (publishing does not exist
 * yet — K-CHURCHWEB-001E).
 */
class WebsiteOverview extends Page
{
    protected static ?string $cluster = Website::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Overview';

    protected static ?string $title = 'Website';

    protected static ?int $navigationSort = 0;

    protected string $view = 'filament.clusters.website.pages.website-overview';

    public function getSubheading(): ?string
    {
        return "Manage your Church's public presence.";
    }

    public static function canAccess(): bool
    {
        return app(TenantContext::class)->currentMembership()?->hasCapability(Capability::WebsiteContentView) ?? false;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label('Preview')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn (): string => route('website.preview'))
                ->openUrlInNewTab(),
            Action::make('publish')
                ->label(fn (): string => app(WebsitePublicationStatus::class)->current()['state'] === 'never_published' ? 'Publish Website' : 'Publish Changes')
                ->icon('heroicon-o-rocket-launch')
                ->visible(fn (): bool => app(TenantContext::class)->currentMembership()?->hasCapability(Capability::WebsitePublish) ?? false)
                ->requiresConfirmation()
                ->modalHeading('Publish your church Website?')
                ->modalDescription('Your current Website content, theme, brand, and Church information will become visible on your public church Website.')
                ->modalSubmitActionLabel('Publish Website')
                ->action(function (): void {
                    $firstPublication = ! WebsitePublication::query()->exists();
                    app(WebsitePublisher::class)->publish();

                    Notification::make()
                        ->title($firstPublication ? 'Your church Website is live.' : 'Website changes published.')
                        ->success()
                        ->send();
                }),
            Action::make('unpublish')
                ->label('Take Offline')
                ->icon('heroicon-o-no-symbol')
                ->color('danger')
                ->visible(fn (): bool => (app(TenantContext::class)->currentMembership()?->hasCapability(Capability::WebsitePublish) ?? false)
                    && app(WebsitePublicationStatus::class)->current()['state'] === 'published')
                ->requiresConfirmation()
                ->modalHeading('Take this Website offline?')
                ->modalDescription('Visitors will no longer be able to access the public Website. Your working content and publication history will be preserved.')
                ->modalSubmitActionLabel('Take Website Offline')
                ->action(function (): void {
                    app(WebsitePublisher::class)->unpublish();
                    Notification::make()->title('Website is offline.')->success()->send();
                }),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $membership = app(TenantContext::class)->currentMembership();
        $canManageContent = $membership?->hasCapability(Capability::WebsiteContentManage) ?? false;
        $canManageBrand = $membership?->hasCapability(Capability::ChurchIdentityManage) ?? false;
        $canManageTheme = $membership?->hasCapability(Capability::WebsiteThemeManage) ?? false;
        $publicationStatus = app(WebsitePublicationStatus::class)->current();
        $church = app(TenantContext::class)->currentChurch();

        $home = WebsiteHomeContent::query()->first();
        $about = WebsiteAboutContent::query()->first();
        $contact = WebsiteContactContent::query()->first();
        $settings = WebsiteSettings::query()->first();
        $brand = ChurchBrandProfile::query()->first();
        $leadershipCount = WebsiteLeadershipProfile::query()->count();
        $ministryCount = WebsiteMinistry::query()->count();
        $domainSummary = $church ? app(WebsiteDomainSummaryBuilder::class)->for($church) : null;
        // K-WEB-V1-001D-B §29/§46 — a page type stored/started by the
        // Church but not rendered by the *active* theme must read as
        // "not available in the current theme," never as deleted. All
        // five current types are supported by Proclaim (the only theme
        // today), so this is architecture proven for a future theme
        // switch, not something a Church can trigger yet.
        $activeTheme = $settings ? app(ThemeRegistry::class)->resolve((string) $settings->getRawOriginal('theme')) : null;
        $themeSupportedTypes = $activeTheme?->supportedPageTypes() ?? [];

        $pages = [
            [
                'type' => WebsitePageType::Home,
                'started' => filled($home?->hero_heading),
                'url' => EditHome::getUrl(),
            ],
            [
                'type' => WebsitePageType::About,
                'started' => filled($about?->church_story),
                'url' => EditAbout::getUrl(),
            ],
            [
                'type' => WebsitePageType::Leadership,
                'started' => $leadershipCount > 0,
                'count' => $leadershipCount,
                'url' => WebsiteLeadershipResource::getUrl(),
            ],
            [
                'type' => WebsitePageType::Ministries,
                'started' => $ministryCount > 0,
                'count' => $ministryCount,
                'url' => WebsiteMinistryResource::getUrl(),
            ],
            [
                'type' => WebsitePageType::Contact,
                'started' => filled($contact?->office_hours) || filled($contact?->map_embed_url),
                'url' => EditContact::getUrl(),
            ],
        ];

        return [
            // K-WEB-V1-001D-B §42 — the canonical label/description/icon
            // for each page type now come from `WebsitePageType` itself
            // (the one platform-owned source, shared with navigation and
            // the sitemap), while the "started"/"count"/"url" values stay
            // here — they depend on Filament page classes and live
            // content presence, which are Website-Management concerns,
            // not page-identity metadata (see that enum's own docblock
            // and §43's explicit instruction against turning this into
            // reflection-based dynamic page generation). §29/§46 —
            // `supportedByTheme` lets the view show "not available in
            // the current theme" instead of a broken/misleading action.
            'pages' => array_map(
                fn (array $page): array => $page + ['supportedByTheme' => in_array($page['type'], $themeSupportedTypes, true)],
                $pages,
            ),
            'churchInformationConfigured' => filled($church?->address)
                || $church?->serviceTimes()->exists()
                || $church?->socialLinks()->exists(),
            'brandConfigured' => filled($brand?->primary_logo_media_id) || filled($brand?->primary_color),
            'theme' => $settings?->theme,
            'canManageContent' => $canManageContent,
            'canManageBrand' => $canManageBrand,
            'canManageTheme' => $canManageTheme,
            'canPublish' => $membership?->hasCapability(Capability::WebsitePublish) ?? false,
            'publicationStatus' => $publicationStatus,
            'domainSummary' => $domainSummary,
            'isEmptyWebsite' => ! filled($home?->hero_heading)
                && ! filled($about?->church_story)
                && ! filled($contact?->office_hours)
                && ! filled($contact?->map_embed_url)
                && $leadershipCount === 0
                && $ministryCount === 0
                && ! filled($brand?->primary_logo_media_id)
                && ! filled($brand?->primary_color),
            'canManageDomains' => $membership?->is_primary && ($membership?->hasCapability(Capability::WebsiteDomainManage) ?? false),
            'domainsUrl' => ManageDomains::getUrl(),
            'recentProvenance' => WebsiteContentProvenance::query()
                ->with([
                    'contentItem:id,title,status,approved_at',
                    'campaign:id,title',
                    'campaignCommunication:id,title',
                    'actor:id,name',
                    'publicationAttributions.publication:id,published_at',
                ])
                ->latest('applied_at')->limit(5)->get(),
        ];
    }
}
