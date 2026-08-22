<?php

namespace App\PublicWebsite;

use Illuminate\Support\Str;

class WebsiteSeo
{
    public function __construct(private readonly PublicWebsiteUrl $urls) {}

    /** @param array<string, mixed> $data */
    public function forPage(string $page, array $data, bool $preview): array
    {
        $church = $data['church'];
        $content = $data['content'] ?? null;
        $label = ucfirst($page);
        $title = $page === 'home' ? $church->name : "{$label} | {$church->name}";
        $source = match ($page) {
            'home' => $content?->hero_subheading ?? $content?->welcome_body,
            'about' => $content?->vision ?? $content?->church_story,
            'contact' => $content?->office_hours,
            default => null,
        };
        $description = Str::limit(trim(strip_tags((string) ($source ?: "Visit {$church->name} to learn about our church community, ministries, and service times."))), 160, '');
        $canonical = $this->urls->page($church, $page);

        $organization = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Church',
            'name' => $church->name,
            'url' => $this->urls->page($church),
            'logo' => $data['logo']['url'] ?? null,
            'email' => $church->email,
            'telephone' => $church->phone,
            'address' => $church->address ? ['@type' => 'PostalAddress', 'streetAddress' => $church->address] : null,
            'sameAs' => $data['socialLinks']->pluck('publicUrl')->values()->all() ?: null,
        ], fn ($value): bool => $value !== null && $value !== '');

        return compact('title', 'description', 'canonical', 'organization', 'preview');
    }
}
