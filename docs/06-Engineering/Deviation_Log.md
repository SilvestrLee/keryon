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
