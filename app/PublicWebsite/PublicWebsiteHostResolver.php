<?php

namespace App\PublicWebsite;

use App\Domain\DomainNameNormalizer;
use App\Enums\DomainStatus;
use App\Enums\DomainTlsStatus;
use App\Enums\PublicWebsiteHostType;
use App\Models\Church;
use App\Models\ChurchDomain;

final readonly class PublicWebsiteHostResolver
{
    public function __construct(
        private PublicWebsiteResolver $firstParty,
        private DomainNameNormalizer $normalizer,
    ) {}

    public function resolve(string $requestHost): ?ResolvedPublicWebsiteHost
    {
        $host = strtolower(rtrim($requestHost, '.'));
        $base = strtolower((string) config('public-website.base_domain'));
        $suffix = '.'.$base;

        if (str_ends_with($host, $suffix)) {
            $slug = substr($host, 0, -strlen($suffix));
            if (! str_contains($slug, '.')) {
                $church = $this->firstParty->resolve($slug);

                return $church === null ? null : new ResolvedPublicWebsiteHost($church, $host, PublicWebsiteHostType::KeryonSubdomain);
            }

            return null;
        }

        if ($this->normalizer->isPlatformHost($host)) {
            return null;
        }

        $church = Church::query()
            ->join('church_domains', 'church_domains.church_id', '=', 'churches.id')
            ->where('church_domains.normalized_hostname', $host)
            ->where('church_domains.status', DomainStatus::Active->value)
            ->whereNotNull('church_domains.ownership_verified_at')
            ->whereNotNull('church_domains.routing_verified_at')
            ->where('church_domains.tls_status', DomainTlsStatus::Ready->value)
            ->whereNotNull('church_domains.tls_ready_at')
            ->whereNull('church_domains.disabled_at')
            ->whereNull('church_domains.released_at')
            ->where('churches.is_active', true)
            ->select('churches.*')
            ->addSelect([
                'church_domains.id as resolved_domain_id',
                'church_domains.uuid as resolved_domain_uuid',
                'church_domains.normalized_hostname as resolved_domain_hostname',
                'church_domains.is_primary as resolved_domain_primary',
            ])
            ->first();

        if ($church === null) {
            return null;
        }

        $domain = (new ChurchDomain)->forceFill([
            'id' => $church->getAttribute('resolved_domain_id'),
            'uuid' => $church->getAttribute('resolved_domain_uuid'),
            'church_id' => $church->getKey(),
            'normalized_hostname' => $church->getAttribute('resolved_domain_hostname'),
            'status' => DomainStatus::Active,
            'tls_status' => DomainTlsStatus::Ready,
            'is_primary' => (bool) $church->getAttribute('resolved_domain_primary'),
        ]);

        return new ResolvedPublicWebsiteHost($church, $host, PublicWebsiteHostType::CustomDomain, $domain);
    }
}
