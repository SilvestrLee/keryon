# Keryon Blueprint Index

This directory contains the governing product and engineering documentation for Keryon.

## Current Blueprint Family

| Version | Document | Authority |
| --- | --- | --- |
| v1.3 | `Keryon_Master_Blueprint_v1.3.md` | Source of truth for product, strategy, scope, modules, platform architecture, engineering governance, business strategy, and roadmap. |
| v1.3.1 | `Keryon_Blueprint_v1.3.1_Engineering_Hardening_Addendum.md` | Binding engineering hardening addendum for tenancy safety, media scalability, UI guardrails, Claude Code rules, and scope protection. |
| v1.3.2 | `Keryon_Blueprint_v1.3.2_Marketplace_Distribution_Addendum.md` | Future marketplace/self-hosted packaging consideration. It must not change current sprint scope. |
| v1.4 | `Keryon_Blueprint_v1.4_FaithFlow_MVP_Addendum.md` | Binding, but narrow: moves FaithFlow from future roadmap into a defined Communications Hub → Content Studio MVP capability. Does not reopen "AI" generally — all other AI capability remains future roadmap. |
| v1.4.1 | `Keryon_Blueprint_v1.4.1_Product_Surfaces_Identity_Membership_Authorization_Addendum.md` | Architecture direction for product surfaces, User identity, church membership, and authorization. Documentation only — authorizes no schema, model, policy, middleware, or Filament change. Implementation constraints live in `../06-Engineering/Keryon_Identity_Membership_Authorization_Architecture_v1.4.1.md`. |

## Interpretation Rules

1. The Master Blueprint v1.3 is the primary source of truth.
2. The v1.3.1 Engineering Hardening Addendum is binding for implementation safety.
3. The v1.3.2 Marketplace Distribution Addendum is strategic only until Product Office issues a specific marketplace preparation directive.
4. The v1.4 FaithFlow MVP Addendum is binding only within its own narrow scope boundary — it supersedes the Master Blueprint's FaithFlow/AI future-roadmap classification to that extent only, and does not authorize AI capability outside what it explicitly approves.
5. The v1.4.1 Product Surfaces, Identity, Membership & Authorization Addendum is the authoritative direction for identity/membership/authorization architecture, but is documentation only — implementation requires its own separately commissioned milestone(s), staged per the order in its companion engineering document.
6. If there is a conflict between a chat instruction and the blueprint, stop and ask Product Office for clarification.
7. Any product expansion requires a versioned Product Office decision.

## Current Product Boundary

Keryon is a Church Communications Platform.

Keryon is not:

- Church ERP
- Accounting software
- Donation processor
- Attendance tracker
- Volunteer scheduling system
- Event registration platform
- Generic website builder
- Page builder

## Current Engineering Priority

Reconciled by K-CHURCHWEB-001B (§5) — the flat priority list previously
recorded here predated the work below and no longer reflected repository
state. See K-CHURCHWEB-001A §36.3 and the Deviation Log. Historical
Blueprint decisions below are unchanged; this section only records what
has actually shipped versus what remains.

**Completed:**

- Identity, Church Membership & capability-based Authorization (Blueprint v1.4.1)
- Communications Hub → Content Studio (content domain, review/approval workflow)
- Communications Hub → FaithFlow (AI-assisted communication generation, Blueprint v1.4 MVP)
- Tenant-aware background execution foundation (K-ASYNC-001)
- Church Website — architecture audit + domain foundation: Church Brand Profile, Institutional Media, canonical Website Content domain (K-CHURCHWEB-001A, K-CHURCHWEB-001B)

**In progress / next:**

- Church Website — Management product experience (K-CHURCHWEB-001C)
- Church Website — Proclaim theme + public rendering (K-CHURCHWEB-001D)
- Church Website — Preview/publishing integration + closeout (K-CHURCHWEB-001E)

**Not yet started:**

