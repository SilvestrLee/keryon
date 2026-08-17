# Keryon Identity, Membership & Authorization Architecture (v1.4.1)

Document Type: Engineering Architecture Standard
Parent Document: `docs/00-Blueprint/Keryon_Blueprint_v1.4.1_Product_Surfaces_Identity_Membership_Authorization_Addendum.md`
Status: Approved direction. Implementation constraints and migration strategy only — no implementation authorized by this document.

This document records the technical constraints, invariants, and staged migration principle Engineering must follow once the identity/membership/authorization work is actually commissioned. The product-facing decisions behind these constraints live in the Blueprint v1.4.1 addendum; this document does not restate the product reasoning, only what it requires of the implementation.

---

## 1. Current State

- `users.church_id` — nullable FK, couples one User to at most one Church. No membership concept, no role, no status beyond `email_verified_at`.
- `App\Filament\Pages\ChurchSetup` — the only onboarding path; writes `church_id` directly onto the authenticated user, with no invite/membership flow.
- `App\Http\Middleware\EnsureUserHasChurch` — redirects to setup when `church_id` is null. No concept of membership status.
- `App\Models\Concerns\BelongsToChurch` — global scope reads `Auth::user()?->church_id` inline, fails closed (`whereRaw('0 = 1')`) when absent. This fail-closed behavior is sound and must be preserved through any refactor.
- Single `admin` Filament panel, single `web` guard, single `users` provider (`config/auth.php`).
- Policies (`ContentItemPolicy`, `CongregationMemberPolicy`) check `$user->church_id === $record->church_id` directly — flat, no role concept.
- No RBAC package installed. No raw scope-bypass patterns found anywhere in `app/`, `routes/`, or `database/` (`withoutGlobalScope`, `DB::table`, `DB::select`, `DB::statement`, `unscoped` — all zero matches as of this review).

## 2. Target State

```txt
User
├── ChurchMembership(s)   — church access, status, responsibility role(s)
└── PlatformAccess        — separate platform-staff authorization domain (§9)

TenantContext              — resolves the active Church from the active ChurchMembership,
                              replacing direct Auth::user()->church_id reads
```

`ChurchMembership` is a first-class model, not a bare pivot. `TenantContext` is the one seam between identity and tenant scoping — introducing it is the highest-leverage, lowest-risk first step, because every other change (membership, roles, Care privacy, platform separation) can then land independently behind that seam instead of as one large rewrite.

## 3. TenantContext Seam

`BelongsToChurch`'s global scope must resolve its church ID through a bound `TenantContext` service rather than reading `Auth::user()->church_id` inline. The scope mechanism itself — auto-fill on create, fail-closed when no context — does not change; only where it gets the church ID from changes.

This is the anti-pattern being retired:

```php
// current
Auth::user()?->church_id

// target
app(TenantContext::class)->currentChurchId()
```

## 4. Fail-Closed Tenancy — Permanent Invariant

No resolvable tenant context must never mean "show everything." It must always mean "show nothing tenant-owned." Any refactor that weakens this is prohibited regardless of how it is justified. This invariant predates this document and is restated here because it is exactly what a careless `TenantContext` implementation could accidentally break.

## 5. Church Membership — Structural Requirements

- One User may hold zero, one, or many `ChurchMembership` records; one Church may have many.
- A membership carries: church, user, status, one or more responsibility roles, and standard timestamps. Exact schema is an implementation-milestone decision, not frozen here.
- Do not add a separate boolean for Primary Administrator status alongside a role field — the Blueprint addendum (§7) intentionally keeps ownership designation as a single source of truth rather than two fields that can drift out of sync. The exact safe representation is chosen at implementation time.
- Roles must be composable — a membership may hold more than one responsibility role simultaneously.

## 6. Session and Membership Revalidation

A session may remember which church is currently selected. **It may not be trusted as proof of continuing authorization.** Every relevant request must re-establish, in order:

```txt
User exists
→ membership exists
→ membership is active
→ Church exists
→ Church is active
→ TenantContext established
```

Removing or suspending a membership must block access on the **next request**, not merely after the affected session logs out and back in.

## 7. Church.is_active Enforcement

`Church.is_active` exists today but is read by nothing. Its enforcement is not an optional fast-follow — it is mandatory scope of the identity/`TenantContext` implementation milestone itself (`K-IDENTITY-001`, once commissioned). An inactive Church must never establish a valid Church Workspace tenant context.

## 8. Authorization Check Order

```txt
authenticated?
→ valid active membership?
→ church active?
→ TenantContext established?
→ required responsibility/capability present?
→ record belongs to the active church?
→ commercial entitlement satisfied, where the action is entitlement-gated?
```

This is a conceptual ordering, not a mandated function signature — Engineering may optimize the implementation as long as the security semantics (each step fails closed into the next) are preserved.

## 9. Capability Architecture

Approved model: **fixed Keryon responsibility roles, backed by a fixed, code-defined set of capabilities.** Not a role-only switch (insufficient to make the Care Center boundary independently testable), and not a full permissions framework or church-configurable roles (no current product requirement justifies that complexity).

Illustrative capability families — final naming is an implementation decision:

```txt
church.manage
staff.manage
congregation.read / congregation.manage
care.read / care.manage
content.read / content.manage
faithflow.use
website.content.manage / website.theme.manage / website.publish / website.domain.manage
billing.manage
subscription.manage
```

