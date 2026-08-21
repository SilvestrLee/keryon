<?php

namespace App\Enums;

/**
 * System-defined church responsibility roles. Composable — a single
 * ChurchMembership may hold more than one. Churches cannot define their
 * own roles. See Keryon Blueprint v1.4.1 §8-§9.
 *
 * Deliberately excludes Primary Administrator — that is an account-ownership
 * designation (ChurchMembership::is_primary), not a responsibility role.
 * See Keryon Blueprint v1.4.1 §7.
 */
enum ChurchRole: string
{
    case ADMINISTRATOR = 'administrator';
    case COMMUNICATIONS = 'communications';
    case CARE = 'care';

    public function label(): string
    {
        return match ($this) {
            self::ADMINISTRATOR => 'Administrator',
            self::COMMUNICATIONS => 'Communications',
            self::CARE => 'Care',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->all();
    }

    /**
     * The single authoritative responsibility → capability mapping. Fixed,
     * explicit, greppable — not a database-backed permissions table.
     * ChurchMembership::hasCapability() unions this across every role held
     * by the membership; this method carries no TenantContext, Policy, or
     * ownership logic of its own. See Keryon Blueprint v1.4.1 §9 and
     * K-AUTH-001B §11-§14 for the approved matrix this encodes verbatim.
     *
     * `Capability::WebsiteDomainManage` is deliberately never returned by
     * any role here — it is reserved in the catalogue but intentionally
     * unassigned pending future Primary-governance/K-DOMAIN-001 design.
     * See K-AUTH-001B §16.
     *
     * `Capability::ChurchIdentityView/Manage` and `MediaView/Manage`
     * (K-CHURCHWEB-001B) are granted only to COMMUNICATIONS, matching
     * every other Website-adjacent capability — Administrator does not
     * automatically gain them, per Keryon Blueprint v1.4.1 §9's
     * responsibility-boundary rule. See K-CHURCHWEB-001B §33.
     *
     * @return list<Capability>
     */
    public function capabilities(): array
    {
        return match ($this) {
            self::ADMINISTRATOR => [
                Capability::ChurchView,
                Capability::ChurchManage,
                Capability::CongregationView,
                Capability::CongregationManage,
                Capability::StaffView,
                Capability::StaffManage,
            ],
            self::COMMUNICATIONS => [
                Capability::CongregationView,
                Capability::ContentView,
                Capability::ContentManage,
                Capability::FaithflowUse,
                Capability::CampaignsView,
                Capability::CampaignsManage,
                Capability::WebsiteContentView,
                Capability::WebsiteContentManage,
                Capability::WebsiteThemeManage,
                Capability::WebsitePublish,
                Capability::ChurchIdentityView,
                Capability::ChurchIdentityManage,
                Capability::MediaView,
                Capability::MediaManage,
            ],
            self::CARE => [
                Capability::CongregationView,
                Capability::CareView,
                Capability::CareManage,
            ],
        };
    }
}
