# Keryon Engineering Deviation Log

Use this file to record any approved deviation from the Keryon blueprint family.

## Template

```md
## YYYY-MM-DD

Blueprint Version:
Changed:
Reason:
Approved By:
Implementation Notes:
```

## Entries

## 2026-07-16

Blueprint Version: v1.3
Changed: Added net-new scope not covered by any existing blueprint document — a public marketing website for `keryon.app` (distinct from Blueprint v1.3 §12 "Website Architecture," which governs the per-church tenant website feature, not Keryon's own marketing site).
Reason: `.claude/adjustment` introduced a "Keryon Public Website v1.0 / Product Office Frontend Blueprint" (status "Approved For Design Exploration") specifying homepage messaging, IA, brand personality, and a mandatory UI-tooling workflow. The file self-declared approval but was not recorded anywhere in `Blueprint_Index.md` or this log, so it was raised as a Scope Challenge before implementation.
Approved By: Product Office (confirmed in-session, 2026-07-16)
Implementation Notes: Homepage built per the blueprint's §8 Sections 01-05 and 07 (Testimonials/§06 omitted — no real churches to quote yet, and fabricated testimonials are forbidden by the Product Language Standard). Stub pages for Features/Solutions/Pricing/Resources/About (content not yet specified in the blueprint). No new packages installed, no new database tables, no lead-capture backend — "Book a Demo" is a static page with a `mailto:` CTA pending a separate approval for a real intake flow.

## 2026-08-16

Blueprint Version: v1.3 → v1.4 (new addendum)
Changed: FaithFlow moved from future-roadmap classification (Master Blueprint v1.3 §35 "Version 2 — Intelligent Communications," and the general AI exclusion lists mirrored in CLAUDE.md and `Scope_Challenge_Protocol.md`) into a narrowly-defined Keryon MVP capability, inside Communications Hub → Content Studio.
Reason: Product Office directive (K-MVP-FAITHFLOW-001) determined FaithFlow provides genuine ministry/communications value and a legitimate variable-cost dimension for commercial tiering, not merely marketing-page content. Raised as a formal Scope Challenge before this work began (FaithFlow was explicitly named in CLAUDE.md's Scope Challenge trigger list and `Scope_Challenge_Protocol.md`'s "AI Scope Drift" section, both citing "AI is future roadmap, not v1.3 implementation scope"); Product Office responded by authorizing a new tracked, versioned Blueprint addendum rather than an informal override.
Approved By: Product Office (confirmed in-session, 2026-08-16)
Implementation Notes: This is a documentation/governance-only change. Created `docs/00-Blueprint/Keryon_Blueprint_v1.4_FaithFlow_MVP_Addendum.md` defining the narrow MVP boundary (text-only input, an 8-item output catalogue, mandatory human review, no automatic publishing, explicit Care Center/congregation-data boundaries, no provider/model approval, no schema). Updated `Blueprint_Index.md`'s version table and interpretation rules, `CLAUDE.md`'s governing-documents list and Scope Challenge trigger line, and `Scope_Challenge_Protocol.md`'s "AI Scope Drift" section to reference the new addendum precisely — all other AI capability remains excluded and the general Scope Challenge mechanism is unweakened. Master Blueprint v1.3 itself was not edited. No models, migrations, controllers, AI provider integrations, pricing, or billing code were created — commercial tiering work (K-PRICE-006) remains paused pending this addendum's approval, per Product Office's own stated sequencing.

## 2026-08-17

Blueprint Version: v1.3/v1.4 → v1.4.1 (new addendum)
Changed: Recorded the target identity, church membership, and authorization architecture — moving `User` away from the implicit "one user = one church" assumption in `users.church_id` toward a first-class `ChurchMembership` relationship, a `TenantContext` tenant-resolution seam, composable church responsibility roles (Administrator/Communications/Care), a Primary Administrator ownership designation distinct from those roles, a mandatory Care Center privacy boundary, and a separate platform-staff authorization domain for a future Keryon Central.
Reason: Product Office directive `K-SURFACES-001` commissioned an architecture-discovery report (identity, membership, authorization, product surfaces). Product Office reviewed that report and issued `K-GOV-1.4.1` to convert the approved — and in places amended — decisions into durable governance before any implementation begins. The clearest amendment: Primary Administrator is recorded as an account-ownership designation, not as a fourth church responsibility role, departing from the discovery report's original recommendation.
Approved By: Product Office (confirmed in-session, 2026-08-17)
Implementation Notes: Documentation/governance-only change; no schema, model, policy, middleware, Filament, or package change was made or authorized. Created `docs/00-Blueprint/Keryon_Blueprint_v1.4.1_Product_Surfaces_Identity_Membership_Authorization_Addendum.md` (product truth: five product surfaces, User/ChurchMembership definitions, role model, Care Center privacy boundary, entitlements-vs-permissions, hostname direction, Self-Hosted freeze) and `docs/06-Engineering/Keryon_Identity_Membership_Authorization_Architecture_v1.4.1.md` (implementation constraints: TenantContext seam, fail-closed invariant, staged no-big-bang migration principle for `users.church_id`, capability architecture, package decisions, sequencing). Updated `Blueprint_Index.md`'s version table and interpretation rules, `Scope_Challenge_Protocol.md`'s parent-document list and a new "Identity / Authorization Architecture Drift" trigger section, and `CLAUDE.md`'s governing-documents list. The already-built-but-uncommitted Content Studio Filament UI (C-STUDIO-B) was inspected only, not modified — it was built against the current same-church policy and remains valid to commit as-is; only a small follow-up authorization patch is expected once `K-AUTH-001` lands. `users.church_id` remains in place, reclassified as legacy transitional architecture, not removed. Implementation (`K-IDENTITY-001` and beyond) remains a separate, not-yet-commissioned milestone.

## 2026-08-21

Blueprint Version: v1.3 §9/§12/§13, v1.4.1 §14/§15
Changed: Established the Church Website domain foundation — a shared Church Brand Profile (`church_brand_profiles`), a minimal Institutional Media primitive (`media_assets`), and the canonical Church Website Content domain (`website_settings`, `website_home_contents`, `website_about_contents`, `website_contact_contents`, `website_leadership_profiles`, `website_ministries`), plus two Church-owned institutional tables (`church_social_links`, `church_service_times`) and a new `churches.address` column. Four new `Capability` cases (`church.identity.view/manage`, `media.view/manage`), granted to `COMMUNICATIONS` only. No public rendering, no theme implementation, no publishing, and no UI beyond what proves the domain — all deliberately deferred per the directive's own scope boundary.
Reason: Product Office directive K-CHURCHWEB-001B, following the read-only K-CHURCHWEB-001A architecture audit (historically `K-WEB-001A`; see the identifier reconciliation note in `Blueprint_Index.md`), authorized implementation of the smallest durable domain foundation Website Management, Proclaim, Design Studio, and Campaigns can all safely build on, per the Theme≠Content and shared-brand/shared-media principles already approved in Blueprint v1.3 §9/§12/§13 and v1.4.1 §14.
Approved By: Product Office (confirmed in-session, 2026-08-21)
Implementation Notes: 11 migrations (see the K-CHURCHWEB-001B report §20 for the full list), 10 new Eloquent models, 10 new Policy classes (all via the existing `ResolvesTenantMembership`/`BelongsToChurch` pattern, no new authorization mechanism), 5 new bounded enums (`BrandFontChoice`, `SocialPlatform`, `DayOfWeek`, `LeadershipCategory`, `WebsiteTheme`), and a new `ValidatesMediaAssetOwnership` trait enforcing that a media reference can never cross a Church boundary. No package installed — Spatie Media Library was considered and deliberately not installed this milestone (see report §10). Institutional-ownership test applied throughout: physical address moved to `Church` (not a Website field), service times and social links modeled as Church-owned (not Website-owned) tables. Updated `Blueprint_Index.md`'s "Current Engineering Priority" (reconciled to reflect actual completed work), added a "Church Website Identifier Reconciliation" note distinguishing `K-CHURCHWEB-*` from the historical marketing-site `K-WEB-001`–`K-WEB-005` identifiers, and added "Shared Cross-Product Architecture" / "Responsibility-Aware Communications" sections recording Brand Profile and Institutional Media as explicitly not Website-owned. No public website rendering, Proclaim implementation, custom-domain work, Design Studio, or Campaigns code was created — full suite passed at 580/580 (536 pre-existing + 44 new), no regressions.

## 2026-08-22 — Campaign Domain Boundary Reconciliation

Blueprint Version: v1.3 §Module 4 / v1.4.1 responsibility architecture

Changed: Approved the minimum v1 Campaign aggregate as `Campaign` → `CampaignCommunication` → optional canonical `ContentItem`. Campaign is a Church-owned communication initiative; CampaignCommunication is planned communication intent that may exist before content. Readiness is derived from Content Studio and future publishing state rather than stored as progress. The existing `campaigns.view/manage` capability pair remains sufficient and Communications-only.

Reason: K-CAMPAIGN-001A audited Campaigns against the implemented Content Studio, FaithFlow, Website publication, Institutional Media, TenantContext, and capability architecture. Product Office accepted the audit and explicitly excluded project/task/event/fundraising/analytics scope, manual giving goals/progress, and vague campaign-performance metrics from v1.

Approved By: Product Office (confirmed in-session, 2026-08-22)

Implementation Notes: K-CAMPAIGN-001B is limited to the Campaign/CampaignCommunication domain foundation, typed status/channel vocabulary, tenant-safe actions/policies, and tests. FaithFlow, Website, Media, Calendar/Publishing Queue, Design Studio, and UI integrations remain separately sequenced. Historical Blueprint language is not rewritten; the Blueprint Index reconciliation records which boundary governs current implementation.

## 2026-08-22 — Campaign Content Orchestration Seam

Blueprint Version: v1.3 §Module 4 / v1.4 FaithFlow / v1.4.1 responsibility architecture

Changed: A CampaignCommunication may explicitly initialize canonical Content Studio creation or a FaithFlow run. The resulting ContentItem is linked back to the communication intent; Campaigns do not own copy, generation, review, or approval state.

Reason: Product Office directive K-CAMPAIGN-001D authorized the minimum cross-domain handoff needed to turn planned communication intent into canonical content while preserving Content Studio and FaithFlow ownership and authorization.

Approved By: Product Office (confirmed in-session, 2026-08-22)

Implementation Notes: Explicit tenant-validated CampaignCommunication context is carried by query state into Content Studio creation and by one nullable foreign key on FaithFlowRun. FaithFlow prompt behavior is unchanged. Approval, ContentItem creation, and Campaign linkage use the existing transactional handoff. Website, Media, Calendar/Publishing Queue, and Design Studio remain excluded.

## 2026-08-22 — Campaign Media and Website Coordination Seam

Blueprint Version: v1.3 §Module 4 / Website delivery checkpoint / v1.4.1 responsibility architecture

Changed: Campaigns may associate existing Church-owned Institutional Media through an explicit Campaign–MediaAsset association. Website-channel plan entries gain capability-gated navigation and truthful Church Website operational context, but no Website target persistence, editing, readiness claim, or publication authority.

Reason: Product Office directive K-CAMPAIGN-001E authorized the smallest cross-domain coordination layer while retaining MediaAsset and Website ownership boundaries.

Approved By: Product Office (confirmed in-session, 2026-08-22)

Implementation Notes: Campaign media associations carry only an optional Campaign label and ordering; they neither duplicate asset metadata nor create storage. The Website panel explicitly distinguishes Content Studio readiness from Website action and publication. Existing FaithFlow work may finish after a Campaign becomes Completed, but new contextual work remains limited to Draft/Planned/Active and Archived/cancelled contexts fail closed. Calendar, Publishing Queue, Website publication from Campaigns, and Design Studio remain excluded.
