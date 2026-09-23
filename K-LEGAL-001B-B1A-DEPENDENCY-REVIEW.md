# K-LEGAL-001B-B1A — Dependency & Authorization Review

**Milestone:** K-LEGAL-001B-B1A — Registry Schema & Integrity Foundation
**Date:** 2026-09-24
**Revision:** 3 — Revision 2 corrected §1 of Revision 1's claim that `symfony/html-sanitizer` would be a self-contained new addition; it is in fact already an indirect dependency (via `filament/support`), and its installed 8.1.x line requires PHP `>=8.4.1`, exceeding this project's declared `^8.3`. **Revision 3 corrects Revision 2's own remaining gap:** the `php >=8.4.1` requirement is not specific to `html-sanitizer` — every already-installed `symfony/*` v8.1.x package checked (`console`, `css-selector`, `event-dispatcher`, `filesystem`, `finder`, `clock`) independently requires it. Revision 2's framing implied choosing the `html-sanitizer` 7.4.x line alone could restore PHP 8.3 compatibility; it cannot — see new §1.3. §2 and §3 are unchanged.
**Status:** Review only. **No package has been installed by this document or this milestone.** Every `composer require --dry-run` performed for this correction was non-mutating and confirmed reverted via `git diff` immediately afterward. Grounded in `origin/staging` at `ef266658a6d3772a47abc80d3aa3240085201b1b`, `K-LEGAL-001B-A-ARCHITECTURE.md` Revision 3, and `K-LEGAL-001B-A-DECISION-ADDENDUM.md`.

---

## 1. HTML sanitizer recommendation

**Correction to Revision 1 of this document:** the original claim — "no other dependency requires updating," implying `symfony/html-sanitizer` would be a clean, self-contained new addition — was made without checking the package's own complete dependency requirements. Checking them directly (via `vendor/symfony/html-sanitizer/composer.json`, Packagist's real metadata, and two non-mutating `composer require --dry-run` runs, all reverted and confirmed via `git diff` afterward) surfaced a materially different and more important fact than anything in the original recommendation.

### 1.1 `symfony/html-sanitizer` is already present — as an indirect dependency

`symfony/html-sanitizer` **v8.1.0 is already installed in `vendor/`, already in `composer.lock`'s production `packages` section, and already autoloadable** — confirmed directly: `class_exists(\Symfony\Component\HtmlSanitizer\HtmlSanitizer::class)` returns `true` in this worktree right now, with no `composer require` run. It is not a direct dependency of this project's own `composer.json` — it is pulled in transitively by `filament/support` v4.11.7, which requires `symfony/html-sanitizer: ^7.0|^8.0`.

This changes the actual decision in front of Product Office: it is not "install a new package," it is **"add an explicit direct `require` for a package already silently relied upon transitively"** — the safer engineering practice, since a future Filament upgrade that drops or narrows its own `html-sanitizer` requirement could otherwise silently remove this project's access to a class its own code has started depending on, with no direct constraint of its own protecting against that.

### 1.2 Verified requirements — 8.1 vs. 7.4, and why they differ materially

