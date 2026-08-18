<?php

namespace App\Enums;

/**
 * The fixed, code-defined authorization vocabulary Keryon policies and
 * Filament resources check against. Not database-backed — capabilities are
 * identifiers for application authorization logic only. See Keryon
 * Blueprint v1.4.1 §9-§10 and K-AUTH-001A/§O-§P.
 *
 * A membership's capabilities are always resolved through
 * ChurchRole::capabilities() → ChurchMembership::hasCapability(), never
 * checked directly as a role. See K-AUTH-001B §7.
 */
enum Capability: string
{
    case ChurchView = 'church.view';
    case ChurchManage = 'church.manage';

    case CongregationView = 'congregation.view';
    case CongregationManage = 'congregation.manage';

    case CareView = 'care.view';
    case CareManage = 'care.manage';

    case ContentView = 'content.view';
    case ContentManage = 'content.manage';

    case FaithflowUse = 'faithflow.use';

    case CampaignsView = 'campaigns.view';
    case CampaignsManage = 'campaigns.manage';

    case WebsiteContentView = 'website.content.view';
    case WebsiteContentManage = 'website.content.manage';
    case WebsiteThemeManage = 'website.theme.manage';
    case WebsitePublish = 'website.publish';
    case WebsiteDomainManage = 'website.domain.manage';

    case StaffView = 'staff.view';
    case StaffManage = 'staff.manage';

    public function label(): string
    {
        return match ($this) {
            self::ChurchView => 'View Church Settings',
            self::ChurchManage => 'Manage Church Settings',
            self::CongregationView => 'View Congregation',
            self::CongregationManage => 'Manage Congregation',
            self::CareView => 'View Care Center',
            self::CareManage => 'Manage Care Center',
            self::ContentView => 'View Content Studio',
            self::ContentManage => 'Manage Content Studio',
            self::FaithflowUse => 'Use FaithFlow',
            self::CampaignsView => 'View Campaigns',
            self::CampaignsManage => 'Manage Campaigns',
            self::WebsiteContentView => 'View Website Content',
            self::WebsiteContentManage => 'Manage Website Content',
            self::WebsiteThemeManage => 'Manage Website Theme',
            self::WebsitePublish => 'Publish Website',
            self::WebsiteDomainManage => 'Manage Website Domain',
            self::StaffView => 'View Staff',
            self::StaffManage => 'Manage Staff',
        };
    }
}
