<?php

namespace Tests\Feature\Authorization;

use App\Enums\Capability;
use App\Enums\ChurchRole;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Proves the ChurchRole → Capability mapping and its composition in
 * isolation from any Policy or TenantContext. See K-AUTH-001B §43/§49.
 */
class CapabilityMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function membership(array $roles = [], bool $primary = false): ChurchMembership
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church-'.uniqid()]);
        $user = User::factory()->create();

        $membership = ChurchMembership::factory()->for($church)->for($user)->state(['is_primary' => $primary])->create();
        $membership->assignRoles($roles);

        return $membership;
    }

    public function test_administrator_capability_set(): void
    {
        $membership = $this->membership([ChurchRole::ADMINISTRATOR]);

        $this->assertTrue($membership->hasCapability(Capability::ChurchView));
        $this->assertTrue($membership->hasCapability(Capability::ChurchManage));
        $this->assertTrue($membership->hasCapability(Capability::CongregationView));
        $this->assertTrue($membership->hasCapability(Capability::CongregationManage));
        $this->assertTrue($membership->hasCapability(Capability::StaffView));
        $this->assertTrue($membership->hasCapability(Capability::StaffManage));

        $this->assertFalse($membership->hasCapability(Capability::CareView));
        $this->assertFalse($membership->hasCapability(Capability::CareManage));
        $this->assertFalse($membership->hasCapability(Capability::ContentView));
        $this->assertFalse($membership->hasCapability(Capability::ContentManage));
        $this->assertFalse($membership->hasCapability(Capability::FaithflowUse));
        $this->assertFalse($membership->hasCapability(Capability::CampaignsView));
        $this->assertFalse($membership->hasCapability(Capability::CampaignsManage));
        $this->assertFalse($membership->hasCapability(Capability::WebsiteContentView));
        $this->assertFalse($membership->hasCapability(Capability::WebsiteContentManage));
        $this->assertFalse($membership->hasCapability(Capability::WebsiteThemeManage));
        $this->assertFalse($membership->hasCapability(Capability::WebsitePublish));
        $this->assertFalse($membership->hasCapability(Capability::WebsiteDomainManage));
    }

    public function test_communications_capability_set(): void
    {
        $membership = $this->membership([ChurchRole::COMMUNICATIONS]);

        $this->assertTrue($membership->hasCapability(Capability::CongregationView));
        $this->assertTrue($membership->hasCapability(Capability::ContentView));
        $this->assertTrue($membership->hasCapability(Capability::ContentManage));
        $this->assertTrue($membership->hasCapability(Capability::FaithflowUse));
        $this->assertTrue($membership->hasCapability(Capability::CampaignsView));
        $this->assertTrue($membership->hasCapability(Capability::CampaignsManage));
        $this->assertTrue($membership->hasCapability(Capability::WebsiteContentView));
        $this->assertTrue($membership->hasCapability(Capability::WebsiteContentManage));
        $this->assertTrue($membership->hasCapability(Capability::WebsiteThemeManage));
        $this->assertTrue($membership->hasCapability(Capability::WebsitePublish));

        $this->assertFalse($membership->hasCapability(Capability::CongregationManage));
        $this->assertFalse($membership->hasCapability(Capability::CareView));
        $this->assertFalse($membership->hasCapability(Capability::CareManage));
        $this->assertFalse($membership->hasCapability(Capability::StaffView));
        $this->assertFalse($membership->hasCapability(Capability::StaffManage));
        $this->assertFalse($membership->hasCapability(Capability::ChurchManage));
        $this->assertFalse($membership->hasCapability(Capability::WebsiteDomainManage));
    }

    public function test_care_capability_set(): void
    {
        $membership = $this->membership([ChurchRole::CARE]);

        $this->assertTrue($membership->hasCapability(Capability::CongregationView));
        $this->assertTrue($membership->hasCapability(Capability::CareView));
        $this->assertTrue($membership->hasCapability(Capability::CareManage));

        $this->assertFalse($membership->hasCapability(Capability::CongregationManage));
        $this->assertFalse($membership->hasCapability(Capability::ContentView));
        $this->assertFalse($membership->hasCapability(Capability::ContentManage));
        $this->assertFalse($membership->hasCapability(Capability::FaithflowUse));
        $this->assertFalse($membership->hasCapability(Capability::CampaignsView));
        $this->assertFalse($membership->hasCapability(Capability::CampaignsManage));
        $this->assertFalse($membership->hasCapability(Capability::WebsiteContentView));
        $this->assertFalse($membership->hasCapability(Capability::WebsiteContentManage));
        $this->assertFalse($membership->hasCapability(Capability::WebsiteThemeManage));
        $this->assertFalse($membership->hasCapability(Capability::WebsitePublish));
        $this->assertFalse($membership->hasCapability(Capability::WebsiteDomainManage));
        $this->assertFalse($membership->hasCapability(Capability::StaffView));
        $this->assertFalse($membership->hasCapability(Capability::StaffManage));
        $this->assertFalse($membership->hasCapability(Capability::ChurchManage));
    }

    public function test_administrator_and_communications_union(): void
    {
        $membership = $this->membership([ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS]);

        $this->assertTrue($membership->hasCapability(Capability::ChurchManage));
        $this->assertTrue($membership->hasCapability(Capability::CongregationManage));
        $this->assertTrue($membership->hasCapability(Capability::ContentManage));
        $this->assertTrue($membership->hasCapability(Capability::FaithflowUse));
        $this->assertTrue($membership->hasCapability(Capability::WebsitePublish));

        $this->assertFalse($membership->hasCapability(Capability::CareView));
        $this->assertFalse($membership->hasCapability(Capability::CareManage));
    }

    public function test_administrator_and_care_union(): void
    {
        $membership = $this->membership([ChurchRole::ADMINISTRATOR, ChurchRole::CARE]);

        $this->assertTrue($membership->hasCapability(Capability::ChurchManage));
        $this->assertTrue($membership->hasCapability(Capability::CongregationManage));
        $this->assertTrue($membership->hasCapability(Capability::CareView));
        $this->assertTrue($membership->hasCapability(Capability::CareManage));

        $this->assertFalse($membership->hasCapability(Capability::ContentManage));
        $this->assertFalse($membership->hasCapability(Capability::FaithflowUse));
    }

    public function test_communications_and_care_union(): void
    {
        $membership = $this->membership([ChurchRole::COMMUNICATIONS, ChurchRole::CARE]);

        $this->assertTrue($membership->hasCapability(Capability::ContentManage));
        $this->assertTrue($membership->hasCapability(Capability::FaithflowUse));
        $this->assertTrue($membership->hasCapability(Capability::CareView));
        $this->assertTrue($membership->hasCapability(Capability::CareManage));

        $this->assertFalse($membership->hasCapability(Capability::ChurchManage));
        $this->assertFalse($membership->hasCapability(Capability::CongregationManage));
        $this->assertFalse($membership->hasCapability(Capability::StaffManage));
    }

    public function test_administrator_communications_and_care_full_union(): void
    {
        $membership = $this->membership([ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS, ChurchRole::CARE]);

        foreach (Capability::cases() as $capability) {
            if ($capability === Capability::WebsiteDomainManage) {
                $this->assertFalse($membership->hasCapability($capability), 'website.domain.manage must remain unassigned even with all three roles.');

                continue;
            }

            $this->assertTrue($membership->hasCapability($capability), "Expected {$capability->value} to be present in the full role union.");
        }
    }

    public function test_no_roles_grants_no_capabilities(): void
    {
        $membership = $this->membership([]);

        foreach (Capability::cases() as $capability) {
            $this->assertFalse($membership->hasCapability($capability));
        }
    }

    public function test_primary_only_grants_no_role_capabilities(): void
    {
        $membership = $this->membership([], primary: true);

        $this->assertTrue($membership->is_primary);

        foreach (Capability::cases() as $capability) {
            $this->assertFalse($membership->hasCapability($capability), "Primary alone must not grant {$capability->value}.");
        }
    }

    public function test_primary_plus_administrator_still_has_no_care_or_communications(): void
    {
        $membership = $this->membership([ChurchRole::ADMINISTRATOR], primary: true);

        $this->assertTrue($membership->hasCapability(Capability::ChurchManage));
        $this->assertTrue($membership->hasCapability(Capability::CongregationManage));

        $this->assertFalse($membership->hasCapability(Capability::CareView));
        $this->assertFalse($membership->hasCapability(Capability::CareManage));
        $this->assertFalse($membership->hasCapability(Capability::ContentManage));
        $this->assertFalse($membership->hasCapability(Capability::FaithflowUse));
        $this->assertFalse($membership->hasCapability(Capability::WebsitePublish));
    }

    public function test_primary_plus_communications_still_has_no_care(): void
    {
        $membership = $this->membership([ChurchRole::COMMUNICATIONS], primary: true);

        $this->assertTrue($membership->hasCapability(Capability::ContentManage));
        $this->assertFalse($membership->hasCapability(Capability::CareView));
        $this->assertFalse($membership->hasCapability(Capability::CareManage));
        $this->assertFalse($membership->hasCapability(Capability::CongregationManage));
    }

    public function test_primary_plus_care_still_has_no_communications(): void
    {
        $membership = $this->membership([ChurchRole::CARE], primary: true);

        $this->assertTrue($membership->hasCapability(Capability::CareManage));
        $this->assertFalse($membership->hasCapability(Capability::ContentManage));
        $this->assertFalse($membership->hasCapability(Capability::FaithflowUse));
        $this->assertFalse($membership->hasCapability(Capability::CongregationManage));
    }

    /**
     * Future-surface capability mapping proof (K-AUTH-001B §49) — proves
     * the architecture for FaithFlow/Campaigns/Website/Staff without
     * implementing any of those surfaces.
     */
    public function test_future_capability_mappings(): void
    {
        $communications = $this->membership([ChurchRole::COMMUNICATIONS]);
        $administrator = $this->membership([ChurchRole::ADMINISTRATOR]);
        $care = $this->membership([ChurchRole::CARE]);
        $primaryOnly = $this->membership([], primary: true);

        $this->assertTrue($communications->hasCapability(Capability::CampaignsManage));
        $this->assertTrue($administrator->hasCapability(Capability::StaffManage));
        $this->assertFalse($care->hasCapability(Capability::WebsitePublish));
        $this->assertFalse($communications->hasCapability(Capability::WebsiteDomainManage));
        $this->assertFalse($primaryOnly->hasCapability(Capability::WebsiteDomainManage));
    }

    public function test_website_domain_manage_is_never_role_granted(): void
    {
        $membership = $this->membership([ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS, ChurchRole::CARE], primary: true);

        $this->assertFalse($membership->hasCapability(Capability::WebsiteDomainManage));
    }
}
