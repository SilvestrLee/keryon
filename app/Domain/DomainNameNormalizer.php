<?php

namespace App\Domain;

use DomainException;

final class DomainNameNormalizer
{
    public function normalize(string $input): string
    {
        $hostname = strtolower(trim($input));

        if (str_ends_with($hostname, '.')) {
            $hostname = substr($hostname, 0, -1);
        }

        if ($hostname === '' || strlen($hostname) > 253) {
            throw new DomainException('Enter a valid hostname no longer than 253 characters.');
        }

        if (! mb_check_encoding($hostname, 'ASCII') || str_contains($hostname, 'xn--')) {
            throw new DomainException('Internationalized domain names are not supported yet.');
        }

        if (str_contains($hostname, '://') || preg_match('/[\/@?#:*]/', $hostname)) {
            throw new DomainException('Enter a hostname only, without a scheme, path, port, query, or wildcard.');
        }

        if (filter_var($hostname, FILTER_VALIDATE_IP) !== false) {
            throw new DomainException('IP addresses cannot be used as Church domains.');
        }

        $labels = explode('.', $hostname);
        if (count($labels) < 2 || in_array($hostname, ['localhost', 'localhost.localdomain'], true)) {
            throw new DomainException('Enter a public hostname with at least two labels.');
        }

        foreach ($labels as $label) {
            if ($label === '' || strlen($label) > 63 || ! preg_match('/^[a-z0-9](?:[a-z0-9-]*[a-z0-9])?$/', $label)) {
                throw new DomainException('Hostname labels may contain only letters, numbers, and interior hyphens.');
            }
        }

        if ($this->isPlatformHost($hostname)) {
            throw new DomainException('Keryon platform hostnames cannot be claimed as custom domains.');
        }

        return $hostname;
    }

    public function isPlatformHost(string $hostname): bool
    {
        $hostname = strtolower(rtrim($hostname, '.'));
        $base = strtolower((string) config('public-website.base_domain'));

        if ($hostname === $base || str_ends_with($hostname, '.'.$base)) {
            return true;
        }

        return in_array($hostname, $this->platformHosts(), true);
    }

    /** @return list<string> */
    public function platformHosts(): array
    {
        $base = strtolower((string) config('public-website.base_domain'));
        $reserved = array_map(fn (string $label): string => strtolower($label).'.'.$base, config('public-website.reserved_subdomains', []));

        return array_values(array_unique(array_map('strtolower', [
            $base,
            ...config('public-website.marketing_hosts', []),
            ...config('public-website.application_hosts', []),
            ...$reserved,
        ])));
    }
}
