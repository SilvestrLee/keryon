<?php

namespace App\PublicWebsite;

use App\Models\Church;

class PublicWebsiteUrl
{
    public function page(Church $church, string $page = 'home'): string
    {
        $path = $page === 'home' ? '' : '/'.$page;

        return sprintf('%s://%s.%s%s',
            config('public-website.scheme'),
            $church->slug,
            config('public-website.base_domain'),
            $path,
        );
    }
}
