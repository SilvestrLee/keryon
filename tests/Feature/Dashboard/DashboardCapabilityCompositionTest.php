<?php

namespace Tests\Feature\Dashboard;

use App\Dashboard\ChurchDashboardSnapshotBuilder;
use App\Enums\ChurchRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsDashboardContext;
use Tests\TestCase;

class DashboardCapabilityCompositionTest extends TestCase
{
    use BuildsDashboardContext;
    use RefreshDatabase;

    /** @param list<ChurchRole> $roles @param list<string> $summaries */
    #[DataProvider('compositions')]
    public function test_dashboard_signal_families_follow_capability_union(array $roles, bool $primary, array $summaries): void
    {
        $this->dashboardActor($roles, $primary);
        $actual = array_keys(app(ChurchDashboardSnapshotBuilder::class)->build()->summaries);
        $this->assertEqualsCanonicalizing($summaries, $actual);
    }

    public static function compositions(): array
    {
        return [
            'primary admin communications' => [[ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS], true, ['campaigns', 'congregation']],
            'administrator' => [[ChurchRole::ADMINISTRATOR], false, ['congregation']],
            'communications' => [[ChurchRole::COMMUNICATIONS], false, ['campaigns', 'congregation']],
            'care' => [[ChurchRole::CARE], false, ['care', 'congregation']],
            'administrator care' => [[ChurchRole::ADMINISTRATOR, ChurchRole::CARE], false, ['care', 'congregation']],
            'communications care' => [[ChurchRole::COMMUNICATIONS, ChurchRole::CARE], false, ['campaigns', 'care', 'congregation']],
            'administrator communications' => [[ChurchRole::ADMINISTRATOR, ChurchRole::COMMUNICATIONS], false, ['campaigns', 'congregation']],
        ];
    }
}
