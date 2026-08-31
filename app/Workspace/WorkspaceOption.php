<?php

namespace App\Workspace;

use App\Enums\WorkspaceType;

final readonly class WorkspaceOption
{
    public function __construct(
        public WorkspaceType $type,
        public int $id,
        public string $name,
        public bool $current,
    ) {}
}
