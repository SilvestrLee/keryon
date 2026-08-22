<?php

namespace App\FaithFlow\Actions;

use App\Campaigns\CampaignCommunicationContext;
use App\Campaigns\CampaignCommunicationManager;
use App\Enums\ContentOrigin;
use App\Models\ContentItem;
use App\Models\FaithFlowOutput;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;

/**
 * The explicit ContentItem-creation seam — see K-FAITHFLOW-001E §13. This is
 * the *only* place a FaithFlow output becomes a Content Studio ContentItem;
 * nothing else (a Filament callback, an Eloquent observer) is allowed to
 * construct one on FaithFlow's behalf. Normally invoked once, from inside
 * ApproveFaithFlowOutput's own transaction, but safe and fully self-
 * contained to call directly too (idempotent on an already-linked output).
 *
 * Authorization is deliberately not performed here — the caller's
 * responsibility, mirroring every other FaithFlow Action class's
 * established precedent. Per K-FAITHFLOW-001E §27, the caller must
 * independently confirm the acting membership is permitted to use
 * FaithFlow *and* permitted to create a ContentItem (Capability::
 * ContentManage, via ContentItemPolicy) before invoking this — this class
 * does not, and cannot, verify that on its own.
 */
class CreateContentItemFromFaithFlow
{
    public function handle(FaithFlowOutput $output): ContentItem
    {
        if ($output->content_item_id !== null) {
            // Idempotency safety net (§16/§39) — even outside
            // ApproveFaithFlowOutput's own already-APPROVED short circuit.
            // withTrashed(): a soft-deleted handoff target is still the
            // authoritative prior handoff — see §40, no silent second
            // ContentItem is ever created for the same output.
            $contentItem = ContentItem::withTrashed()->findOrFail($output->content_item_id);
            $this->linkCampaignCommunication($output, $contentItem);

            return $contentItem;
        }

        $contentType = $output->output_type->contentType();

        if ($contentType === null) {
            throw new LogicException(
                "Output type [{$output->output_type->value}] is FaithFlow-native and has no Content Studio handoff."
            );
        }

        $body = trim((string) $output->content);

        if ($body === '') {
            throw new LogicException('This output has no usable content to hand off to Content Studio.');
        }

        // §28: the output's owning Church must match the active tenant
        // context exactly — never trust a caller-supplied Church ID, and
        // never hand off across a Church boundary even internally.
        $churchId = app(TenantContext::class)->currentChurchId();

        if ($churchId === null || $churchId !== $output->church_id) {
            throw new LogicException('The active Church context does not match this output — handoff denied.');
        }

        return DB::transaction(function () use ($output, $contentType, $body) {
            $contentItem = new ContentItem([
                'title' => $this->deriveTitle($output),
                'content_type' => $contentType,
                'body' => $body,
            ]);
            // Not mass-assignable (system-controlled, §30/K-CONTENT-002
            // §11) — set explicitly before save() so ContentItem's own
            // `origin ??= ContentOrigin::HUMAN` creating-hook sees it
            // already populated and leaves it alone. `status` is left
            // untouched: the same hook's `status ??= ContentStatus::DRAFT`
            // default is exactly the initial status this handoff wants
            // (see K-FAITHFLOW-001E report §14).
            $contentItem->origin = ContentOrigin::FAITHFLOW;
            $contentItem->save();

            // The provenance link (§17): ContentItem <- content_item_id <-
            // FaithFlowOutput -> faithflow_run_id -> FaithFlowRun already
            // answers "which output/run produced this ContentItem" without
            // any new column or polymorphic provenance table.
            $output->forceFill(['content_item_id' => $contentItem->id])->save();

            $this->linkCampaignCommunication($output, $contentItem);

            return $contentItem;
        });
    }

    private function linkCampaignCommunication(FaithFlowOutput $output, ContentItem $contentItem): void
    {
        $communicationId = $output->run?->campaign_communication_id;

        if ($communicationId === null) {
            return;
        }

        $communication = app(CampaignCommunicationContext::class)
            ->forFaithFlowHandoff($communicationId);

        if ($communication->church_id !== $output->church_id || $contentItem->church_id !== $output->church_id) {
            throw new LogicException('FaithFlow Campaign handoff must remain within the active Church.');
        }

        app(CampaignCommunicationManager::class)->linkContentItem($communication, $contentItem);
    }

    /**
     * Deterministic, non-AI title strategy — see K-FAITHFLOW-001E §18/§32.
     * Prefers the run's canonical `ministry_context` (already a short,
     * human-authored-feeling phrase such as "Sunday sermon") Title-Cased
     * and paired with the output type's label — e.g. "Sunday Sermon —
     * Devotional". Falls back to the first few words of `source_summary`
     * when `ministry_context` is blank, and finally to a plain "FaithFlow"
     * label if the run's canonical analysis is somehow unavailable. Never
     * exposes an internal ID; never calls a provider.
     */
    private function deriveTitle(FaithFlowOutput $output): string
    {
        $analysis = $output->run?->canonical_analysis ?? [];

        $topic = $this->cleanTopic($analysis['ministry_context'] ?? null)
            ?? $this->cleanTopic($this->firstWords($analysis['source_summary'] ?? null, 8))
            ?? 'FaithFlow';

        return "{$topic} — {$output->output_type->label()}";
    }

    private function cleanTopic(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : Str::of($value)->title()->toString();
    }

    private function firstWords(?string $value, int $count): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $words = preg_split('/\s+/', $value) ?: [];

        return implode(' ', array_slice($words, 0, $count));
    }
}
