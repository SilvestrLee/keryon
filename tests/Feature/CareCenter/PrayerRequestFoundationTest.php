<?php

namespace Tests\Feature\CareCenter;

use App\Enums\PrayerRequestStatus;
use App\Models\Church;
use App\Models\CongregationMember;
use App\Models\PrayerRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrayerRequestFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_create_a_prayer_request_for_the_authenticated_users_church_without_mass_assigning_church_id(): void
    {
        $church = Church::create([
            'name' => 'Test Church',
            'slug' => 'test-church',
        ]);

        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user);

        $prayerRequest = PrayerRequest::create([
            'requester_name' => 'Jane Doe',
            'request' => 'Please pray for my family.',
        ]);

        $this->assertSame($church->id, $prayerRequest->church_id);
        $this->assertNotContains('church_id', $prayerRequest->getFillable());
    }

    public function test_it_casts_status_to_prayer_request_status(): void
    {
        $church = Church::create([
            'name' => 'Test Church',
            'slug' => 'test-church',
        ]);

        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user);

        $prayerRequest = PrayerRequest::create([
            'request' => 'Please pray for healing.',
            'status' => 'praying',
        ]);

        $this->assertInstanceOf(PrayerRequestStatus::class, $prayerRequest->status);
        $this->assertSame(PrayerRequestStatus::PRAYING, $prayerRequest->status);
    }

    public function test_it_can_belong_to_a_congregation_member(): void
    {
        $church = Church::create([
            'name' => 'Test Church',
            'slug' => 'test-church',
        ]);

        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user);

        $member = CongregationMember::create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'phone' => '555-0101',
            'status' => 'active',
        ]);

        $prayerRequest = PrayerRequest::create([
            'congregation_member_id' => $member->id,
            'request' => 'Please pray for my recovery.',
        ]);

        $this->assertTrue($prayerRequest->congregationMember->is($member));
    }

    public function test_it_soft_deletes_prayer_requests(): void
    {
        $church = Church::create([
            'name' => 'Test Church',
            'slug' => 'test-church',
        ]);

        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user);

        $prayerRequest = PrayerRequest::create([
            'request' => 'Please pray for guidance.',
        ]);

        $prayerRequest->delete();

        $this->assertSoftDeleted($prayerRequest);
    }

    public function test_visibility_defaults_to_private(): void
    {
        $church = Church::create([
            'name' => 'Test Church',
            'slug' => 'test-church',
        ]);

        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user);

        $prayerRequest = PrayerRequest::create([
            'request' => 'Please pray for peace.',
        ]);

        $this->assertSame('private', $prayerRequest->visibility);
    }
}
