<?php

namespace App\Filament\Clusters\Website\Pages;

use App\Enums\Capability;
use App\Filament\Clusters\Website;
use App\Filament\Clusters\Website\Resources\WebsiteLeadershipResource;
use App\Filament\Clusters\Website\Resources\WebsiteMinistryResource;
use App\Models\ChurchBrandProfile;
use App\Models\WebsiteAboutContent;
use App\Models\WebsiteContactContent;
use App\Models\WebsiteHomeContent;
use App\Models\WebsiteLeadershipProfile;
use App\Models\WebsiteMinistry;
use App\Models\WebsitePublication;
use App\Models\WebsiteSettings;
use App\PublicWebsite\WebsitePublicationStatus;
use App\PublicWebsite\WebsitePublisher;
use App\Support\TenantContext;
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

        $home = WebsiteHomeContent::query()->first();
        $about = WebsiteAboutContent::query()->first();
        $contact = WebsiteContactContent::query()->first();
        $settings = WebsiteSettings::query()->first();
        $brand = ChurchBrandProfile::query()->first();

        return [
            'pages' => [
                [
                    'label' => 'Home',
                    'description' => 'Hero, welcome message, and scripture highlight.',
                    'started' => filled($home?->hero_heading),
                    'url' => EditHome::getUrl(),
                    'icon' => 'heroicon-o-home',
                ],
                [
                    'label' => 'About',
                    'description' => 'Church story, vision, mission, and leadership introduction.',
                    'started' => filled($about?->church_story),
                    'url' => EditAbout::getUrl(),
                    'icon' => 'heroicon-o-book-open',
                ],
                [
                    'label' => 'Leadership',
                    'description' => 'Pastors, ministers, elders, and team profiles.',
                    'started' => WebsiteLeadershipProfile::query()->exists(),
                    'count' => WebsiteLeadershipProfile::query()->count(),
                    'url' => WebsiteLeadershipResource::getUrl(),
                    'icon' => 'heroicon-o-user-group',
                ],
                [
                    'label' => 'Ministries',
                    'description' => 'The ministries your church website shows to visitors.',
                    'started' => WebsiteMinistry::query()->exists(),
                    'count' => WebsiteMinistry::query()->count(),
                    'url' => WebsiteMinistryResource::getUrl(),
                    'icon' => 'heroicon-o-heart',
                ],
                [
                    'label' => 'Contact',
                    'description' => 'Office hours and map link, alongside your Church Information.',
                    'started' => filled($contact?->office_hours) || filled($contact?->map_embed_url),
                    'url' => EditContact::getUrl(),
                    'icon' => 'heroicon-o-envelope',
                ],
            ],
            'churchInformationConfigured' => filled(app(TenantContext::class)->currentChurch()?->address)
                || app(TenantContext::class)->currentChurch()?->serviceTimes()->exists()
                || app(TenantContext::class)->currentChurch()?->socialLinks()->exists(),
            'brandConfigured' => filled($brand?->primary_logo_media_id) || filled($brand?->primary_color),
            'theme' => $settings?->theme,
            'canManageContent' => $canManageContent,
            'canManageBrand' => $canManageBrand,
            'canManageTheme' => $canManageTheme,
            'canPublish' => $membership?->hasCapability(Capability::WebsitePublish) ?? false,
            'publicationStatus' => $publicationStatus,
        ];
    }
}
