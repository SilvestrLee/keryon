<?php

namespace Tests\Feature\ProductExperience;

use App\Models\Church;
use App\Models\User;
use App\Support\CurrentChurch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChurchNamePersonalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_the_authenticated_users_church_name(): void
    {
        $church = Church::create(['name' => 'Demo Church', 'slug' => 'demo-church']);
        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user);

        $this->assertSame('Demo Church', CurrentChurch::name());
    }

    public function test_it_falls_back_to_your_church_when_no_user_is_authenticated(): void
    {
        $this->assertSame('your church', CurrentChurch::name());
    }

    public function test_it_falls_back_to_your_church_when_the_user_has_no_church(): void
    {
        $user = User::factory()->create(['church_id' => null]);

        $this->actingAs($user);

        $this->assertSame('your church', CurrentChurch::name());
    }

    public function test_possessive_name_appends_apostrophe_s_for_the_churchs_name(): void
    {
        $church = Church::create(['name' => 'Demo Church', 'slug' => 'demo-church']);
        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user);

        $this->assertSame("Demo Church's", CurrentChurch::possessiveName());
    }

    public function test_possessive_name_falls_back_to_your_churchs_when_no_church_exists(): void
    {
        $this->assertSame("your church's", CurrentChurch::possessiveName());
    }

    public function test_possessive_name_appends_only_apostrophe_when_name_ends_in_s(): void
    {
        $church = Church::create(['name' => 'Believers Fellowship Saints', 'slug' => 'believers-fellowship-saints']);
        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user);

        $this->assertSame("Believers Fellowship Saints'", CurrentChurch::possessiveName());
    }
}
