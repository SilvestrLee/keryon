# Keryon Blueprint v1.4.1

## Product Surfaces, Identity, Membership & Authorization Addendum

Document Type: Blueprint Addendum
Parent Document: Keryon Master Blueprint v1.3
Previous Addendum: Keryon Blueprint v1.4 — FaithFlow MVP Addendum
Status: Approved — architecture direction, documentation only. No implementation authorized by this document.
Purpose: Establish the authoritative product surfaces, identity model, church membership concept, and authorization strategy Keryon is moving toward, replacing the implicit "one user equals one church" assumption baked into the current schema.
Scope: Identity, membership, and authorization architecture only. Does not change any product pillar, does not add ERP/finance/attendance/event scope, and does not authorize any schema, model, policy, or Filament change.
Blueprint Impact: Clarifies and extends Master Blueprint v1.3's tenancy and authorization principles ahead of implementation. Does not supersede any v1.3 exclusion. Does not renumber or rewrite v1.3, v1.3.1, v1.3.2, or v1.4.

---

# 1. Addendum Purpose

Keryon's current implementation ties a user to a church with a single column: `users.church_id`. This was a reasonable MVP shortcut, but it cannot represent platform staff, multiple staff roles, Care Center privacy separation, or a person who serves more than one church.

This addendum records the target identity, membership, and authorization architecture Product Office has approved, ahead of building it. It follows a dedicated architecture discovery milestone (`K-SURFACES-001`) whose findings Product Office reviewed and amended before adoption — this document is the amended, authoritative version, not a copy of that discovery report.

This addendum is documentation only. It authorizes no migration, no model, no policy, no middleware, no Filament panel, and no package installation. The implementation sequence it depends on is recorded separately in `docs/06-Engineering/Keryon_Identity_Membership_Authorization_Architecture_v1.4.1.md`.

---

# 2. Product Surfaces

Keryon has five conceptual product surfaces. Two exist today; the rest are directional.

## 2.1 Keryon Public Website — exists

`keryon.app`. Platform-owned marketing, product education, themes, pricing, and resources. Not a tenant surface.

## 2.2 Church Workspace — exists

The authenticated, tenant-facing product churches use day to day: Congregation, Care Center, Communications Hub, Campaigns, Website, Settings. This is the current `admin` Filament panel. Approved architectural hostname direction: `app.keryon.app` (see §12).

## 2.3 Keryon Central — future, not approved for implementation

Internal Keryon platform operations: churches, subscriptions, trials, billing, support, domains, platform health, platform CMS. Not a church tenant workspace. Approved architectural hostname direction: `central.keryon.app`. Building Central itself requires a separate, dedicated Product Office directive.

## 2.4 Church Public Websites — future, paused pending K-DOMAIN-001

Public visitor-facing church sites: `{church}.keryon.app` and, where supported, a custom church domain. No Keryon user authentication required to view them. Hostname resolves to church/website context independently of authenticated identity.

## 2.5 Congregant Portal — not MVP

Not part of Keryon's MVP. Not to be designed or implemented. Keryon is a staff communications tool, not a congregation-facing community/social product.

---

# 3. User Identity

**A User represents an authenticated human identity — nothing more.**

A User is not inherently a church, a church role, a tenant, a congregation member, or a platform employee. Church access is a relationship a user holds, not a property a user has.

`users.church_id` is reclassified as **legacy transitional architecture**. It remains in place and functional until a staged migration milestone safely supersedes it (see the engineering architecture document, §7). It must not be removed as part of this addendum or any unrelated change.

---

# 4. Church Membership

A first-class relationship — **Church Membership** — represents "this User has access to this Church." It is not a bare pivot table; it is a real concept with a status and one or more responsibility roles.

Target shape:

```txt
one User   → zero, one, or many Church Memberships
one Church → many Church Memberships
```

This makes multi-church support a property of the data model from the start (§5), even though the MVP interface does not need to expose it yet.

---

# 5. Multi-Church Readiness

The data model must support a person holding active memberships in more than one church. The product does not need a church-switcher interface for this at MVP — most churches will never have staff who also serve elsewhere in Keryon.

If and when a real user needs to switch active church context, that is a small UI addition on top of an already-capable data model, not a schema change. Do not build switcher UI speculatively.

---

# 6. Membership Status and Access

A Church Membership carries a status describing whether it currently grants access — conceptually: invited, active, suspended, removed. Exact naming is an implementation decision.

**Governing rule: access to a church requires an active membership.** A suspended or removed membership must stop access on its own, independent of the underlying User account's status and independent of the Church's own status. Membership access must be re-established on every relevant request, not cached as a standing assumption for the life of a session (see engineering document §6 for enforcement detail).

---

# 7. Primary Administrator

**Primary Administrator is an account-governance designation, not a ministry responsibility and not an authorization role.** It represents ownership: subscription and billing authority, ownership transfer, highest-risk account recovery, and domain ownership.

It is not encoded as one of the Church responsibility roles in §8 — that was the one point where Product Office amended the preceding architecture discovery's recommendation. The exact schema representation (a membership attribute, a designation flag, or another safe shape) is an implementation decision for the identity milestone, not frozen here.

**Invariant: every active church has exactly one active Primary Administrator at all times.** The product must eventually prevent removing, suspending, or replacing the last Primary Administrator without a valid replacement already in place. Ownership transfer is a deliberate, atomic action. This must be enforced in code and covered by tests — a UI-only safeguard is not sufficient.

Holding the Primary Administrator designation does **not** automatically grant Care Center access (§10).

---

# 8. Church Responsibility Roles

Approved MVP responsibility roles:

```txt
Administrator
Communications
Care
```

`Contributor` is explicitly excluded from MVP — Content Studio's self-approval model (§11) gives it no real boundary to enforce yet.

