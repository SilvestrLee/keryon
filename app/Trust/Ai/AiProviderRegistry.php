<?php

namespace App\Trust\Ai;

class AiProviderRegistry
{
    /** @return array<string, mixed>|null */
    public function provider(string $provider): ?array
    {
        $record = config("ai-governance.providers.{$provider}");

        return is_array($record) ? $record : null;
    }
}
