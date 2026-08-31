<?php

namespace App\Search\Concerns;

use App\Enums\Capability;
use App\Enums\WorkspaceType;
use App\Support\TenantContext;

trait ChurchSearchProvider
{
    abstract protected function capability(): Capability;

    public function supports(WorkspaceType $workspace): bool
    {
        return $workspace === WorkspaceType::Church;
    }

    public function eligible(): bool
    {
        return app(TenantContext::class)->currentMembership()?->hasCapability($this->capability()) ?? false;
    }

    protected function pattern(string $term): string
    {
        return '%'.addcslashes($term, '\\%_').'%';
    }
}
