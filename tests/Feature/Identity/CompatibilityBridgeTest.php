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

    public function test_legacy_same_church_policy_checks_still_function_via_the_compatibility_mirror(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->forChurch($church)->create();

        // ContentItemPolicy / CongregationMemberPolicy are deliberately
        // untouched by K-IDENTITY-001B — see engineering doc §38/§12 —
        // and still read $user->church_id directly. This must keep
        // working until K-AUTH-001 converts them.
        $this->assertTrue($user->church_id !== null);
        $this->assertSame($church->id, $user->church_id);
    }
}
