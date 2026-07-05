<?php

namespace Tests\Feature\Tenancy;

use App\Filament\Resources\CongregationResource;
use App\Filament\Resources\CongregationResource\Pages\ListCongregationMembers;
use App\Models\Church;
use App\Models\CongregationMember;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CrossChurchCongregationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected Church $churchA;

    protected Church $churchB;

    protected User $userA;

    protected User $userB;

    protected CongregationMember $memberA;

    protected CongregationMember $memberB;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

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
        $this->memberA = CongregationMember::create([
            'first_name' => 'Alice',
            'last_name' => 'ChurchA',
            'phone' => '555-0001',
            'status' => 'active',
        ]);

        $this->actingAs($this->userB);
        $this->memberB = CongregationMember::create([
            'first_name' => 'Bob',
            'last_name' => 'ChurchB',
            'phone' => '555-0002',
            'status' => 'active',
        ]);
    }

    public function test_tenant_query_returns_only_own_church_members(): void
    {
        $this->actingAs($this->userA);
        $idsForA = CongregationMember::query()->pluck('id')->all();
        $this->assertContains($this->memberA->id, $idsForA);
        $this->assertNotContains($this->memberB->id, $idsForA);

        $this->actingAs($this->userB);
        $idsForB = CongregationMember::query()->pluck('id')->all();
        $this->assertContains($this->memberB->id, $idsForB);
        $this->assertNotContains($this->memberA->id, $idsForB);
    }

    public function test_tenant_scoped_query_cannot_resolve_other_church_member(): void
    {
        $this->actingAs($this->userA);

        $resolved = CongregationMember::query()->find($this->memberB->id);

        $this->assertNull($resolved);
    }

    public function test_congregation_resource_query_excludes_other_church_records(): void
    {
        $this->actingAs($this->userA);

        $ids = CongregationResource::getEloquentQuery()->pluck('id')->all();

        $this->assertContains($this->memberA->id, $ids);
        $this->assertNotContains($this->memberB->id, $ids);
    }

    public function test_congregation_list_page_hides_other_church_members(): void
    {
        Livewire::actingAs($this->userA)
            ->test(ListCongregationMembers::class)
            ->assertCanSeeTableRecords([$this->memberA])
            ->assertCanNotSeeTableRecords([$this->memberB]);
    }
}
