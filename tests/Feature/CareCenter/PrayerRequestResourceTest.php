<?php

namespace Tests\Feature\CareCenter;

use App\Filament\Resources\PrayerRequestResource\Pages\EditPrayerRequest;
use App\Filament\Resources\PrayerRequestResource\Pages\ListPrayerRequests;
use App\Filament\Resources\PrayerRequestResource\Pages\ViewPrayerRequest;
use App\Models\Church;
use App\Models\PrayerRequest;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PrayerRequestResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_guest_is_redirected_to_login_instead_of_crashing(): void
    {
        $response = $this->get('/admin/prayer-requests');

        $response->assertRedirect('/admin/login');
    }

    public function test_list_page_renders_and_shows_records_for_the_current_church(): void
    {
        $church = Church::create(['name' => 'QA Church', 'slug' => 'qa-church']);
        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user);
        $prayerRequest = PrayerRequest::create(['request' => 'Please pray for the QA suite.']);

        Livewire::test(ListPrayerRequests::class)
            ->assertCanSeeTableRecords([$prayerRequest]);
    }

    public function test_create_action_saves_a_prayer_request_without_exposing_church_id(): void
    {
        $church = Church::create(['name' => 'QA Church', 'slug' => 'qa-church']);
        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user);

        Livewire::test(ListPrayerRequests::class)
            ->callAction(CreateAction::class, data: [
                'requester_name' => 'Browser QA Test',
                'requester_email' => 'qa@example.com',
                'requester_phone' => '08000000000',
                'title' => 'Browser QA Prayer Request',
                'request' => 'Please pray for a smooth Keryon Care Center test.',
                'visibility' => 'private',
                'status' => 'new',
            ])
            ->assertHasNoActionErrors();

        $prayerRequest = PrayerRequest::where('title', 'Browser QA Prayer Request')->firstOrFail();

        $this->assertSame($church->id, $prayerRequest->church_id);
    }

    public function test_view_page_renders_for_an_existing_record(): void
    {
        $church = Church::create(['name' => 'QA Church', 'slug' => 'qa-church']);
        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user);
        $prayerRequest = PrayerRequest::create(['request' => 'Please pray for clarity.']);

        Livewire::test(ViewPrayerRequest::class, ['record' => $prayerRequest->getKey()])
            ->assertSuccessful();
    }

    public function test_edit_action_updates_status_and_visibility(): void
    {
        $church = Church::create(['name' => 'QA Church', 'slug' => 'qa-church']);
        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user);
        $prayerRequest = PrayerRequest::create(['request' => 'Please pray for strength.']);

        Livewire::test(EditPrayerRequest::class, ['record' => $prayerRequest->getKey()])
            ->fillForm([
                'status' => 'reviewed',
                'visibility' => 'care_team',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('reviewed', $prayerRequest->fresh()->status->value);
        $this->assertSame('care_team', $prayerRequest->fresh()->visibility);
    }

    public function test_delete_action_soft_deletes_instead_of_hard_deleting(): void
    {
        $church = Church::create(['name' => 'QA Church', 'slug' => 'qa-church']);
        $user = User::factory()->create(['church_id' => $church->id]);

        $this->actingAs($user);
        $prayerRequest = PrayerRequest::create(['request' => 'Please pray for peace.']);

        Livewire::test(ListPrayerRequests::class)
            ->callTableAction(DeleteAction::class, $prayerRequest);

        $this->assertNotContains(
            $prayerRequest->id,
            PrayerRequest::query()->pluck('id')->all()
        );

        $this->assertTrue(
            PrayerRequest::withTrashed()->whereKey($prayerRequest->id)->exists()
        );
    }
}
