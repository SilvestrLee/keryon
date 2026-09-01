<?php

namespace App\Filament\Clusters\Website\Pages;

use App\Domain\ChurchDomainStatusPresenter;
use App\Domain\DisableChurchDomain;
use App\Domain\MakeChurchDomainPrimary;
use App\Domain\RegenerateChurchDomainToken;
use App\Domain\ReleaseChurchDomain;
use App\Domain\RequestChurchCustomDomain;
use App\Enums\Capability;
use App\Filament\Clusters\Website;
use App\Filament\Clusters\Website\WebsiteNavigation;
use App\Jobs\VerifyChurchDomain;
use App\Models\Church;
use App\Models\ChurchDomain;
use App\Models\WebsiteSettings;
use App\PublicWebsite\PublicWebsiteUrl;
use App\Support\TenantContext;
use App\Website\ChurchPublicUrlResolver;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

class ManageDomains extends Page
{
    protected static ?string $cluster = Website::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-link';

    protected static ?string $navigationLabel = 'Domains';

    protected static string|\UnitEnum|null $navigationGroup = WebsiteNavigation::CONFIGURATION;

    protected static ?string $title = 'Domains';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.clusters.website.pages.manage-domains';

    public string $hostname = '';

    public ?string $verificationToken = null;

    public ?int $verificationDomainId = null;

    public static function canAccess(): bool
    {
        $membership = app(TenantContext::class)->currentMembership();

        return $membership !== null
            && $membership->is_primary
            && $membership->hasCapability(Capability::WebsiteDomainManage);
    }

    public function claim(): void
    {
        $church = $this->authorizeCurrentChurch();

        try {
            $result = app(RequestChurchCustomDomain::class)->execute($church, $this->hostname);
        } catch (ValidationException $exception) {
            $messages = $exception->errors()['hostname'] ?? ['Enter only the domain name, such as www.yourchurch.org.'];
            $this->addError('hostname', $this->friendlyValidationMessage($messages[0]));

            return;
        }

        $this->hostname = '';
        $this->verificationToken = $result->verificationToken;
        $this->verificationDomainId = $result->domain->getKey();
        Notification::make()->title('Domain added')->success()->send();
    }

    public function regenerate(int $domainId): void
    {
        $domain = $this->domainForCurrentChurch($domainId);
        $result = app(RegenerateChurchDomainToken::class)->execute($domain);
        $this->verificationToken = $result->verificationToken;
        $this->verificationDomainId = $domain->getKey();
        Notification::make()->title('New verification value generated')->success()->send();
    }

    public function verify(int $domainId): void
    {
        $domain = $this->domainForCurrentChurch($domainId);
        abort_unless($this->productionConnectionAvailable(), 409);
        VerifyChurchDomain::dispatch($domain->getKey());
        Notification::make()->title('Verification check started')->body('Keryon is checking your DNS records.')->success()->send();
    }

    public function makePrimary(int $domainId): void
    {
        $domain = $this->domainForCurrentChurch($domainId);
        app(MakeChurchDomainPrimary::class)->execute($domain);
        Notification::make()->title('Official website address updated')->success()->send();
    }

    public function disable(int $domainId): void
    {
        app(DisableChurchDomain::class)->execute($this->domainForCurrentChurch($domainId));
        Notification::make()->title('Domain disabled')->success()->send();
    }

    public function release(int $domainId): void
    {
        app(ReleaseChurchDomain::class)->execute($this->domainForCurrentChurch($domainId));
        Notification::make()->title('Domain released')->success()->send();
    }

    /** @return array<string, mixed> */
    public function getViewData(): array
    {
        $church = $this->authorizeCurrentChurch();
        $domains = ChurchDomain::query()
            ->orderByRaw('released_at is not null')
            ->orderByDesc('is_primary')
            ->orderBy('id')
            ->get();
        $activeDomains = $domains->whereNull('released_at');

        return [
            'church' => $church,
            'domains' => $activeDomains,
            'releasedDomains' => $domains->whereNotNull('released_at'),
            'presenter' => app(ChurchDomainStatusPresenter::class),
            'keryonUrl' => app(PublicWebsiteUrl::class)->page($church),
            'officialUrl' => app(ChurchPublicUrlResolver::class)->resolve($church),
            'isPublished' => WebsiteSettings::query()->whereNotNull('current_publication_id')->exists(),
            'canAddDomain' => $activeDomains->count() < (int) config('public-website.custom_domains.claim_limit', 2),
            'connectionAvailable' => $this->productionConnectionAvailable(),
            'routingInstructions' => $this->routingInstructions(),
        ];
    }

    private function authorizeCurrentChurch(): Church
    {
        if (! static::canAccess() || ($church = app(TenantContext::class)->currentChurch()) === null) {
            throw new AuthorizationException;
        }

        return $church;
    }

    private function domainForCurrentChurch(int $domainId): ChurchDomain
    {
        $church = $this->authorizeCurrentChurch();

        return ChurchDomain::query()->where('church_id', $church->getKey())->findOrFail($domainId);
    }

    private function productionConnectionAvailable(): bool
    {
        return config('public-website.custom_domains.dns_resolver') !== 'unavailable'
            && config('public-website.custom_domains.provisioner') !== 'unavailable'
            && ($this->routingInstructions() !== []);
    }

    /** @return array{type: string, name: string, value: string}|array{} */
    private function routingInstructions(): array
    {
        $target = (string) config('public-website.custom_domains.dns_ingress_target');
        if ($target !== '') {
            return ['type' => 'CNAME', 'name' => 'Your custom domain', 'value' => $target];
        }

        $addresses = config('public-website.custom_domains.apex_ipv4', []);

        return $addresses === [] ? [] : ['type' => 'A', 'name' => 'Your custom domain', 'value' => implode(', ', $addresses)];
    }

    private function friendlyValidationMessage(string $message): string
    {
        if (str_contains($message, 'Internationalized')) {
            return "Internationalized domain names aren't supported yet. Use an ASCII domain for now.";
        }
        if (str_contains($message, 'Keryon platform')) {
            return "This Keryon address can't be used as a custom domain.";
        }
        if (str_contains($message, 'scheme') || str_contains($message, 'hostname only')) {
            return 'Enter only the domain name, such as www.yourchurch.org.';
        }

        return $message;
    }
}
