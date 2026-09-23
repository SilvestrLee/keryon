# K-LEGAL-001B-A — Decision Addendum

**Milestone:** K-LEGAL-001B-B0 — Governance Evidence Foundation
**Date:** 2026-09-23
**Status:** Records two binding corrections Product Office attached to its conditional acceptance of `K-LEGAL-001B-A-ARCHITECTURE.md` Revision 3: transactional governance-event delivery, and complete lifecycle-transition enforcement. This document is the decision record; `K-LEGAL-001B-B0` (this milestone) implements the isolated governance-event foundation those decisions require. `PolicyVersion` itself, and the lifecycle methods this addendum specifies a corrected contract for, remain unbuilt — a separately authorized future milestone.

---

## 1. The transactional-outbox decision, and why an external log call cannot commit atomically with MySQL

Revision 3 of the architecture required every `approve()`/`publish()`/`retire()` transition to write a governance-log entry as a required, blocking part of the transition (§6.2), but did not specify *how* that write could be made reliable. It cannot be reliable if the log write is a direct call to an external system — a categorically different resource with its own independent success/failure semantics that MySQL's transaction manager has no visibility into or control over. No two-phase-commit coordinator spans "a MySQL transaction" and "an arbitrary external HTTP call" in this architecture, and introducing one (XA transactions) would be exactly the kind of heavyweight, operationally novel infrastructure this whole document series has consistently avoided.

Concretely, either ordering of "commit the database transaction" and "call the external log" as two separate steps fails in one of two ways:

- **Log before commit:** if the surrounding database transaction then fails or rolls back for any reason — including one unrelated to the log call itself, such as a deadlock retry or a constraint violation elsewhere in the same transaction — external evidence now exists for a transition that never actually happened. A phantom event, tied to nothing real.
- **Log after commit:** if that external call fails (network fault, external service down, timeout), the transition is real and committed, but has **no independent evidence at all** — precisely the silent gap this whole requirement exists to close.

**Decision: the transactional-outbox pattern.** Instead of calling the external destination directly, the fact "this transition happened" is written as a plain row in a new local database table — `policy_governance_events` — in the **same** database, as part of the **same** local transaction as the business change. Because it is the same database, this commit is genuinely atomic: either both the business change and the event row commit together, or neither does. A separate, asynchronous **relay** process then attempts delivery of that already-durable row to the real external destination, retrying indefinitely and idempotently until it succeeds. The relay's success or failure has no bearing on whether the fact of the transition is durably recorded — that was already guaranteed the moment the local transaction committed. Until the relay succeeds, the row honestly shows `pending`, which is the truth, rather than a fabricated `delivered`.

This is why K-LEGAL-001B-B0 exists as its own, prior milestone: the outbox mechanism can be built and its atomicity/idempotency guarantees proven **completely in isolation**, before the not-yet-built `PolicyVersion` lifecycle ever depends on it.

---

## 2. Scope boundary: single-table design preserved; this is not a second document store

`policy_versions` (still unbuilt) remains the single table for policy **document** storage — Product Office's prior instruction to keep that design single-table is unaffected by this addendum. `policy_governance_events`, built in this milestone, is authorized as a **separate table because it serves a categorically different purpose**: durable, append-only delivery evidence for lifecycle transitions, not a second place to store or version document content. It never contains a document body — only a SHA-256 snapshot, per the explicit restriction in this milestone's brief. The two tables are not alternatives to each other; `policy_governance_events` exists *underneath* whatever `policy_versions` eventually becomes, exactly as `policy_versions` itself sits underneath the existing, unmodified `church_activations`/`church_staff_invitations` acceptance evidence.

**"Policy identity" on an event row is the natural key `(document_type, version)`, not a foreign key.** `PolicyVersion` does not exist yet — this milestone is deliberately isolated from it — and even once it does, an application-enforced natural key keeps the same referencing philosophy already established for the acceptance tables (Architecture §7/§6.1 of Revision 3), rather than introducing a forward-referencing FK dependency this milestone would otherwise be blocked on.

---

## 3. The publication-evidence acknowledgement gate

Building the outbox mechanism is not the same as having independent evidence. **Independent tamper evidence for `policy_versions` remains unavailable** — this addendum does not change that fact, and this milestone does not claim otherwise. The gate before any real (non-synthetic) `publish()` may ever be called in a real environment is now precise:

1. A genuinely external destination — outside the primary application database's own write scope — has been provisioned.
2. That destination has been verified reachable and durable (its own retention/backup posture is a fact Product Office/Ops must confirm, not something this codebase can self-certify).
3. The relay has **successfully delivered and received acknowledgement for at least one event** against that real destination — proving the pipeline works end-to-end, not merely that the code compiles and passes tests against a fake channel.

Until all three hold, `GovernanceEventDeliveryChannel` binds to `UnavailableGovernanceEventDeliveryChannel` (§2 of the code summary below) — every event this milestone's foundation can currently produce stays `pending`, honestly, indefinitely. This is the concrete, checkable form of "explicitly mark independent tamper evidence as unavailable rather than claiming the requirement is met."

---

## 4. Corrected lifecycle-transition contract

Applies to the future milestone that builds `PolicyVersion` and its `approve()`/`publish()`/`retire()` methods (Architecture Revision 3 §4.2). Four corrections, binding on that future work:

### 4.1 Complete mandatory fields before approval

`title` and `effective_at` must both be non-blank/non-null before a draft can be approved — Revision 3 left `effective_at` nullable indefinitely and never stated a precondition tying it to approval. It is not coherent to approve a policy with no stated title or no stated legal effective date. `approve()`'s guard must check this alongside the existing non-blank-evidence-reference requirement (Architecture §4.2).

### 4.2 `title` and `effective_at` are immutable after approval

