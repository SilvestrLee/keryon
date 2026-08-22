<?php

namespace App\PublicWebsite;

class PublicUrl
{
    public function external(?string $url): ?string
    {
        if (! is_string($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
            ? $url
            : null;
    }

    public function link(?string $url): ?string
    {
        if (is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        return $this->external($url);
    }
}
