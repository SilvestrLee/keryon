<?php

namespace App\PublicWebsite;

use App\Models\Church;
use LogicException;

/** Request-scoped identity for an unauthenticated public Church Website. */
class PublicWebsiteContext
{
    private ?Church $church = null;

    public function resolve(Church $church): void
    {
        $this->church = $church;
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
}
