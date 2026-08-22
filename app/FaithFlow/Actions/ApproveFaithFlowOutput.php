<?php

namespace App\FaithFlow\Actions;

use App\Enums\FaithFlowOutputStatus;
use App\Models\FaithFlowOutput;
use App\Support\TenantContext;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * The human-approval + conditional Content Studio handoff seam — see
 * K-FAITHFLOW-001E §9-§11/§20-§21, corrected by K-FAITHFLOW-001E-R1 §2-§3.
 * "Approval" and "handoff" are one user intention for Content-Studio-mapped
 * output types, executed atomically: either both the status transition and
 * the ContentItem creation succeed together, or neither happens and the
 * output is left exactly as it was (GENERATED) — trivially retry-safe, and
 * avoids ever inventing an "approved but handoff pending" state (§21's own
 * smallest-state-model instruction). For FaithFlow-native reference outputs
 * (Key Themes/Key Quotes, no ContentType mapping) this only ever performs
 * the status transition — see K-FAITHFLOW-001E report §16/§25.
 *
 * No AI/provider call happens here (§20/§43) — safe to wrap in a plain
 * database transaction.
 *
 * K-FAITHFLOW-001E-R1: approval attribution is deliberately NOT accepted as
 * a caller-supplied `User` parameter — that would let any caller stamp an
 * arbitrary User as the approver merely by constructing the argument. The
 * approver is instead resolved *inside* this class from the same trusted
 * seam every other tenant-scoping decision in Keryon already goes through:
 * `TenantContext::currentMembership()`, which is itself derived only from
 * `Auth::user()`'s own active, valid ChurchMembership (see TenantContext's
 * own `resolve()`) — never from `$user->church_id`, never from
 * `memberships()->first()`. This also closes the loop on the required
 * invariant: authenticated User + active TenantContext + current active
 * ChurchMembership + an output belonging to that same Church = a trusted
 * approval actor. If the active membership's Church does not match the
 * output's Church, there is no trusted actor for this approval and the
 * call is rejected outright, before any state changes.
 *
 * Caller-side policy authorization is unchanged and still required: the
 * caller must check both `Gate::authorize('approve', $output)` (FaithFlow)
 * and, when this output maps to a ContentType, `Gate::authorize('create',
 * ContentItem::class)` (Content Studio) before invoking this — see
 * CreateContentItemFromFaithFlow's own docblock for the second half of
 * that requirement.
 */
class ApproveFaithFlowOutput
{
    public function __construct(private readonly CreateContentItemFromFaithFlow $createContentItem) {}

    public function handle(FaithFlowOutput $output): FaithFlowOutput
    {
        if ($output->status === FaithFlowOutputStatus::APPROVED) {
            // Idempotent (§16/§39) — a duplicate approve click or a retried
            // request must not re-run handoff, overwrite the original
            // approval attribution, or create a second ContentItem.
            if ($output->output_type->contentType() !== null) {
                $this->createContentItem->handle($output);
            }

            return $output;
        }

        if (! $output->canBeApproved()) {
            throw new LogicException(
                "An output with status [{$output->status->value}] cannot be approved — only a GENERATED output can."
            );
        }

        $membership = app(TenantContext::class)->currentMembership();

        if ($membership === null || $membership->church_id !== $output->church_id) {
            // No trusted approval actor for this output — either nobody is
            // authenticated with a valid active membership at all, or the
            // active membership belongs to a different Church than the one
            // that owns this output. Fail closed; do not guess an actor.
            throw new LogicException('No trusted approval identity for this output — the active Church context does not match.');
        }

        return DB::transaction(function () use ($output, $membership) {
            $output->forceFill([
                'status' => FaithFlowOutputStatus::APPROVED,
                'approved_at' => now(),
                'approved_by' => $membership->user_id,
            ])->save();

            if ($output->output_type->contentType() !== null) {
                $this->createContentItem->handle($output);
            }

            return $output->fresh();
        });
    }
}
