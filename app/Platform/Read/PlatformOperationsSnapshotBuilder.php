<?php

namespace App\Platform\Read;

use App\Enums\PlatformCapability;
use App\Filament\Central\Pages\ActivationDetail;
use App\Filament\Central\Pages\DeliveryDetail;
use App\Filament\Central\Pages\DomainDetail;
use App\Filament\Central\Pages\ProviderStatus;
use App\Platform\Read\Dto\PlatformAttentionItem;
use App\Support\PlatformContext;
use Illuminate\Support\Facades\DB;

final class PlatformOperationsSnapshotBuilder
{
    /** @return list<PlatformAttentionItem> */
    public function build(): array
    {
        $context = app(PlatformContext::class);
        abort_unless($context->hasCapability(PlatformCapability::PlatformHomeView), 403);
        $items = [];
        if ($context->hasCapability(PlatformCapability::ActivationsView)) {
            foreach (DB::table('church_activations as a')->join('churches as c', 'c.id', '=', 'a.church_id')->whereIn('a.status', ['commercial_review', 'expired'])->orderByDesc('a.created_at')->limit(5)->get(['a.id', 'a.uuid', 'a.status', 'a.created_at', 'c.name']) as $r) {
                $items[] = new PlatformAttentionItem('Activation', 'warning', str($r->status)->replace('_', ' ')->headline()->toString(), 'activation', $r->uuid, $r->name, (string) $r->created_at, null, ActivationDetail::getUrl(['record' => $r->id], panel: 'central'), PlatformCapability::ActivationsView);
            }
        }
        if ($context->hasCapability(PlatformCapability::DeliveriesView)) {
            foreach (DB::table('invitation_delivery_attempts as d')->whereIn('status', ['failed', 'bounced', 'complained'])->orderByDesc('requested_at')->limit(5)->get(['id', 'uuid', 'status', 'failure_category', 'requested_at']) as $r) {
                $items[] = new PlatformAttentionItem('Delivery', 'critical', $r->failure_category ?? str($r->status)->headline()->toString(), 'delivery', $r->uuid, 'Invitation delivery '.$r->status, (string) $r->requested_at, null, DeliveryDetail::getUrl(['record' => $r->id], panel: 'central'), PlatformCapability::DeliveriesView);
            }
        }
        if ($context->hasCapability(PlatformCapability::DomainsView)) {
            foreach (DB::table('church_domains')->where(fn ($q) => $q->where('status', 'degraded')->orWhere('tls_status', 'failed'))->orderByDesc('last_checked_at')->limit(5)->get(['id', 'uuid', 'normalized_hostname', 'failure_code', 'last_checked_at']) as $r) {
                $items[] = new PlatformAttentionItem('Domain', 'critical', $r->failure_code ?? 'Domain degraded', 'domain', $r->uuid, $r->normalized_hostname, null, $r->last_checked_at ? (string) $r->last_checked_at : null, DomainDetail::getUrl(['record' => $r->id], panel: 'central'), PlatformCapability::DomainsView);
            }
        }
        if ($context->hasCapability(PlatformCapability::ProvidersView)) {
            foreach (app(PlatformProviderStatusQuery::class)->all() as $p) {
                if ($p->blockers) {
                    $items[] = new PlatformAttentionItem('Provider', 'warning', $p->blockers[0], 'provider', $p->key, $p->name, null, $p->lastCheckedAt, ProviderStatus::getUrl(panel: 'central'), PlatformCapability::ProvidersView);
                }
            }
        }

        return array_slice($items, 0, 20);
    }
}