Revision 3's protected-field set (Architecture §4.4) omitted `title` and `effective_at` entirely — meaning a caller could have silently retitled or re-dated an already-approved, already-published policy at any time, which is exactly the kind of silent history-rewrite the immutability design exists to prevent. **Corrected:** `title` and `effective_at` join `content`, `sha256`, `document_type`, `audience`, `version`, and `is_synthetic_fixture` in the protected field set, locked from the moment a row leaves `draft`, enforced by the same transition-shape guard described in Architecture §4.4.

### 4.3 Verified approval hashes (unchanged, restated)

`approve()` continues to require an `$expectedSha256` argument, sourced from counsel's own sign-off record, and re-derives the sanitized content fresh to compare against it before proceeding (Architecture §4.2/§4.3). This addendum does not change that mechanism — it is restated here because it is now one clause of a larger, explicit contract rather than a standalone method signature.

### 4.4 Explicit, authorized transition context — not a bare string

Revision 3's `approve(string $approvedByReference, ...)` accepted **any** string for the actor reference, with nothing verifying it names a real, currently-authorized platform operator. **Corrected:** the future contract requires an explicit, verified authorization context object — not a raw string a caller could supply unchecked — e.g. a `PlatformOperatorAuthorization` value object produced only by whatever authorization/capability check gates access to these methods in the first place (consistent with the existing bounded `platform_operator` reference pattern, K-TRUST-002). The transition methods derive `actor_reference` from that verified context, never accept it as an independently-supplied free-text argument.

### 4.5 Illustrative corrected signature (not built by this milestone)

```php
// Future milestone, not this one:
public function approve(
    PlatformOperatorAuthorization $authorizedBy,   // verified context, not a bare string — §4.4
    string $approvalEvidenceReference,
    string $expectedSha256,
): void {
    if ($this->status !== PolicyVersionStatus::Draft->value) { throw ...; }
    if (blank($this->title) || $this->effective_at === null) { throw ...; }      // §4.1
    if (blank($approvalEvidenceReference)) { throw ...; }
    $sanitized = $this->previewSanitizedContent();
    $actualSha256 = hash('sha256', $sanitized);
    if (! hash_equals($expectedSha256, $actualSha256)) { throw ...; }            // §4.3

    $this->content = $sanitized;
    $this->sha256 = $actualSha256;
    $this->forceFill([
        'status' => PolicyVersionStatus::Approved->value,
        'approved_by_reference' => $authorizedBy->reference(),
        'approval_evidence_reference' => $approvalEvidenceReference,
        'approved_at' => now(),
    ])->save();

    PolicyGovernanceEvent::record($this->document_type, $this->version, PolicyGovernanceTransitionType::APPROVED, $this->sha256, $authorizedBy->reference());
    // both writes above must occur inside the same DB::transaction() — §1
}
```

`title`/`effective_at` are now part of the protected field set (§4.2), so this same guard mechanism blocks any later attempt to change them, exactly as it already blocks `content`/`sha256` changes today.

---

## 5. What this addendum changes vs. Architecture Revision 3

| Item | Revision 3 | This addendum |
|---|---|---|
| Governance-log delivery | A required write, mechanism unspecified | Transactional outbox: local `policy_governance_events` row, same transaction as the business change; async, idempotent, indefinitely-retried relay to the real destination |
| `title`/`effective_at` after approval | Mutable (not in the protected field set) | Immutable — added to the protected field set |
| Approval preconditions | Non-blank evidence references only | Also requires non-blank `title` and non-null `effective_at` |
| `approvedByReference` | A bare, unverified string | A verified authorization-context object |
| "Independent evidence" claim | Stated as a requirement to satisfy | Explicitly gated (§3): unavailable until a real destination is provisioned, verified, and proven via at least one real acknowledged delivery |

---

## 6. Code summary (this milestone's actual output — see closeout message for the full file list and test results)

- `database/migrations/2026_09_23_190000_create_policy_governance_events_table.php`
- `app/Models/PolicyGovernanceEvent.php` — every row must be *created* pending, with zero attempts and no delivery timestamps/acknowledgement (rejecting a directly-constructed "already delivered" row, not only relying on the `record()` factory); identity/content fields are immutable once created; once `delivered`, the entire delivery history — attempts and timestamps included, not only the terminal fields — is frozen; `delivery_status` may only move `pending → delivered`, and only together with both acknowledgement fields in the same write; deletion is permanently blocked.
- `app/Trust/Legal/GovernanceEventDeliveryChannel.php` (interface), `UnavailableGovernanceEventDeliveryChannel.php` (honest default — always leaves events pending), `FakeGovernanceEventDeliveryChannel.php` (test double, allowlisted to `local`/`testing` only — checked both at the container binding and independently in the class's own constructor, never merely "not production").
- `app/Trust/Legal/PolicyGovernanceEventRelay.php` — **does not hold a database row lock across the external delivery call.** Delivery is three separately-committed steps: (1) claim — lock the row, confirm it is still pending, record the attempt, commit, release the lock; (2) deliver — call the channel with no lock held at all; (3) record — lock the row again, confirm it is still pending, apply the outcome, commit. Each locked section is bounded by ordinary local database write latency only, never by external I/O latency. A `null`, empty, or whitespace-only acknowledgement from the channel is treated identically — none confirm delivery — and, like an unexpected exception from the channel, leaves the event pending without ever reaching (or needing to survive) the model's own validation guard.
- `config/policy-governance.php` — driver selection, defaulting (with no env var set) to the unavailable channel.

**Not built by this milestone:** `PolicyVersion`, `policy_versions`, any of the four acceptance flows' wiring, the sanitizer, or any real delivery-channel implementation. Nothing in this milestone enables actual policy approval, publication, retirement, or acceptance.
