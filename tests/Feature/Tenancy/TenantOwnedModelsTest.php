<?php

namespace Tests\Feature\Tenancy;

use App\Models\Church;
use App\Models\CongregationMember;
use App\Models\Concerns\BelongsToChurch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantOwnedModelsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Every tenant-owned model must be listed here. Add new tenant-owned
     * models to this list as soon as they are introduced.
     *
     * @var array<class-string>
     */
    protected array $tenantOwnedModels = [
        CongregationMember::class,
    ];

    public function test_tenant_owned_models_have_church_id_column(): void
    {
        foreach ($this->tenantOwnedModels as $modelClass) {
            $model = new $modelClass();

            $this->assertTrue(
                Schema::hasColumn($model->getTable(), 'church_id'),
                "{$modelClass} table is missing a church_id column."
            );
        }
    }

    public function test_tenant_owned_models_use_belongs_to_church(): void
    {
        foreach ($this->tenantOwnedModels as $modelClass) {
            $this->assertContains(
                BelongsToChurch::class,
                class_uses_recursive($modelClass),
                "{$modelClass} does not use the BelongsToChurch trait."
            );
        }
    }

    public function test_tenant_owned_models_expose_church_relationship(): void
    {
        foreach ($this->tenantOwnedModels as $modelClass) {
            $model = new $modelClass();

            $this->assertTrue(
                method_exists($model, 'church'),
                "{$modelClass} does not expose a church() relationship."
            );
        }
    }

    public function test_tenant_owned_models_exclude_church_id_from_fillable(): void
    {
        foreach ($this->tenantOwnedModels as $modelClass) {
            $model = new $modelClass();

            $this->assertNotContains(
                'church_id',
                $model->getFillable(),
                "{$modelClass} must not allow church_id in \$fillable."
            );
        }
    }

    public function test_congregation_member_auto_assigns_church_id_from_authenticated_user(): void
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

        $this->assertSame($church->id, $member->church_id);
    }
}