- Care Center (beyond Prayer Requests)
- Dashboard
- Media Library — full product experience (K-CHURCHWEB-001B's media foundation is a domain primitive only, not the product)
- Templates
- Campaigns
- Design Studio — K-DESIGN-001 (Communications Design System & Rendering Architecture) → K-DESIGN-002 (Design Studio Product Experience) → K-DESIGN-003 (Creative Templates & Multi-Format Generation). Structured, curated, brand-aware, template-driven — not a general canvas/Canva-style tool.
- Content Calendar / Publishing Queue
- Settings
- Custom domains (K-DOMAIN-001)

## Church Website Identifier Reconciliation

The tenant Church Website work stream uses the `K-CHURCHWEB-*` identifier
prefix from K-CHURCHWEB-001A onward. This is distinct from the historical
`K-WEB-001` through `K-WEB-005` identifiers already present in code
comments (`routes/web.php`, `resources/views/components/site/*.blade.php`,
`tests/Feature/Website/PricingPageTest.php`) — those refer to Keryon's own
marketing website (`keryon.app`) and are unrelated to tenant church
websites. Do not rename that historical code for aesthetics; this note
exists only to prevent future ambiguity between the two identifier
families. `K-WEB-001A` (the architecture audit) is recorded here under
its reconciled identity, `K-CHURCHWEB-001A`.

## Shared Cross-Product Architecture

Two concepts introduced by K-CHURCHWEB-001B are explicitly **not**
Website-owned, and must not be duplicated by future modules:

- **Church Brand Profile** (`church_brand_profiles`) — the church's visual
  identity (logo, mark, colors, typography preference). Canonical owner:
  the Church domain. Consumers: Church Website now; Design Studio and
  Campaigns later, without duplicating their own copies of the same data.
- **Institutional Media** (`media_assets`) — tenant-owned files. Canonical
  owner: the Church domain (a future Media Library will manage this same
  table's product experience, not replace it). Consumers: Church Website
  now; Design Studio (as a producer) and Campaigns later.

Also introduced, and likewise not Website-owned: `church_social_links`
and `church_service_times` — institutional facts about the church that
would remain true even if Website did not exist.

## Responsibility-Aware Communications

Church Website operates inside the `COMMUNICATIONS` responsibility,
alongside Content Studio and FaithFlow (Keryon Blueprint v1.4.1 §9). Care
has no Website/Brand/Media access by default; Administrator does not
automatically inherit Communications capabilities merely by being
Administrator; Primary remains an account-governance designation, not a
ministry responsibility. See `Capability::ChurchIdentityView/Manage`,
`MediaView/Manage`, and the pre-existing `WebsiteContentView/Manage`,
`WebsiteThemeManage`, `WebsitePublish` in `app/Enums/Capability.php`.

## Campaign Architecture Reconciliation

Product Office directive K-CAMPAIGN-001A/001B narrows the v1 Campaign
domain to communication orchestration:

- `Campaign` is a Church-owned communication initiative, not a project,
  task, event, fundraising, marketing-automation, or analytics system.
- `CampaignCommunication` is the planned communication intent and may
  exist before canonical content does.
- Content Studio remains authoritative for communication content and its
  workflow; a CampaignCommunication may optionally reference one
  same-Church ContentItem, and one ContentItem may satisfy plan entries
  across multiple Campaigns.
- Campaign readiness is derived from authoritative linked state. No
  progress percentage is stored.
- Manual giving goals/progress and vague campaign-performance analytics
  are excluded from the v1 Campaign domain; v1 insight is operational
  readiness only.
- Website publishing, Institutional Media, FaithFlow, Calendar/Publishing
  Queue, and future Design Studio retain their own ownership boundaries.
- K-CAMPAIGN-001D adds only an explicit orchestration seam:
  `CampaignCommunication` may initialize Content Studio or FaithFlow, and
  the resulting canonical `ContentItem` links back to the plan entry.
  Content Studio still owns content/workflow; FaithFlow still owns
  generation/review; Campaigns still own only communication intent.
- K-CAMPAIGN-001E associates existing Institutional Media without changing
  MediaAsset ownership or storage. Website-channel plan entries navigate to
  canonical Website Management and may show overall Website operational state,
  but they do not edit, publish, or claim Campaign-specific Website readiness.
- In-flight FaithFlow work legitimately started while a Campaign was actionable
  may finish after the Campaign becomes Completed. New contextual work remains
  limited to Draft/Planned/Active; Archived or cancelled context fails closed.

The historical Master Blueprint remains the record of its time; this
reconciliation governs K-CAMPAIGN-001 implementation where its narrower
boundary differs from older campaign-project/giving-performance language.

## Current Congregation Sprint

The immediate active task remains:

**Congregation C-04 â€” Status Enum**

Approved statuses:

- `active`
- `visitor`
- `inactive`

Do not add:

- `archived`
- `deleted`
- `pending`
- `prospect`
- `lead`
- `member`

Record lifecycle must be handled separately through soft deletes or another Product Office-approved lifecycle mechanism.

## Documentation Rule

When a blueprint decision changes, update the appropriate versioned document and record the change in the deviation log where applicable.

## Related Engineering Governance

- `../06-Engineering/Scope_Challenge_Protocol.md` — operational protocol requiring Claude to challenge scope drift, blueprint conflicts, and unsafe implementation requests.
- `../06-Engineering/Website_Content_Contract.md` — the required component-specification format for every editable Website field, applies to the canonical Website Content domain established by K-CHURCHWEB-001B.
- `../06-Engineering/Media_Path_Strategy.md` — the tenant-prefixed storage path pattern `MediaAsset` follows.
