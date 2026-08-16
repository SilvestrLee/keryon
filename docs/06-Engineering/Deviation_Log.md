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
