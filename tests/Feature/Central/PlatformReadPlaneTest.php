<?php

namespace Tests\Feature\Central;

use App\Enums\PlatformAuditReasonCategory;
use App\Enums\PlatformRole;
use App\Enums\WorkspaceType;
use App\Filament\Central\Pages\Activations;
use App\Filament\Central\Pages\CentralHome;
use App\Filament\Central\Pages\Churches;
use App\Filament\Central\Pages\Deliveries;
use App\Filament\Central\Pages\Domains;
use App\Filament\Central\Pages\Organizations;
use App\Filament\Central\Pages\ProviderStatus;
use App\Filament\Central\Pages\Subscriptions;
use App\Models\User;
use App\Platform\PlatformStaffService;
use App\Search\GlobalSearchService;
use App\Support\OrganizationContext;
use App\Support\PlatformContext;
use App\Support\TenantContext;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformReadPlaneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Filament::setCurrentPanel(Filament::getPanel('central'));
    }

    protected function tearDown(): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        parent::tearDown();
    }

    public function test_support_can_render_only_approved_empty_read_surfaces_without_customer_context(): void
    {
        $this->asRole(PlatformRole::SUPPORT);
        foreach ([CentralHome::class, Churches::class, Activations::class, Organizations::class, Domains::class, Deliveries::class, ProviderStatus::class] as $page) {
            Livewire::test($page)->assertOk()->assertDontSee('Create')->assertDontSee('Delete');
        }
        Livewire::test(Subscriptions::class)->assertForbidden();
        $this->assertFalse(app(TenantContext::class)->hasContext());
        $this->assertFalse(app(OrganizationContext::class)->hasContext());
    }

    public function test_commercial_can_read_subscriptions_but_trust_cannot(): void
    {
        $this->asRole(PlatformRole::COMMERCIAL);
        Livewire::test(Subscriptions::class)->assertOk();
        $this->asRole(PlatformRole::TRUST_SECURITY);
        Livewire::test(Subscriptions::class)->assertForbidden();
        Livewire::test(ProviderStatus::class)->assertOk();
    }

    public function test_central_search_executes_only_capability_eligible_platform_providers(): void
    {
        $this->asRole(PlatformRole::SUPPORT);
        $queries = [];
        DB::listen(fn ($q) => $queries[] = $q->sql);
        app(GlobalSearchService::class)->search(WorkspaceType::Central, 'alpha');
        $sql = implode(' ', array_map('strtolower', $queries));
        foreach (['prayer_requests', 'congregation_members', 'content_items', 'campaigns', 'media_assets', 'subscriptions'] as $prohibited) {
            $this->assertStringNotContainsString($prohibited, $sql);
        }
    }

    public function test_new_central_runtime_has_no_prohibited_imports_or_scope_bypasses(): void
    {
        $files = array_merge(glob(app_path('Platform/Read/*.php')), glob(app_path('Filament/Central/Pages/*.php')), glob(app_path('Search/Central/*.php')));
        $source = implode("\n", array_map('file_get_contents', $files));
        foreach (['PrayerRequest', 'CareCenter', 'CongregationMember', 'ContentItem', 'Campaign', 'MediaAsset', 'TenantContext', 'OrganizationContext', 'withoutGlobalScope'] as $name) {
            $this->assertStringNotContainsString($name, $source);
        }
        foreach (['token_hash', 'verification_token_hash', 'sensitive_payload', 'tax_identifier_value'] as $field) {
            $this->assertStringNotContainsString("'{$field}'", $source);
        }
    }

    private function asRole(PlatformRole $role): void
    {
        $user = User::factory()->create();
        app(PlatformStaffService::class)->create($user, $role, null, PlatformAuditReasonCategory::PLATFORM_ADMINISTRATION, 'Read plane test');
        $this->actingAs($user)->withSession(['active_workspace_type' => 'central']);
        app(PlatformContext::class)->forgetResolved();
    }
}