A membership's role(s) map to a fixed capability set via a simple, explicit, greppable match — not a database-backed permissions table.

## 10. Sequencing

Product Office has rejected parallel implementation. The governing order is:

```txt
K-GOV-1.4.1        this documentation milestone
        ↓
K-IDENTITY-001      ChurchMembership + TenantContext foundation, Church.is_active enforcement
        ↓
K-AUTH-001          church authorization conversion (policies → capabilities)
        ↓
C-STUDIO-B          Content Studio UI continuation (already built against current
                    policy — see §12; commits as-is, then receives a small follow-up
                    patch once K-AUTH-001 lands, not a rebuild)
        ↓
FaithFlow           domain + implementation
        ↓
K-DOMAIN-001        domain discovery/implementation (public-website hostname
                    resolution is independent of identity work and may proceed
                    in parallel once separately approved)
        ↓
Central foundation
        ↓
Commercial entitlements
        ↓
Final pricing implementation
```

Product Office may reorder this later; until then it is binding.

## 11. Migration Principle — No Big-Bang

`users.church_id` is legacy transitional architecture (Blueprint v1.4.1 §3). It must not be dropped in one migration. Required staging philosophy for the eventual `K-IDENTITY-001` milestone:

```txt
introduce ChurchMembership/TenantContext structure
→ backfill existing users.church_id into a corresponding membership, safely
→ run old and new resolution paths side by side where necessary
→ convert tenant resolution (BelongsToChurch) to TenantContext
→ convert authorization (policies) to capability checks
→ verify — full tenancy/membership/role test suite green
→ prove no regressions against existing Congregation/Care/Content Studio behavior
→ only then, as a separate, later, isolated migration, remove users.church_id
```

`users.church_id` removal requires evidence, not a schedule: every user has a corresponding membership, `TenantContext` is stable in production use, tenancy tests pass, authorization policies are fully converted, onboarding (`ChurchSetup`) is converted, no code path still reads `users.church_id`, and a rollback strategy is understood. This is its own later, isolated milestone.

## 12. Existing Code Impact (identified, not modified)

- `ContentItemPolicy`, `CongregationMemberPolicy` — the same-church half of each check is unaffected; only the authorization half (currently "any authenticated same-church user") converts to a capability check.
- `BelongsToChurch` — one line changes: the source of the church ID.
- `EnsureUserHasChurch` — likely adapted to check for an active membership rather than a raw `church_id`, same overall shape.
- `ChurchSetup::save()` — should create a `ChurchMembership` with the ownership designation instead of writing `church_id` directly onto the user.
- Test suite — 17 files / 62 references to `church_id` across Tenancy/Care Center/Content Studio tests will need a membership-creation helper in place of `User::factory()->create(['church_id' => ...])`. Mechanical, not risky, but real work; scope this explicitly into `K-IDENTITY-001`.
- `ContentItem` schema — no change required. Tenant resolution changing upstream does not require a schema rewrite downstream.

## 13. Package Decisions

- **No tenancy package** (`stancl/tenancy`, `spatie/laravel-multitenancy`, or equivalent). The problem being solved is identity coupling and authorization, not a demonstrated failure of shared-database tenancy. Simple `church_id`-based tenancy remains approved.
- **No RBAC package** (`spatie/laravel-permission` or equivalent). The approved target — fixed roles backed by fixed, code-defined capabilities — is intentionally small. A package may be reconsidered later only if Engineering demonstrates a material complexity reduction without weakening these constraints, brought to Product Office as its own decision.

## 14. Security Requirements

- Cross-tenant support access (§ Blueprint addendum §12) must be explicit, permissioned, target-church-scoped, and audited — never a routine `withoutGlobalScopes()` call inside ordinary church-facing code. No such calls exist in the codebase today (verified); this must remain true by construction, not convention, once Central's support pattern is built.
- Care Center capability must never be implied by Primary Administrator, Administrator, or Communications responsibility — it must be checked independently and explicitly, server-side, in every relevant policy.
- Platform-staff authorization must never share a check, table, or code path with church-membership authorization.

## 15. Test Requirements (for the eventual identity milestone)

- **Membership** — single church, multiple churches, removed membership loses access, suspended membership loses access, zero memberships, final-Primary-Administrator invariant blocks removal without a replacement.
- **Roles** — Communications cannot reach Care Center; Care cannot manage domain, billing, or staff; Administrator cannot manage billing or domain; Primary Administrator can do everything including ownership transfer.
- **Tenancy** — `TenantContext` scopes correctly; switching active church cannot leak the prior church's records; no context still fails closed; direct-URL cross-church access resolves to not-found (matching the pattern already proven for `ContentItem`).
- **Central** (once built) — a church-only user cannot reach Central; a platform-only user gains no tenant capability by default; every cross-tenant support action produces an audit row.
- **Session invalidation** — revoking a membership mid-session blocks the next request, not just the next login.

## 16. Deferred / Not Authorized by This Document

```txt
church_memberships migration
TenantContext implementation
users/model changes
policy changes
middleware changes
auth config changes
Filament panel changes (Church Workspace or Central)
RBAC or tenancy package installation
domain/DNS routing
Central panel or CMS
FaithFlow implementation
pricing/entitlement implementation
```

All of the above require their own commissioned milestone under the sequencing in §10.
