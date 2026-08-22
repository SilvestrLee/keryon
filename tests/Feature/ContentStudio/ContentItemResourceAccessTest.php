<?php

namespace Tests\Feature\ContentStudio;

use App\Enums\ChurchRole;
use App\Filament\Resources\ContentItemResource;
use App\Filament\Resources\ContentItemResource\Pages\EditContentItem;
use App\Filament\Resources\ContentItemResource\Pages\ListContentItems;
use App\Filament\Resources\ContentItemResource\Pages\ViewContentItem;
use App\Models\Church;
use App\Models\ContentItem;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ContentItemResourceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_guest_is_redirected_to_login_instead_of_crashing(): void
    {
        $response = $this->get(ContentItemResource::getUrl('index'));

        $response->assertRedirect('/admin/login');
    }

    public function test_church_user_can_access_content_studio_list(): void
    {
        $church = Church::create(['name' => 'Test Church', 'slug' => 'test-church']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);

        Livewire::test(ListContentItems::class)->assertSuccessful();
    }

    public function test_church_a_user_cannot_view_church_b_content_via_direct_url(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'church-a']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b']);
        $userA = User::factory()->forChurch($churchA, [ChurchRole::COMMUNICATIONS])->create();
        $userB = User::factory()->forChurch($churchB, [ChurchRole::COMMUNICATIONS])->create();

        $this->actingAs($userB);
        $contentItemB = ContentItem::create([
            'title' => 'Church B Content',
            'content_type' => 'general',
            'body' => 'Body text.',
        ]);

        $this->actingAs($userA);
        $this->expectException(ModelNotFoundException::class);
        Livewire::test(ViewContentItem::class, ['record' => $contentItemB->getKey()]);
    }

    public function test_church_a_user_cannot_edit_church_b_content_via_direct_url(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'church-a']);
        $churchB = Church::create(['name' => 'Church B', 'slug' => 'church-b']);
        $userA = User::factory()->forChurch($churchA, [ChurchRole::COMMUNICATIONS])->create();
        $userB = User::factory()->forChurch($churchB, [ChurchRole::COMMUNICATIONS])->create();

        $this->actingAs($userB);
        $contentItemB = ContentItem::create([
            'title' => 'Church B Content',
            'content_type' => 'general',
            'body' => 'Body text.',
        ]);

        $this->actingAs($userA);
        $this->expectException(ModelNotFoundException::class);
        Livewire::test(EditContentItem::class, ['record' => $contentItemB->getKey()]);
    }
}
