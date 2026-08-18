<?php

namespace Tests\Feature\Identity;

use App\Models\Church;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * users.church_id is a deliberately preserved, deprecated compatibility
 * bridge during the K-IDENTITY-001 transition — Blueprint v1.4.1 §3/§11,
 * preflight decision #9. These tests exist to prove that bridge holds,
 * not to justify writing any *new* code against users.church_id.
 */
class CompatibilityBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_membership_aware_test_helper_still_populates_the_legacy_church_id_column(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->forChurch($church)->create();

        $this->assertSame($church->id, $user->fresh()->church_id);
    }

    public function test_church_id_mirror_persists_independent_of_policy_conversion(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->forChurch($church)->create();

        // ContentItemPolicy / CongregationMemberPolicy / PrayerRequestPolicy
        // stopped reading $user->church_id in K-AUTH-001B — see
        // K-AUTH-001B §40 — but the physical compatibility mirror on
        // users.church_id remains intentionally intact until its own,
        // later, isolated retirement milestone. This proves the mirror
        // itself, not any current authorization behavior.
        $this->assertTrue($user->church_id !== null);
        $this->assertSame($church->id, $user->church_id);
    }
}
