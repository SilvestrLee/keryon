<?php

namespace App\Enums;

enum WorkspaceType: string
{
    case Church = 'church';
    case Organization = 'organization';
    case Central = 'central';

    public function label(): string
    {
        return match ($this) {
            self::Church => __('shell.church_workspace'),
            self::Organization => __('shell.organization_workspace'),
            self::Central => 'Keryon Central',
        };
    }
}
