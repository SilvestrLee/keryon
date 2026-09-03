<?php

namespace App\Organizations\Communications;

use App\Enums\OrganizationAuditEventType;
use App\Enums\OrganizationCapability;
use App\Enums\OrganizationCommunicationAdaptationPolicy;
use App\Enums\OrganizationCommunicationKind;
use App\Enums\OrganizationCommunicationMaterialType;
use App\Enums\OrganizationCommunicationRevisionState;
use App\Enums\OrganizationCommunicationState;
use App\Models\OrganizationCommunication;
use App\Models\OrganizationCommunicationAsset;
use App\Models\OrganizationCommunicationMaterial;
use App\Models\OrganizationCommunicationRevision;
use App\Models\OrganizationUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

class OrganizationCommunicationManager
{
    public function __construct(
        private readonly OrganizationCommunicationAuthorizer $authorizer,
        private readonly OrganizationCommunicationAudit $audit,
    ) {}

    /** @param array<string, mixed> $revisionAttributes */
    public function create(
        OrganizationUnit $governingUnit,
        OrganizationCommunicationKind $kind,
        array $revisionAttributes,
    ): OrganizationCommunication {
        return DB::transaction(function () use ($governingUnit, $kind, $revisionAttributes): OrganizationCommunication {
            $unit = OrganizationUnit::query()->lockForUpdate()->findOrFail($governingUnit->id);
            $actor = $this->authorizer->forUnit(OrganizationCapability::CommunicationsCreate, $unit);

            $communication = new OrganizationCommunication;
            $communication->forceFill([
                'organization_id' => $actor->organization_id,
                'governing_unit_id' => $unit->id,
                'created_by_organization_membership_id' => $actor->id,
                'kind' => $kind,
                'state' => OrganizationCommunicationState::DRAFT,
            ])->save();

            $this->persistRevision($communication, $actor->id, 1, $revisionAttributes);

            $this->audit->record(
                OrganizationAuditEventType::COMMUNICATION_CREATED,
                $communication,
                $actor,
                ['state' => OrganizationCommunicationState::DRAFT->value, 'kind' => $kind->value, 'revision' => 1],
            );

            return $communication->fresh(['revisions.materials']);
        }, 3);
    }

    /** @param array<string, mixed> $attributes */
    public function updateDraftRevision(
        OrganizationCommunicationRevision $revision,
        array $attributes,
    ): OrganizationCommunicationRevision {
        return DB::transaction(function () use ($revision, $attributes): OrganizationCommunicationRevision {
            $locked = $this->lockedRevision($revision);
            $this->authorizer->forCommunication(OrganizationCapability::CommunicationsEdit, $locked->communication);
            $this->assertDraft($locked);
            $locked->forceFill($this->canonicalAttributes($attributes, requireTitle: false))->save();

            return $locked->fresh();
        }, 3);
    }

    public function addMaterial(
        OrganizationCommunicationRevision $revision,
        OrganizationCommunicationMaterialType $type,
        string $body,
        ?string $title = null,
    ): OrganizationCommunicationMaterial {
        return DB::transaction(function () use ($revision, $type, $body, $title): OrganizationCommunicationMaterial {
            $locked = $this->lockedRevision($revision);
            $this->authorizer->forCommunication(OrganizationCapability::CommunicationsEdit, $locked->communication);
            $this->assertDraft($locked);
            $body = trim($body);
            if ($body === '') {
                throw ValidationException::withMessages(['body' => 'Communication material cannot be empty.']);
            }

            $material = new OrganizationCommunicationMaterial;
            $material->forceFill([
                'organization_communication_revision_id' => $locked->id,
                'type' => $type,
                'title' => filled($title) ? trim((string) $title) : null,
                'body' => $body,
                'sort_order' => ((int) $locked->materials()->max('sort_order')) + 1,
            ])->save();

            return $material->fresh();
        }, 3);
    }

    /** @param array{type?: OrganizationCommunicationMaterialType, title?: ?string, body?: string} $attributes */
    public function updateMaterial(
        OrganizationCommunicationMaterial $material,
        array $attributes,
    ): OrganizationCommunicationMaterial {
        return DB::transaction(function () use ($material, $attributes): OrganizationCommunicationMaterial {
            $locked = OrganizationCommunicationMaterial::query()->lockForUpdate()->findOrFail($material->id);
            $revision = $this->lockedRevision($locked->revision);
            $this->authorizer->forCommunication(OrganizationCapability::CommunicationsEdit, $revision->communication);
            $this->assertDraft($revision);

            $allowed = array_intersect_key($attributes, array_flip(['type', 'title', 'body']));
            if (array_key_exists('type', $allowed) && ! $allowed['type'] instanceof OrganizationCommunicationMaterialType) {
                throw ValidationException::withMessages(['type' => 'Choose a supported Organization communication material type.']);
            }
            if (array_key_exists('body', $allowed)) {
                $allowed['body'] = trim((string) $allowed['body']);
                if ($allowed['body'] === '') {
                    throw ValidationException::withMessages(['body' => 'Communication material cannot be empty.']);
                }
            }
            if (array_key_exists('title', $allowed)) {
                $allowed['title'] = filled($allowed['title']) ? trim((string) $allowed['title']) : null;
            }
            $locked->forceFill($allowed)->save();

            return $locked->fresh();
        }, 3);
    }

