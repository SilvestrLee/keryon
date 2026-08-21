<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\ChurchRole;
use App\Enums\DayOfWeek;
use App\Enums\SocialPlatform;
use App\Models\Church;
use App\Models\ChurchServiceTime;
use App\Models\ChurchSocialLink;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * K-CHURCHWEB-001B §37 — domain-boundary proof for the institutional
 * (not Website-owned) Church identity extensions: Social Links (§24) and
 * Service Times (§23). Both authorize against `church.identity.*`.
 */
class ChurchInstitutionalIdentityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_communications_user_can_manage_social_links_for_their_own_church(): void
    {
        $church = Church::create(['name' => 'Social Church', 'slug' => 'social-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        $link = ChurchSocialLink::create([
            'platform' => SocialPlatform::INSTAGRAM->value,
            'url' => 'https://instagram.com/example',
            'sort_order' => 1,
        ]);

        $this->assertSame($church->id, $link->church_id);
        $this->assertSame(SocialPlatform::INSTAGRAM, $link->fresh()->platform);
        $this->assertTrue(Gate::allows('update', $link));
    }

    public function test_church_a_user_cannot_view_church_bs_social_links(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'church-a-social']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b-social']);
        $userA = User::factory()->forChurch($churchA, [ChurchRole::COMMUNICATIONS])->create();
        $userB = User::factory()->forChurch($churchB, [ChurchRole::COMMUNICATIONS])->create();

        $this->actingAs($userB);
        $linkB = ChurchSocialLink::create([
            'platform' => SocialPlatform::FACEBOOK->value,
            'url' => 'https://facebook.com/example',
        ]);

        $this->actingAs($userA);

        $this->assertFalse(Gate::allows('view', $linkB));
        $this->assertNull(ChurchSocialLink::find($linkB->id));
    }

    public function test_care_user_cannot_manage_social_links(): void
    {
        $church = Church::create(['name' => 'Care Social Church', 'slug' => 'care-social-church']);
        $commsUser = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($commsUser);
        $link = ChurchSocialLink::create([
            'platform' => SocialPlatform::YOUTUBE->value,
            'url' => 'https://youtube.com/example',
        ]);

        $careUser = User::factory()->forChurch($church, [ChurchRole::CARE])->create();
        $this->actingAs($careUser);

        $this->assertFalse(Gate::allows('viewAny', ChurchSocialLink::class));
        $this->assertFalse(Gate::allows('update', $link));
    }

    public function test_communications_user_can_manage_service_times_for_their_own_church(): void
    {
        $church = Church::create(['name' => 'Service Church', 'slug' => 'service-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        $serviceTime = ChurchServiceTime::create([
            'label' => 'Sunday Worship',
            'day_of_week' => DayOfWeek::SUNDAY->value,
            'time' => '10:00 AM',
            'sort_order' => 1,
        ]);

        $this->assertSame($church->id, $serviceTime->church_id);
        $this->assertSame(DayOfWeek::SUNDAY, $serviceTime->fresh()->day_of_week);
        $this->assertTrue(Gate::allows('update', $serviceTime));
    }

    public function test_church_a_user_cannot_view_church_bs_service_times(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'church-a-service']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b-service']);
        $userA = User::factory()->forChurch($churchA, [ChurchRole::COMMUNICATIONS])->create();
        $userB = User::factory()->forChurch($churchB, [ChurchRole::COMMUNICATIONS])->create();

        $this->actingAs($userB);
        $serviceTimeB = ChurchServiceTime::create([
            'label' => 'Wednesday Bible Study',
            'time' => '7:00 PM',
        ]);

        $this->actingAs($userA);

        $this->assertFalse(Gate::allows('view', $serviceTimeB));
        $this->assertNull(ChurchServiceTime::find($serviceTimeB->id));
    }

    public function test_administrator_does_not_automatically_gain_church_identity_access(): void
    {
        $church = Church::create(['name' => 'Admin Identity Church', 'slug' => 'admin-identity-church']);
        $commsUser = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($commsUser);
        $serviceTime = ChurchServiceTime::create(['label' => 'Sunday Worship', 'time' => '10:00 AM']);

        $adminUser = User::factory()->forChurch($church, [ChurchRole::ADMINISTRATOR])->create();
        $this->actingAs($adminUser);

        $this->assertFalse(Gate::allows('view', $serviceTime));
        $this->assertFalse(Gate::allows('viewAny', ChurchServiceTime::class));
    }

    public function test_service_time_and_social_link_relations_are_available_on_church(): void
    {
        $church = Church::create(['name' => 'Relation Church', 'slug' => 'relation-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        ChurchServiceTime::create(['label' => 'Sunday Worship', 'time' => '10:00 AM']);
        ChurchSocialLink::create(['platform' => SocialPlatform::X->value, 'url' => 'https://x.com/example']);

        $this->assertCount(1, $church->serviceTimes()->get());
        $this->assertCount(1, $church->socialLinks()->get());
    }
}
