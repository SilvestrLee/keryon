# K-LEGAL-001B-B1A — Dependency & Authorization Review

**Milestone:** K-LEGAL-001B-B1A — Registry Schema & Integrity Foundation
**Date:** 2026-09-24
**Status:** Review only. **No package has been installed by this document or this milestone.** Grounded in `origin/staging` at `ef266658a6d3772a47abc80d3aa3240085201b1b`, `K-LEGAL-001B-A-ARCHITECTURE.md` Revision 3, and `K-LEGAL-001B-A-DECISION-ADDENDUM.md`.

---

## 1. HTML sanitizer recommendation

### 1.1 Recommendation: `symfony/html-sanitizer`

| Fact | Value | Status |
|---|---|---|
| Package identity, license, minimum PHP | `symfony/html-sanitizer`, MIT, PHP 8.1+ (project requires `^8.3` — satisfied) | **Verified** — public package facts, not dependent on any decision |
| Maintainer / support cycle | Symfony core team, same cycle as every other `symfony/*` package | **Verified** |
| `symfony/*` already present in this project | `composer.lock` contains `symfony/console`, `symfony/event-dispatcher`, `symfony/filesystem`, `symfony/finder`, `symfony/css-selector`, `symfony/clock`, and others at the `v8.1.x` line, transitively via `laravel/framework: ^13.8` | **Verified** — confirmed by reading `composer.lock` directly in this worktree, not assumed |
| Design (allowlist-based, safe-by-default) | Nothing permitted unless explicitly allowed via `HtmlSanitizerConfig`; `<script>`, `on*`, `javascript:`/`data:`, `<iframe>`/`<object>`/`<embed>`, `<style>` stripped by default | **Verified** — documented library behavior |
| **Choosing this package over `mews/purifier`, and installing it at all** | — | **Recommendation — requires Product Office/Engineering approval before `composer require` runs.** Nothing above being verified makes the choice itself pre-approved. |

The row split above is deliberate: everything about *what the package is* is a checkable fact, independent of anyone's judgment. *Whether to adopt it* is a decision this document proposes but does not make.

**Why this over the more commonly recommended `mews/purifier`:** this project's `composer.lock` already pulls in `symfony/*` components (console, event-dispatcher, filesystem, finder, css-selector, clock, and others) at the `v8.1.x` line, transitively via `laravel/framework: ^13.8`. Adding `symfony/html-sanitizer` at a version from that same family introduces no new dependency ecosystem and no version-conflict risk — it slots into a tree that already exists. `mews/purifier` would instead pull in `ezyang/htmlpurifier`, an entirely separate, older library with **LGPL-2.1** licensing — worth flagging explicitly given this specific subsystem exists to serve legal documents: introducing a copyleft-family license into exactly the code path responsible for rendering the Terms of Use is the kind of detail that invites avoidable legal-review friction. `symfony/html-sanitizer`'s MIT license carries no such concern.

`mews/purifier`/`ezyang/htmlpurifier` remains a credible alternative on pure technical merit — it has a longer production track record — but is not the primary recommendation here for the licensing reason above, on top of the dependency-tree argument.

**Composer changes required (not performed by this document):** `composer require symfony/html-sanitizer` (dev-time: no corresponding `--dev` package needed; a `PolicyContentSanitizerTest` would use the same package). No other dependency requires updating — the existing `symfony/*` v8.1.x lock entries already satisfy `symfony/html-sanitizer`'s own internal `symfony/*` constraints at that version line.

### 1.2 Proposed `PolicyContentSanitizer` contract

```php
namespace App\Trust\Legal;

interface PolicyContentSanitizer
{
    /**
     * Returns the sanitized HTML — safe to store as PolicyVersion::content
     * and safe to output verbatim at render time with no further
     * transformation (Architecture §5: sanitize once, before hashing, never
     * at render time).
     */
    public function sanitize(string $html): string;
}
```

A single method, matching the shape `PolicyVersion::previewSanitizedContent()` (Architecture §4.2) already expects to call.

### 1.3 Permitted elements and attributes

| Category | Elements | Notes |
|---|---|---|
| Headings | `h1`–`h6` | |
| Paragraphs | `p` | |
| Lists | `ul`, `ol`, `li` | |
| Tables | `table`, `thead`, `tbody`, `tr`, `th`, `td` | |
| Emphasis | `strong`, `b`, `em`, `i` | |
| Links | `a` | `href` attribute only, restricted to `http://`/`https://` schemes — `javascript:`, `data:`, and all other schemes rejected |

This is the literal set Architecture §5 names — nothing broader. `br` (line breaks inside paragraphs) is a plausible, common addition for real legal-document formatting but is **not** included in this recommendation; it is named here as an open question for Product Office/counsel to decide when real content is authored, not assumed by this review. The same applies to whether `a` should carry `rel="noopener"` (forced, not caller-supplied) or a `target` attribute at all — left open.

Everything else — `<script>`, `<iframe>`, `<object>`, `<embed>`, `<style>`, all `on*` attributes, `class`/`id`/inline `style` attributes, and any element/attribute not explicitly listed above — is rejected by default under this library's allowlist model; no explicit deny-list is needed for them.

---

## 2. Platform-operator authorization — reuse the existing mechanism

**An existing, complete platform-level (not Church-tenant) authorization system already covers this need. No new privileged role is proposed.**

Inspected: `app/Models/PlatformMembership.php`, `app/Support/PlatformContext.php`, `app/Enums/PlatformCapability.php`, `app/Enums/PlatformRole.php`, `app/Models/PlatformAuditEvent.php`.

