<?php

namespace App\Communications\OrganizationInbox;

use App\Enums\OrganizationCommunicationDeliveryState;
use App\Enums\OrganizationCommunicationKind;
use App\Models\OrganizationCommunicationDelivery;
use App\Support\TenantContext;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * K-ORG-COMMS-001D §39-§45/§65 — the single canonical Church-scoped read
 * model behind the Organization Inbox. Always scoped to the current
 * TenantContext Church only; never broadens to sibling Churches or to
 * Organization-wide browsing. Eager-loads exactly what the list/detail
 * surfaces need — no full material bodies are ever loaded here.
 */
final class ChurchOrganizationCommunicationQuery
{
    public const STATE_ALL = 'all';

    public const STATE_AVAILABLE = 'available';

    public const STATE_ACCEPTED = 'accepted';

    public const STATE_DECLINED = 'declined';

    public const STATE_EXPIRED = 'expired';

    public function __construct(private readonly TenantContext $tenantContext) {}

    public function paginate(string $state = self::STATE_ALL, string $kind = '', string $search = '', int $perPage = 12): LengthAwarePaginator
    {
        $query = $this->scopedQuery();

        $this->applyStateFilter($query, $state);

        if (in_array($kind, ['communication', 'campaign'], true)) {
            $query->whereHas('revision.communication', fn (Builder $q) => $q->where('kind', $kind));
        }

        $term = trim($search);
        if ($term !== '') {
            $query->where(function (Builder $q) use ($term): void {
                $q->whereHas('revision', fn (Builder $revision) => $revision->where('title', 'like', '%'.$term.'%'))
                    ->orWhereHas('organization', fn (Builder $organization) => $organization->where('name', 'like', '%'.$term.'%'));
            });
        }

        return $query
            ->orderByDesc('available_at')
            ->paginate(min(max($perPage, 1), 50), pageName: 'inboxPage')
            ->through(fn (OrganizationCommunicationDelivery $delivery): ChurchOrganizationCommunicationSummary => $this->summarize($delivery));
    }

    /**
     * K-ORG-COMMS-001G §7/§27-§29 — the single, cheap count behind the
     * Church Dashboard's "Organization communications waiting" attention
     * item. Reuses `applyStateFilter()`'s existing `STATE_AVAILABLE`
     * definition unchanged — no second definition of "Available" is
     * introduced here, and nothing beyond the row count (no materials,
     * assets, Organization hierarchy, or import records) is ever loaded
     * to produce this number.
     */
    public function availableCount(): int
    {
        $query = $this->scopedQuery();
        $this->applyStateFilter($query, self::STATE_AVAILABLE);

        return $query->count();
    }

    public function findByUuid(string $uuid): OrganizationCommunicationDelivery
    {
        $delivery = OrganizationCommunicationDelivery::query()
            ->where('church_id', $this->currentChurchId())
            ->where('uuid', $uuid)
            ->with([
                'organization',
                'revision.materials',
                'revision.assets',
                'revision.communication',
                'responderMembership.user',
            ])
            ->firstOrFail();

        abort_unless(auth()->user()?->can('view', $delivery), 403);

        return $delivery;
    }

    public function summarize(OrganizationCommunicationDelivery $delivery): ChurchOrganizationCommunicationSummary
    {
        $revision = $delivery->revision;
        $communication = $revision?->communication;
        $responseState = $delivery->derivedResponseState();

        return new ChurchOrganizationCommunicationSummary(
            id: $delivery->id,
            uuid: $delivery->uuid,
            title: $revision?->title ?? 'Untitled communication',
            organizationName: $delivery->organization?->name ?? '—',
            kind: $communication?->kind->value ?? OrganizationCommunicationKind::COMMUNICATION->value,
            kindLabel: $communication?->kind === OrganizationCommunicationKind::CAMPAIGN ? 'Shared campaign' : 'Communication',
            revisionVersion: $revision?->version ?? 1,
            availableAt: (string) $delivery->available_at,
            availableUntil: $delivery->available_until !== null ? (string) $delivery->available_until : null,
            responseState: is_string($responseState) ? $responseState : $responseState->value,
            responseStateLabel: $this->stateLabel($responseState),
            adaptationPolicyLabel: $revision?->adaptation_policy->label() ?? '—',
        );
    }

    public function stateLabel(OrganizationCommunicationDeliveryState|string $state): string
    {
        $value = is_string($state) ? $state : $state->value;

        return match ($value) {
            self::STATE_AVAILABLE => 'Available',
            self::STATE_ACCEPTED => 'Accepted',
            self::STATE_DECLINED => 'Declined',
            self::STATE_EXPIRED => 'Expired',
            'withdrawn' => 'Withdrawn',
            default => str($value)->headline()->toString(),
        };
    }

    private function applyStateFilter(Builder $query, string $state): void
    {
        $now = Carbon::now();

        match ($state) {
            self::STATE_ACCEPTED => $query->whereNotNull('accepted_at'),
            self::STATE_DECLINED => $query->whereNotNull('declined_at'),
            self::STATE_EXPIRED => $query->whereNull('accepted_at')
                ->whereNull('declined_at')
                ->where('state', '!=', OrganizationCommunicationDeliveryState::WITHDRAWN->value)
                ->whereNotNull('available_until')
                ->where('available_until', '<', $now),
            self::STATE_AVAILABLE => $query->whereNull('accepted_at')
                ->whereNull('declined_at')
                ->where('state', '!=', OrganizationCommunicationDeliveryState::WITHDRAWN->value)
                ->where(fn (Builder $q) => $q->whereNull('available_until')->orWhere('available_until', '>=', $now)),
            default => null,
        };
    }

    private function scopedQuery(): Builder
    {
        $churchId = $this->currentChurchId();

        return OrganizationCommunicationDelivery::query()
            ->where('church_id', $churchId)
            ->with([
                'organization:id,name',
                'revision:id,organization_communication_id,title,version,adaptation_policy',
                'revision.communication:id,kind',
            ]);
    }

    private function currentChurchId(): int
    {
        $churchId = $this->tenantContext->currentChurchId();

        abort_if($churchId === null, 403);

        return $churchId;
    }
}
