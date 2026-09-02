<?php

namespace App\Enums;

enum OrganizationRole: string
{
    case ORGANIZATION_ADMINISTRATOR = 'organization_administrator';
    case UNIT_ADMINISTRATOR = 'unit_administrator';
    case ORGANIZATION_VIEWER = 'organization_viewer';

    public function label(): string
    {
        return match ($this) {
            self::ORGANIZATION_ADMINISTRATOR => 'Organization Administrator',
            self::UNIT_ADMINISTRATOR => 'Unit Administrator',
            self::ORGANIZATION_VIEWER => 'Organization Viewer',
        };
    }

    /** @return list<OrganizationCapability> */
    public function capabilities(): array
    {
        return match ($this) {
            self::ORGANIZATION_ADMINISTRATOR => OrganizationCapability::cases(),
            self::UNIT_ADMINISTRATOR => [
                OrganizationCapability::UnitsView,
                OrganizationCapability::UnitsManage,
                OrganizationCapability::ChurchesView,
                OrganizationCapability::ChurchesManageAssignments,
            ],
            self::ORGANIZATION_VIEWER => [
                OrganizationCapability::OrganizationView,
                OrganizationCapability::UnitsView,
                OrganizationCapability::ChurchesView,
            ],
        };
    }

    public function hasCapability(OrganizationCapability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }

    /** @return list<string> */
    public static function valuesGranting(OrganizationCapability $capability): array
    {
        return array_values(array_map(
            fn (self $role): string => $role->value,
            array_filter(self::cases(), fn (self $role): bool => $role->hasCapability($capability)),
        ));
    }
}