- `PlatformMembership` (role + status + capabilities) is the platform-level analogue of `ChurchMembership` — a `User` can hold an active `PlatformMembership` with one of five roles (`ADMINISTRATOR`, `OPERATIONS`, `SUPPORT`, `COMMERCIAL`, `TRUST_SECURITY`), each mapped to a fixed `PlatformCapability` set via `PlatformRole::capabilities()`.
- `PlatformContext::currentMembership()` / `hasCapability(PlatformCapability $capability)` is the existing, already-used resolution point (the exact analogue of `TenantContext` for the platform plane) — this is precisely the "existing platform capability or authorization mechanism" the assignment asks to identify.
- `TRUST_SECURITY` already holds `PlatformCapability::TrustView`/`TrustManage`, and is the role this whole document series has repeatedly referenced as the intended "platform_operator" concept (K-TRUST-002).
- `PlatformAuditEvent` is an existing, independent precedent for exactly the append-only, immutable-guard pattern `PolicyGovernanceEvent` already implements (`updating()`/`deleting()` both throw unconditionally) — further evidence this codebase already has a mature pattern for platform-governance evidence that the K-LEGAL series' design has been converging on independently.

### 2.1 Recommendation

Add one new, narrowly-scoped capability value to the existing `PlatformCapability` enum:

```php
case PolicyGovernanceManage = 'platform.policy_governance.manage';
```

Assign it to `TRUST_SECURITY` (and, automatically, `ADMINISTRATOR`, which already receives `PlatformCapability::cases()` in full) — not to `OPERATIONS`, `SUPPORT`, or `COMMERCIAL`, whose existing capability sets are unrelated domains (church provisioning, billing, support tooling). This is an **extension of the existing enum**, not a new role or a new authorization system, and is a smaller, more consistent change than inventing a standalone "policy operator" concept.

The future `approve()`/`publish()`/`retire()` methods' verified authorization context (Decision Addendum §4.4's `PlatformOperatorAuthorization`) should be constructed from `PlatformContext::currentMembership()` after confirming `hasCapability(PlatformCapability::PolicyGovernanceManage)`, with its `reference()` derived from the membership record — e.g. `"platform-membership:{$membership->id}"` — consistent with the "bounded interim `platform_operator` reference" language already used throughout this series. This value object, its exact construction, and the policy class that gates access to it are **not built by this milestone** — named here as the concrete target for the future lifecycle-methods milestone, per the assignment's restriction on inventing new mechanisms this review should instead identify.

---

## 3. Publication-evidence acknowledgement check

Restated precisely from `K-LEGAL-001B-A-DECISION-ADDENDUM.md` §3, as the exact checklist a future milestone's `publish()` gate (or its surrounding operational process) must confirm before any **non-synthetic** `PolicyVersion` becomes publicly readable or acceptance-eligible:

1. **Provisioning:** a `GovernanceEventDeliveryChannel` implementation exists that calls a genuinely external destination — outside the primary application database's own write scope. (Today: only `UnavailableGovernanceEventDeliveryChannel`, which always returns `null`, is bound in any environment — Architecture/Addendum §3.)
2. **Verified reachability and durability:** that destination has been confirmed reachable, and its own retention/backup posture confirmed by Product Office/Ops — a fact this codebase cannot self-certify.
3. **At least one proven delivery:** the relay (`PolicyGovernanceEventRelay`, K-LEGAL-001B-B0) has successfully delivered and received a confirmed acknowledgement for at least one real `PolicyGovernanceEvent` against that real destination — proving the full pipeline works end-to-end, not merely that tests pass against `FakeGovernanceEventDeliveryChannel`.

All three must hold simultaneously. Until they do, `GovernanceEventDeliveryChannel` remains bound to `UnavailableGovernanceEventDeliveryChannel`, and — separately, once `approve()`/`publish()` exist — the transition methods' own required governance-log write (Architecture §6.2) would fail closed rather than silently proceed, consistent with the fail-closed posture this entire series has maintained. This is a **precondition for a future milestone's `publish()` call**, not something K-LEGAL-001B-B1A implements or needs to check, since no `publish()` method exists yet.

---

## 4. Summary — verified facts vs. open recommendations

| Item | Verified fact (checkable now, not a judgment call) | Recommendation (requires Product Office/Engineering approval before acting) |
|---|---|---|
| Sanitizer | `symfony/html-sanitizer` is MIT-licensed, requires PHP 8.1+, and `symfony/*` v8.1.x already exists in this project's `composer.lock` | **Adopting it, and running `composer require`, is not yet approved** |
| Alternative | `mews/purifier`/`ezyang/htmlpurifier` exists, is LGPL-2.1, has a longer production track record | Rejecting it in favor of the above is this review's position, not a decided fact |
| Permitted elements/attributes (§1.3) | Architecture §5 literally names headings/paragraphs/lists/tables/bold-italic/links | Whether to add `br`, `rel`, or `target` is explicitly left open, not decided here |
| Authorization mechanism | `PlatformMembership`/`PlatformContext`/`PlatformCapability`/`PlatformRole`/`PlatformAuditEvent` exist today, unmodified, exactly as described in §2 | **Adding `PolicyGovernanceManage` to `PlatformCapability` has not been done** — recommended only |
| Publication gate (§3) | Restates Decision Addendum §3 verbatim; not a new claim | N/A — no recommendation here, purely a restatement |

No package has been installed. No capability enum change has been made. No authorization policy class has been created. Every item in the right-hand column above is a proposal for a future milestone, not an action this review or K-LEGAL-001B-B1A has taken.