Do not add real-world church titles (Pastor, Senior Pastor, Media Lead, Secretary, Prayer Coordinator, Church Manager, ...) as authorization roles. **Keryon authorizes by responsibility, not by job title.**

**Roles are composable.** One Church Membership may hold more than one responsibility role — a solo pastor at a small church plausibly holds all three; a larger church's staff may each hold just one. Keryon must model what a person is authorized to do, not force every person into a single artificial title.

Churches may not define their own custom roles or a permissions editor in MVP. Keryon owns the role definitions. This may be revisited only with validated customer demand and a fresh Product Office review.

---

# 9. Responsibility Boundaries

**Administrator** — church settings, staff administration, congregation management, general operational administration. Does not automatically include Care access or Communications access merely because the title sounds senior.

**Communications** — Content Studio, FaithFlow, Website content and theme management, publishing, and read access to Congregation (to know who they're communicating with). Explicitly excludes Care Center unless the same membership separately holds the Care role.

**Care** — Care Center, care notes, prayer request workflow, follow-up, and read access to Congregation. Explicitly excludes Content Studio, FaithFlow, Website management, domain configuration, and billing by default.

Congregation access itself is split conceptually into read and manage — Communications and Care may reasonably need to see who they serve without being able to edit membership records; editing remains an Administrator/Primary Administrator responsibility.

---

# 10. Care Center Privacy Boundary — Mandatory

**Care access is an explicit responsibility. It is never an inherited consequence of being senior, administrative, technical, financial, or communications staff.**

Primary Administrator, Administrator, and Communications must not automatically receive Care Center access. A membership must hold the Care responsibility specifically to see Care Center records. This boundary must eventually be enforced server-side, in policy, and in tests — never as a UI-only restriction. This is a hard requirement, not a preference.

---

# 11. Content Studio and FaithFlow

The existing Content Studio workflow (Draft → In Review → Approved, with Needs Changes → Draft where applicable) remains unchanged. Self-approval remains acceptable for MVP — this addendum does not introduce a reviewer/generator split, and does not reopen `Contributor` as a role to justify one.

FaithFlow remains positioned inside Communications Hub → Content Studio (per Blueprint v1.4), not as a new pillar. Care responsibility alone does not grant FaithFlow access; both Content Studio and FaithFlow authorize through the Communications responsibility (or Administrator/Primary Administrator).

Both remain paused for implementation until the identity/authorization foundation exists — see the engineering document's sequencing (§10).

---

# 12. Platform Staff and Keryon Central

Platform staff (Keryon's own team) authenticate through the same User identity as church staff, but hold a **completely separate authorization domain** — never a bare flag on the User record, and never interchangeable with church membership. A platform staffer's access to Keryon Central grants nothing inside any church's workspace by default, and a church membership grants nothing inside Central.

Initial Central roles, once Central is built: Super Admin and Support. Finance, Marketing, or other Central-specific roles are not defined until real Central workflows require them.

Cross-tenant support access (a platform staffer needing to see one church's data to help them) must be an explicit, permissioned, target-church-scoped, and audited action — never a routine ability to browse all tenants, and never a raw scope bypass scattered through ordinary code paths.

Building Keryon Central itself is not authorized by this addendum.

---

# 13. Permissions vs. Commercial Entitlements

This is now a permanent architectural distinction:

```txt
PERMISSION  — is this person authorized to attempt this action?
ENTITLEMENT — has this church's subscription purchased this feature or capacity?
```

Both may need to be satisfied for a single action, and neither may substitute for the other. Church responsibility roles must never vary by subscription tier or region — there is no "Growth-tier Administrator" or "Premium Communications." What a role can attempt stays constant; what a plan has paid for is checked separately.

A staff seat is consumed by an **active Church Membership**, not by a User identity — one person with active memberships in two churches occupies one seat in each. Seat limits are entitlement architecture, not authorization architecture, and are not defined by this addendum.

---

# 14. CMS Boundaries

Three content systems remain distinct and must not be collapsed into one shared table merely because each stores text:

- **Platform CMS** — Keryon's own public website content. Platform-owned.
- **Church Website Content** — tenant-owned, structured, theme-aware content powering a church's public site.
- **Content Studio** — tenant-owned communication preparation, not itself a public-facing CMS.

Keryon's website philosophy is unchanged and reaffirmed here: **theme is not content**, experiences are fixed, content is flexible. No page-builder behavior, no arbitrary layout system, no custom HTML/CSS by churches.

---

# 15. Hostname Direction

Approved architectural direction, not yet implemented:

```txt
keryon.app            → Keryon Public Website
app.keryon.app         → Church Workspace (authenticated)
central.keryon.app     → Keryon Central
{church}.keryon.app    → Church public website
```

Custom church domains remain supported product direction. Hostname resolution for public church websites is unrelated to authenticated User identity — a visitor never logs in to view a public site. No DNS or routing work is authorized by this addendum; it resumes under `K-DOMAIN-001`.

---

# 16. Self-Hosted Disposition

**Active Self-Hosted engineering is frozen until Keryon Cloud reaches Product Office-defined validation criteria.**

This is a sequencing decision layered over Blueprint v1.3.2, which remains in place unedited as a preserved strategic option. Keryon Cloud is the priority product; Self-Hosted must not influence Cloud MVP architecture or draw engineering capacity while Cloud is still reaching that validation bar.

---

# 17. Related Documents

- `docs/06-Engineering/Keryon_Identity_Membership_Authorization_Architecture_v1.4.1.md` — implementation constraints, invariants, and the staged migration principle for the architecture described above.
- `docs/06-Engineering/Deviation_Log.md` — records this addendum's approval.
- `docs/00-Blueprint/Blueprint_Index.md` — version table and interpretation rules.
