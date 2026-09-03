<?php

namespace App\Enums;

/**
 * K-ORG-COMMS-001B §41 — deliberately not `AssetProvenance` (Church
 * media's rights vocabulary): the options here describe how the
 * Organization itself holds rights to a communication asset, not how a
 * Church declared provenance for an uploaded image. Semantically
 * distinct, so a small dedicated enum rather than reuse.
 */
enum OrganizationCommunicationAssetRightsBasis: string
{
    case OWNED_BY_ORGANIZATION = 'owned_by_organization';
    case LICENSED_FOR_CAMPAIGN = 'licensed_for_campaign';
    case PERMISSION_OBTAINED = 'permission_obtained';
    case PUBLIC_DOMAIN = 'public_domain';

    public function label(): string
    {
        return match ($this) {
            self::OWNED_BY_ORGANIZATION => 'Owned by organization',
            self::LICENSED_FOR_CAMPAIGN => 'Licensed for this campaign',
            self::PERMISSION_OBTAINED => 'Permission obtained',
            self::PUBLIC_DOMAIN => 'Public domain / unrestricted',
        };
    }
}