    public function removeMaterial(OrganizationCommunicationMaterial $material): void
    {
        DB::transaction(function () use ($material): void {
            $locked = OrganizationCommunicationMaterial::query()->lockForUpdate()->findOrFail($material->id);
            $revision = $this->lockedRevision($locked->revision);
            $this->authorizer->forCommunication(OrganizationCapability::CommunicationsEdit, $revision->communication);
            $this->assertDraft($revision);
            $locked->delete();

            $revision->materials()->orderBy('sort_order')->get()->values()->each(
                fn (OrganizationCommunicationMaterial $remaining, int $index) => $remaining->forceFill(['sort_order' => $index + 1])->save(),
            );
        }, 3);
    }

    /**
     * Swaps a material with its immediate neighbor. Uses a transient
     * negative sort_order so the swap never collides with the
     * (revision_id, sort_order) unique index mid-transaction.
     */
    public function moveMaterial(OrganizationCommunicationMaterial $material, string $direction): void
    {
        DB::transaction(function () use ($material, $direction): void {
            $locked = OrganizationCommunicationMaterial::query()->lockForUpdate()->findOrFail($material->id);
            $revision = $this->lockedRevision($locked->revision);
            $this->authorizer->forCommunication(OrganizationCapability::CommunicationsEdit, $revision->communication);
            $this->assertDraft($revision);

            $ordered = $revision->materials()->orderBy('sort_order')->get();
            $index = $ordered->search(fn (OrganizationCommunicationMaterial $item): bool => $item->id === $locked->id);
            $swapIndex = $direction === 'up' ? $index - 1 : $index + 1;

            if ($index === false || $swapIndex < 0 || $swapIndex >= $ordered->count()) {
                return;
            }

            $neighbor = $ordered->get($swapIndex);
            $originalOrder = $locked->sort_order;
            $neighborOrder = $neighbor->sort_order;

            // sort_order is unsigned; 0 is never assigned by addMaterial()
            // (which starts at 1), so it is a safe transient marker that
            // never collides with the (revision_id, sort_order) unique index.
            $locked->forceFill(['sort_order' => 0])->save();
            $neighbor->forceFill(['sort_order' => $originalOrder])->save();
            $locked->forceFill(['sort_order' => $neighborOrder])->save();
        }, 3);
    }

    public function createNextRevision(
        OrganizationCommunicationRevision $source,
    ): OrganizationCommunicationRevision {
        return DB::transaction(function () use ($source): OrganizationCommunicationRevision {
            $communication = OrganizationCommunication::query()->lockForUpdate()->findOrFail($source->organization_communication_id);
            $actor = $this->authorizer->forCommunication(OrganizationCapability::CommunicationsEdit, $communication);
            $lockedSource = OrganizationCommunicationRevision::query()
                ->where('organization_communication_id', $communication->id)
                ->lockForUpdate()
                ->findOrFail($source->id);

            if (! in_array($lockedSource->state, [
                OrganizationCommunicationRevisionState::APPROVED,
                OrganizationCommunicationRevisionState::DISTRIBUTED,
            ], true)) {
                throw new LogicException('Only an approved or distributed revision can seed the next Draft revision.');
            }

            $nextVersion = ((int) $communication->revisions()->max('version')) + 1;
            $next = $this->persistRevision($communication, $actor->id, $nextVersion, $lockedSource->canonicalAttributes());

            foreach ($lockedSource->materials()->orderBy('sort_order')->get() as $material) {
                $copy = new OrganizationCommunicationMaterial;
                $copy->forceFill([
                    'organization_communication_revision_id' => $next->id,
                    'type' => $material->type,
                    'title' => $material->title,
                    'body' => $material->body,
                    'sort_order' => $material->sort_order,
                ])->save();
            }

            // K-ORG-COMMS-001B §83 Option A: the next Draft revision
            // receives new asset records referencing the same immutable
            // stored file (same disk/path/sha256) as the approved source
            // — no byte duplication, and the source revision's asset rows
            // remain untouched and immutable.
            foreach ($lockedSource->assets()->get() as $asset) {
                $copy = new OrganizationCommunicationAsset;
                $copy->uuid = (string) Str::uuid();
                $copy->forceFill([
                    'organization_id' => $asset->organization_id,
                    'organization_communication_id' => $next->organization_communication_id,
                    'organization_communication_revision_id' => $next->id,
                    'uploaded_by_organization_membership_id' => $asset->uploaded_by_organization_membership_id,
                    'disk' => $asset->disk,
                    'path' => $asset->path,
                    'original_filename' => $asset->original_filename,
                    'mime_type' => $asset->mime_type,
                    'size' => $asset->size,
                    'sha256' => $asset->sha256,
                    'width' => $asset->width,
                    'height' => $asset->height,
                    'alt_text' => $asset->alt_text,
                    'rights_basis' => $asset->rights_basis,
                    'usage_guidance' => $asset->usage_guidance,
                    'attribution_required' => $asset->attribution_required,
                    'attribution_text' => $asset->attribution_text,
                ])->save();
            }

            $this->audit->record(
                OrganizationAuditEventType::COMMUNICATION_REVISION_CREATED,
                $next,
                $actor,
                ['state' => OrganizationCommunicationRevisionState::DRAFT->value, 'version' => $nextVersion, 'source_version' => $lockedSource->version],
            );

            return $next->fresh('materials');
        }, 3);
    }