| | `symfony/html-sanitizer` **8.1.x** (already installed, v8.1.0) | `symfony/html-sanitizer` **7.4.x** |
|---|---|---|
| PHP requirement (from the package's own `composer.json`, not inferred) | `>=8.4.1` | `>=8.2` |
| `ext-dom` | Required | Required |
| `league/uri` | `^6.5\|^7.0` | `^6.5\|^7.0` |
| `masterminds/html5` | Not required | **`^2.7.2` — required** |
| `symfony/deprecation-contracts` | Not required | `^2.5\|^3` |
| License | MIT | MIT |

**Verified, not assumed:**
- `league/uri` is already locked in this project at `7.8.1`, which satisfies `^7.0` — no version bump needed for either line.
- `masterminds/html5` is **not** currently present in `composer.lock` at all — choosing the 7.4.x line means a genuinely new package, not merely a version change.
- `symfony/deprecation-contracts` is already locked at `v3.7.0`, satisfying `^2.5|^3` — already satisfied for the 7.4.x line too.
- `ext-dom` is loaded in this environment (`php -m` confirms `dom` present).

**The critical, previously-unchecked fact: this project's `composer.json` declares `"php": "^8.3"`.** The already-installed `symfony/html-sanitizer` v8.1.0 requires PHP `>=8.4.1` — a range this project's own declared PHP floor does **not** guarantee. This is a **pre-existing inconsistency already in the repository** (via Filament's own transitive requirement), not something this review's recommendation introduces — but adopting 8.1.x as an *explicit* direct dependency would make that reliance explicit rather than silent, and does not by itself resolve the inconsistency between what `composer.json` claims to support and what is actually required for the dependency tree to install.

### 1.3 Further correction: the PHP `>=8.4.1` requirement is project-wide, not specific to this package

**§1.2 understated the problem by framing it as an `html-sanitizer`-specific tradeoff. It is not.** Directly inspecting the `composer.json` of every already-installed `symfony/*` package at the `v8.1.x` line in this project (not merely `html-sanitizer`) shows the same `php: >=8.4.1` requirement, independent of anything to do with a sanitizer:

| Package (already installed, v8.1.0) | Own `composer.json` PHP requirement |
|---|---|
| `symfony/console` | `>=8.4.1` |
| `symfony/css-selector` | `>=8.4.1` |
| `symfony/event-dispatcher` | `>=8.4.1` |
| `symfony/filesystem` | `>=8.4.1` |
| `symfony/finder` | `>=8.4.1` |
| `symfony/clock` | `>=8.4.1` |
| `symfony/html-sanitizer` | `>=8.4.1` |

All seven verified directly from each package's own `vendor/symfony/<name>/composer.json`, not inferred from Packagist or from `html-sanitizer` alone.

**Consequence: the "Open question" section below (§1.5), as originally written in the prior revision, was itself incomplete and is corrected here.** Downgrading `symfony/html-sanitizer` to the 7.4.x line would **not** restore PHP 8.3 compatibility for this project — `symfony/console`, `symfony/css-selector`, and the other packages in the table above are core to Laravel's own tooling (console commands, testing, the framework's own dependency graph), are already locked at `v8.1.0` regardless of any sanitizer decision, and already require PHP `>=8.4.1` on their own. If the real deployment target is genuinely PHP 8.3.x, this project's *entire currently-locked dependency tree* — not just the sanitizer choice — already cannot install there, and resolving that is a project-wide `composer.lock` question, not something achievable by picking `html-sanitizer:^7.4` in isolation. **Choosing HTML Sanitizer 7.4 alone cannot establish PHP 8.3 compatibility for this project.**

### 1.4 Dry-run verification (non-mutating; confirmed reverted both times via `git diff`)

```txt
$ composer require "symfony/html-sanitizer:^7.4" --dry-run
  - Locking masterminds/html5 (2.11.0)
  - Downgrading symfony/html-sanitizer (v8.1.0 => v7.4.19)

$ composer require "symfony/html-sanitizer:^8.1" --dry-run
  - Upgrading symfony/html-sanitizer (v8.1.0 => v8.1.7)
```

Both resolve without conflict **in this environment**, which runs PHP 8.5.8 — satisfying both lines' PHP floors regardless of which is chosen. This is exactly the distinction that matters and that Revision 1 did not draw: **resolving successfully in this sandboxed dev environment is not evidence of compatibility with the project's declared `^8.3` range or with any specific deployment target.** Choosing 7.4.x would be a real *downgrade* from what Filament already transitively resolves to, plus a new `masterminds/html5` dependency; choosing 8.1.x keeps the already-resolved version but requires accepting (or correcting) the `^8.3`/`>=8.4.1` inconsistency above.

### 1.5 Open question this review cannot resolve: the intended deployment PHP version, and the full dependency-resolution decision

`docs/06-Engineering/Deployment_Guardrails.md` and `docs/06-Engineering/Local_Development.md` were checked directly — **neither documents a specific target PHP version** for staging/production. Per §1.3, this is no longer a question that can be answered by choosing between two `html-sanitizer` lines:

- **If the real deployment target runs PHP `>=8.4.1`:** the already-installed `symfony/*` v8.1.x family — `html-sanitizer` included — is already consistent with that, and `composer.json`'s `"php": "^8.3"` declaration should be corrected to reflect what the dependency tree actually requires.
- **If the real deployment target is genuinely PHP 8.3.x only:** this project's *entire* currently-locked `symfony/*` v8.1.x family cannot install there — `symfony/console` and `symfony/css-selector` alone already require `>=8.4.1`, with no `html-sanitizer` decision involved. Restoring genuine PHP 8.3 compatibility would require resolving the *whole* dependency tree against an older Symfony line (or an equivalent project-wide `composer update` constraint change), a materially larger undertaking than choosing a sanitizer package — and one this review is not scoped to perform or recommend a path for.

**The actual staging/production PHP version, and the full dependency-resolution decision this implies, remain outstanding — this review does not have visibility into either and does not resolve them.** No package has been installed, downgraded, or updated in the course of producing this correction.

### 1.6 `mews/purifier` alternative (unchanged from Revision 1)

`mews/purifier`/`ezyang/htmlpurifier` remains a credible alternative — longer production track record — but was not re-investigated to the same depth in this correction, since it was not the subject of the finding above. Its LGPL-2.1 licensing (vs. `symfony/html-sanitizer`'s MIT) remains a flagged consideration specific to a legal-document-serving subsystem, unchanged from Revision 1. Note that `mews/purifier`'s underlying `ezyang/htmlpurifier` was not checked for its own PHP-version requirements in this correction — if pursued as an alternative to the `symfony/*` line entirely, its requirements would need the same direct verification this document now applies to `symfony/html-sanitizer`, not assumed compatible by default.

### 1.7 Proposed `PolicyContentSanitizer` contract

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

### 1.8 Permitted elements and attributes

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

| Item | Verified fact (checkable now, not a judgment call) | Recommendation / open question (requires Product Office/Engineering approval or answer before acting) |
|---|---|---|
| Sanitizer presence | `symfony/html-sanitizer` v8.1.0 is **already installed**, already autoloadable, transitively via `filament/support` — confirmed via `class_exists()`, not merely `composer.lock` inspection | **Adding it as a direct `composer.json` dependency is not yet approved** |
| Sanitizer PHP requirement | The already-installed v8.1.0's own `composer.json` requires `php: >=8.4.1` — confirmed by reading `vendor/symfony/html-sanitizer/composer.json` directly | **This project's declared `"php": "^8.3"` does not guarantee that floor.** Pre-existing inconsistency, not introduced by this review — open for Product Office/Engineering to resolve |
| 8.1.x vs. 7.4.x tradeoff (§1.2) | 7.4.x needs PHP `>=8.2` but requires a genuinely new package (`masterminds/html5`) and downgrades the already-resolved version; 8.1.x needs nothing new but requires PHP `>=8.4.1` — both verified via non-mutating dry-runs, reverted | Choosing between them is **not decided by this review** — and, per the next row, **neither choice by itself resolves PHP-8.3 compatibility for the project** |
| PHP `>=8.4.1` is project-wide, not sanitizer-specific (§1.3) | `symfony/console`, `symfony/css-selector`, `symfony/event-dispatcher`, `symfony/filesystem`, `symfony/finder`, `symfony/clock` — every already-installed `symfony/*` v8.1.0 package checked — independently requires `php >=8.4.1`, verified directly from each package's own `composer.json`, with no sanitizer involved | **Choosing HTML Sanitizer 7.4 alone cannot establish PHP 8.3 compatibility.** If PHP 8.3 support is actually required, the entire currently-locked `symfony/*` v8.1.x family needs resolving, not just the sanitizer — a project-wide decision this review does not make |
| Deployment target PHP version | Not documented anywhere in this repository (`Deployment_Guardrails.md`, `Local_Development.md` checked directly) | **Open question Product Office/Ops must answer** — the actual staging/production PHP version and the full dependency-resolution decision it implies both remain outstanding |
| Alternative (`mews/purifier`) | Exists, is LGPL-2.1, has a longer production track record; its own PHP-version requirements were not independently verified in this correction | Rejecting it in favor of `symfony/html-sanitizer` remains this review's position, not a decided fact |
| Permitted elements/attributes (§1.8) | Architecture §5 literally names headings/paragraphs/lists/tables/bold-italic/links | Whether to add `br`, `rel`, or `target` is explicitly left open, not decided here |
| Authorization mechanism | `PlatformMembership`/`PlatformContext`/`PlatformCapability`/`PlatformRole`/`PlatformAuditEvent` exist today, unmodified, exactly as described in §2 | **Adding `PolicyGovernanceManage` to `PlatformCapability` has not been done** — recommended only |
| Publication gate (§3) | Restates Decision Addendum §3 verbatim; not a new claim | N/A — no recommendation here, purely a restatement |

No package has been installed, downgraded, or updated, and no `composer.json`/`composer.lock` change has been made by this document — every `composer require --dry-run` invocation used to produce §1.4's evidence was confirmed reverted via `git diff` immediately after each ran. No capability enum change has been made. No authorization policy class has been created. Every item in the right-hand column above is a proposal or an open question for Product Office/Engineering, not an action this review or K-LEGAL-001B-B1A has taken.
