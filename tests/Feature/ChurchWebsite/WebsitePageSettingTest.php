<?php

namespace Tests\Feature\ChurchWebsite;

use App\Enums\ChurchRole;
use App\Enums\WebsitePageType;
use App\Filament\Clusters\Website\Pages\PageSettings;
use App\Models\Church;
use App\Models\User;
use App\Models\WebsitePageSetting;
use App\PublicWebsite\WebsitePageConfiguration;
use App\Support\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * K-WEB-V1-001D-B §80/§81/§87/§88 — the `website_page_settings` model,
 * its tenancy, its authorization, and — most importantly — the mandatory
 * backward-compatibility guarantee: a Church with zero rows must behave
 * exactly like the pre-K-WEB-V1-001D-B Website.
 */
class WebsitePageSettingTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // §81 — model / uniqueness / tenancy
    // ---------------------------------------------------------------

    public function test_church_page_type_uniqueness_is_enforced(): void
    {
        $church = Church::create(['name' => 'Unique Church', 'slug' => 'unique-page-settings-church']);
        $this->actingAs(User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();

        WebsitePageSetting::create(['page_type' => WebsitePageType::About->value, 'enabled' => true]);

        $this->expectException(QueryException::class);
        WebsitePageSetting::create(['page_type' => WebsitePageType::About->value, 'enabled' => false]);
    }

    public function test_cross_church_isolation_for_page_settings(): void
    {
        $churchA = Church::create(['name' => 'Church A', 'slug' => 'page-settings-church-a']);
        $userA = User::factory()->forChurch($churchA, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($userA);
        app(TenantContext::class)->forgetResolved();
        WebsitePageSetting::create(['page_type' => WebsitePageType::About->value, 'enabled' => false, 'navigation_label' => 'Secret A Label']);

        $churchB = Church::create(['name' => 'Church B', 'slug' => 'page-settings-church-b']);
        $userB = User::factory()->forChurch($churchB, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($userB);
        app(TenantContext::class)->forgetResolved();

        $this->assertSame(0, WebsitePageSetting::query()->count(), 'Church B must not see Church A\'s page settings.');
        $effective = app(WebsitePageConfiguration::class)->effectiveForChurch($churchB->id);
        $this->assertTrue($effective[WebsitePageType::About->value]['enabled'], 'Church B\'s own About page must default to enabled, unaffected by Church A\'s row.');

        $this->assertSame(
            1,
            WebsitePageSetting::withoutGlobalScope('church_tenant')->where('church_id', $churchA->id)->count(),
            'Church A\'s row must still exist, untouched.',
        );
    }

    public function test_no_tenant_context_resolves_no_page_setting_records(): void
    {
        $church = Church::create(['name' => 'No Context Church', 'slug' => 'no-context-page-settings-church']);
        $this->actingAs(User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();
        WebsitePageSetting::create(['page_type' => WebsitePageType::About->value, 'enabled' => false]);

        Auth::logout();
        app(TenantContext::class)->forgetResolved();

        $this->assertSame(0, WebsitePageSetting::query()->count(), 'No TenantContext must fail closed, not resolve every Church\'s rows.');
    }

    // ---------------------------------------------------------------
    // §32/§34/§35 — required-page / effective-enablement resolution
    // ---------------------------------------------------------------

    public function test_a_required_page_always_resolves_enabled_regardless_of_any_stored_row(): void
    {
        $church = Church::create(['name' => 'Required Page Church', 'slug' => 'required-page-church']);
        $this->actingAs(User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();

        // Even a defensively-malformed row claiming Home is disabled
        // must never be honored — required pages are forced enabled.
        WebsitePageSetting::create(['page_type' => WebsitePageType::Home->value, 'enabled' => false]);

        $effective = app(WebsitePageConfiguration::class)->effectiveForChurch($church->id);
        $this->assertTrue($effective[WebsitePageType::Home->value]['enabled']);
    }

    public function test_zero_settings_rows_resolve_the_legacy_all_enabled_default(): void
    {
        $church = Church::create(['name' => 'Legacy Default Church', 'slug' => 'legacy-default-church']);
        $this->actingAs(User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();

        $effective = app(WebsitePageConfiguration::class)->effectiveForChurch($church->id);

        foreach (WebsitePageType::cases() as $type) {
            $this->assertTrue($effective[$type->value]['enabled'], "{$type->value} must default to enabled with zero stored rows.");
            $this->assertSame($type->defaultNavOrder(), $effective[$type->value]['nav_order']);
            $this->assertNull($effective[$type->value]['navigation_label']);
        }
    }

    public function test_optional_page_enablement_nav_order_and_label_resolve_from_a_stored_row(): void
    {
        $church = Church::create(['name' => 'Override Church', 'slug' => 'override-page-settings-church']);
        $this->actingAs(User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();

        WebsitePageSetting::create([
            'page_type' => WebsitePageType::Ministries->value,
            'enabled' => false,
            'nav_order' => 5,
            'navigation_label' => 'Our Ministries',
        ]);

        $effective = app(WebsitePageConfiguration::class)->effectiveForChurch($church->id);
        $this->assertFalse($effective[WebsitePageType::Ministries->value]['enabled']);
        $this->assertSame(5, $effective[WebsitePageType::Ministries->value]['nav_order']);
        $this->assertSame('Our Ministries', $effective[WebsitePageType::Ministries->value]['navigation_label']);
    }

    // ---------------------------------------------------------------
    // §88 — authorization
    // ---------------------------------------------------------------

    /**
     * K-WEB-V1-001D-B §27/§28/§88 — page settings reuse the pre-existing
     * `website.content.*` vocabulary exactly as-is. Administrator does
     * not hold `WebsiteContentView`/`WebsiteContentManage` today (only
     * Communications does — confirmed in `ChurchRole::capabilities()`,
     * unmodified by this milestone), so Administrator is correctly
     * denied here too — no role's existing authority was widened or
     * narrowed to accommodate this feature.
     */
    public function test_communications_can_manage_page_settings_administrator_and_care_cannot(): void
    {
        $church = Church::create(['name' => 'Authorization Church', 'slug' => 'page-settings-authorization-church']);

        $communications = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($communications);
        app(TenantContext::class)->forgetResolved();
        $this->assertTrue(Gate::allows('viewAny', WebsitePageSetting::class));
        $this->assertTrue(PageSettings::canAccess());

        foreach ([ChurchRole::ADMINISTRATOR, ChurchRole::CARE] as $role) {
            $user = User::factory()->forChurch($church, [$role], primary: false)->create();
            $this->actingAs($user);
            app(TenantContext::class)->forgetResolved();
            $this->assertFalse(Gate::allows('viewAny', WebsitePageSetting::class), "{$role->value} must not gain Website authoring authority merely because this feature exists.");
            $this->assertFalse(PageSettings::canAccess());
        }
    }

    public function test_a_user_with_no_church_membership_at_all_is_denied(): void
    {
        // Structural proof that Organization-only/Platform-only accounts
        // (which never carry a ChurchMembership at all) cannot gain local
        // Church Website page-setting authority — `membershipFor()`
        // resolves null the moment no ChurchMembership exists, entirely
        // independent of any Organization/Platform role.
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertFalse(Gate::allows('viewAny', WebsitePageSetting::class));
        $this->assertFalse(PageSettings::canAccess());
    }

    public function test_page_settings_management_page_upserts_bounded_optional_page_rows(): void
    {
        $church = Church::create(['name' => 'Save Church', 'slug' => 'page-settings-save-church']);
        $this->actingAs(User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create());
        app(TenantContext::class)->forgetResolved();

        Livewire::test(PageSettings::class)
            ->fillForm([
                WebsitePageType::About->value => ['enabled' => false, 'nav_order' => 25, 'navigation_label' => 'Our Story'],
            ])
            ->call('save');

        $row = WebsitePageSetting::query()->where('page_type', WebsitePageType::About->value)->first();
        $this->assertNotNull($row);
        $this->assertFalse($row->enabled);
        $this->assertSame(25, $row->nav_order);
        $this->assertSame('Our Story', $row->navigation_label);
    }

    /**
     * K-WEB-V1-001D-B §38 — bounded plain text, never HTML/Markdown. The
     * 60-character bound is enforced at the schema level in MySQL (the
     * production/local dev database) and at the Filament form level
     * (`TextInput::maxLength(60)`) — this test's PHPUnit connection is
     * SQLite, which does not enforce `VARCHAR` length, so the schema-level
     * bound itself is confirmed by direct migration inspection instead of
     * a query-exception assertion here.
     */
    public function test_navigation_label_column_is_bounded_to_sixty_characters_in_the_migration(): void
    {
        $migration = file_get_contents(base_path('database/migrations/2026_09_06_120000_create_website_page_settings_table.php'));

        $this->assertStringContainsString("string('navigation_label', 60)", $migration);
    }

    public function test_navigation_label_is_never_rendered_as_html(): void
    {
        $layout = file_get_contents(resource_path('views/components/public-website/proclaim-layout.blade.php'));

        // Every place `$item['label']` is printed uses Blade's escaping
        // `{{ }}` syntax, never the raw `{!! !!}` output — a navigation
        // label can never inject markup into the public page.
        $this->assertStringNotContainsString("{!! \$item['label']", $layout);
        $this->assertStringContainsString("{{ \$item['label'] }}", $layout);
    }
}
