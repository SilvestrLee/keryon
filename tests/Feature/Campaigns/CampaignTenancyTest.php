<?php

namespace Tests\Feature\Campaigns;

use App\Campaigns\CampaignCommunicationManager;
use App\Campaigns\CampaignManager;
use App\Enums\ChurchRole;
use App\Enums\CommunicationChannel;
use App\Models\Campaign;
use App\Models\Church;
use App\Models\ChurchMembership;
use App\Models\ContentItem;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_church_user_follows_active_tenant_context_not_legacy_user_church_id(): void
    {
        $churchA = Church::create(['name' => 'Campaign Church A', 'slug' => 'campaign-church-a']);
        $churchB = Church::create(['name' => 'Campaign Church B', 'slug' => 'campaign-church-b']);
        $user = User::factory()->create(['church_id' => $churchB->id]);
        ChurchMembership::factory()->for($user)->for($churchA)->create()->assignRoles([ChurchRole::COMMUNICATIONS]);
        ChurchMembership::factory()->for($user)->for($churchB)->create()->assignRoles([ChurchRole::CARE]);
        $this->actingAs($user);

        session(['active_church_id' => $churchA->id]);
        app(TenantContext::class)->forgetResolved();
        $campaign = app(CampaignManager::class)->create(['title' => 'Church A Campaign']);

        $this->assertSame($churchA->id, $campaign->church_id);

        session(['active_church_id' => $churchB->id]);
        app(TenantContext::class)->forgetResolved();
        $this->assertNull(Campaign::query()->find($campaign->id));

        $this->expectException(AuthorizationException::class);
        app(CampaignManager::class)->create(['title' => 'Care cannot create']);
    }

    public function test_church_cannot_mutate_another_church_campaign_or_link_its_content(): void
    {
        $churchA = Church::create(['name' => 'Tenant A', 'slug' => 'campaign-tenant-a']);
        $churchB = Church::create(['name' => 'Tenant B', 'slug' => 'campaign-tenant-b']);

        $userB = User::factory()->forChurch($churchB, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($userB);
        $campaignB = app(CampaignManager::class)->create(['title' => 'Church B Campaign']);
        $contentB = ContentItem::create(['title' => 'Church B content', 'content_type' => 'campaign_copy', 'body' => 'B']);

        $userA = User::factory()->forChurch($churchA, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($userA);
        app(TenantContext::class)->forgetResolved();
        $campaignA = app(CampaignManager::class)->create(['title' => 'Church A Campaign']);
        $communicationA = app(CampaignCommunicationManager::class)->add($campaignA, [
            'title' => 'Church A communication',
            'channel' => CommunicationChannel::GENERAL,
        ]);

        $this->assertNull(Campaign::query()->find($campaignB->id));

        try {
            app(CampaignManager::class)->update($campaignB, ['title' => 'Cross-tenant mutation']);
            $this->fail('Cross-Church Campaign mutation should fail.');
        } catch (AuthorizationException) {
            $this->assertSame('Church B Campaign', Campaign::withoutGlobalScope('church_tenant')->findOrFail($campaignB->id)->title);
        }

        $this->expectException(AuthorizationException::class);
        app(CampaignCommunicationManager::class)->linkContentItem($communicationA, $contentB);
    }

    public function test_no_tenant_context_queries_fail_closed(): void
    {
        $church = Church::create(['name' => 'Hidden Church', 'slug' => 'campaign-hidden']);
        $user = User::factory()->forChurch($church, [ChurchRole::COMMUNICATIONS])->create();
        $this->actingAs($user);
        $campaign = app(CampaignManager::class)->create(['title' => 'Hidden Campaign']);

        auth()->logout();
        session()->flush();
        app(TenantContext::class)->forgetResolved();

        $this->assertSame(0, Campaign::query()->count());

        $this->expectException(AuthorizationException::class);
        app(CampaignManager::class)->update($campaign, ['title' => 'Denied']);
    }
}