    public function deleteDraft(OrganizationCommunication $communication): void
    {
        DB::transaction(function () use ($communication): void {
            $locked = OrganizationCommunication::query()->lockForUpdate()->findOrFail($communication->id);
            $this->authorizer->forCommunication(OrganizationCapability::CommunicationsEdit, $locked);
            if ($locked->state !== OrganizationCommunicationState::DRAFT) {
                throw new LogicException('Only a Draft Organization communication may be deleted.');
            }
            $locked->delete();
        }, 3);
    }

    private function lockedRevision(OrganizationCommunicationRevision $revision): OrganizationCommunicationRevision
    {
        return OrganizationCommunicationRevision::query()
            ->with('communication')
            ->lockForUpdate()
            ->findOrFail($revision->id);
    }

    private function assertDraft(OrganizationCommunicationRevision $revision): void
    {
        if ($revision->state !== OrganizationCommunicationRevisionState::DRAFT) {
            throw new LogicException('Only a Draft Organization communication revision may be edited.');
        }
    }

    /** @param array<string, mixed> $attributes @return array<string, mixed> */
    private function canonicalAttributes(array $attributes, bool $requireTitle): array
    {
        $allowed = array_intersect_key($attributes, array_flip(OrganizationCommunicationRevision::CANONICAL_FIELDS));
        if ($requireTitle && ! filled($allowed['title'] ?? null)) {
            throw ValidationException::withMessages(['title' => 'A communication title is required.']);
        }
        if (array_key_exists('title', $allowed)) {
            $allowed['title'] = trim((string) $allowed['title']);
            if ($allowed['title'] === '') {
                throw ValidationException::withMessages(['title' => 'A communication title is required.']);
            }
        }
        foreach (['summary', 'requested_action'] as $field) {
            if (array_key_exists($field, $allowed)) {
                $allowed[$field] = filled($allowed[$field]) ? trim((string) $allowed[$field]) : null;
            }
        }
        if (array_key_exists('adaptation_policy', $allowed)) {
            $policy = $allowed['adaptation_policy'];
            $allowed['adaptation_policy'] = $policy instanceof OrganizationCommunicationAdaptationPolicy
                ? $policy
                : OrganizationCommunicationAdaptationPolicy::tryFrom((string) $policy);
            if ($allowed['adaptation_policy'] === null) {
                throw ValidationException::withMessages(['adaptation_policy' => 'Choose a supported local adaptation policy.']);
            }
        }

        if ($requireTitle) {
            $allowed['adaptation_policy'] ??= OrganizationCommunicationAdaptationPolicy::LOCAL_ADAPTATION_ENCOURAGED;
        }

        return $allowed;
    }

    /** @param array<string, mixed> $attributes */
    private function persistRevision(
        OrganizationCommunication $communication,
        int $creatorMembershipId,
        int $version,
        array $attributes,
    ): OrganizationCommunicationRevision {
        $revision = new OrganizationCommunicationRevision;
        $revision->forceFill([
            ...$this->canonicalAttributes($attributes, requireTitle: true),
            'organization_communication_id' => $communication->id,
            'created_by_organization_membership_id' => $creatorMembershipId,
            'version' => $version,
            'state' => OrganizationCommunicationRevisionState::DRAFT,
        ])->save();

        return $revision;
    }
}
