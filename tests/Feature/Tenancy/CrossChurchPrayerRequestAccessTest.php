<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\CongregationMember;
use App\Models\PrayerRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrossChurchPrayerRequestAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Church $churchA;

    protected Church $churchB;

    protected User $userA;

    protected User $userB;

    protected PrayerRequest $prayerRequestA;

    protected PrayerRequest $prayerRequestB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->churchA = Church::create([
            'name' => 'Church A',
            'slug' => 'church-a',
        ]);

        $this->churchB = Church::create([
            'name' => 'Church B',
            'slug' => 'church-b',
        ]);

        $this->userA = User::factory()->create(['church_id' => $this->churchA->id]);
        $this->userB = User::factory()->create(['church_id' => $this->churchB->id]);

        $this->actingAs($this->userA);
        $memberA = CongregationMember::create([
            'first_name' => 'Alice',
            'last_name' => 'ChurchA',
            'phone' => '555-0001',
            'status' => 'active',
        ]);
        $this->prayerRequestA = PrayerRequest::create([
            'congregation_member_id' => $memberA->id,
            'request' => 'Please pray for Church A.',
        ]);

        $this->actingAs($this->userB);
        $memberB = CongregationMember::create([
            'first_name' => 'Bob',
            'last_name' => 'ChurchB',
            'phone' => '555-0002',
            'status' => 'active',
        ]);
        $this->prayerRequestB = PrayerRequest::create([
            'congregation_member_id' => $memberB->id,
            'request' => 'Please pray for Church B.',
        ]);
    }

    public function test_church_a_user_sees_only_church_a_prayer_requests(): void
    {
        $this->actingAs($this->userA);

        $ids = PrayerRequest::query()->pluck('id')->all();

        $this->assertContains($this->prayerRequestA->id, $ids);
        $this->assertNotContains($this->prayerRequestB->id, $ids);
    }

    public function test_church_b_user_sees_only_church_b_prayer_requests(): void
    {
        $this->actingAs($this->userB);

        $ids = PrayerRequest::query()->pluck('id')->all();

        $this->assertContains($this->prayerRequestB->id, $ids);
        $this->assertNotContains($this->prayerRequestA->id, $ids);
    }

    public function test_church_a_user_cannot_resolve_church_b_prayer_request(): void
    {
        $this->actingAs($this->userA);

        $resolved = PrayerRequest::query()->find($this->prayerRequestB->id);

        $this->assertNull($resolved);
    }

    public function test_church_b_user_cannot_resolve_church_a_prayer_request(): void
    {
        $this->actingAs($this->userB);

        $resolved = PrayerRequest::query()->find($this->prayerRequestA->id);

        $this->assertNull($resolved);
    }

    public function test_prayer_request_auto_assigns_church_id_from_authenticated_user(): void
    {
        $this->actingAs($this->userA);

        $prayerRequest = PrayerRequest::create([
            'request' => 'Please pray without an explicit church_id.',
        ]);

        $this->assertSame($this->churchA->id, $prayerRequest->church_id);
    }
}
