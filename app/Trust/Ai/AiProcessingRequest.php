<?php

namespace App\Trust\Ai;

use App\Enums\AiCapability;
use App\Enums\AiDataOrigin;
use App\Enums\DataClassification;

readonly class AiProcessingRequest
{
    public function __construct(
        public AiCapability $capability,
        public DataClassification $classification,
        public AiDataOrigin $origin,
        public int $churchId,
        public string $provider,
        public string $model,
        public bool $containsMedia = false,
    ) {}
}
