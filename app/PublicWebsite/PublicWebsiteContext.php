<?php

namespace App\PublicWebsite;

use App\Models\Church;
use LogicException;

/** Request-scoped identity for an unauthenticated public Church Website. */
class PublicWebsiteContext
{
    private ?Church $church = null;

    private ?ResolvedPublicWebsiteHost $host = null;

    public function resolve(Church $church, ?ResolvedPublicWebsiteHost $host = null): void
    {
        $this->church = $church;
        $this->host = $host;
    }

    public function church(): Church
    {
        return $this->church ?? throw new LogicException('No public Church Website has been resolved.');
    }

    public function churchId(): int
    {
        return $this->church()->getKey();
    }

    public function isResolved(): bool
    {
        return $this->church !== null;
    }

    public function resolvedHost(): ?ResolvedPublicWebsiteHost
    {
        return $this->host;
    }
}
