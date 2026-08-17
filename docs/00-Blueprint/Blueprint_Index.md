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

Continue current v1.3 implementation in this order:

1. Congregation
2. Care Center
3. Dashboard
4. Communications Hub
5. Website Content
6. Media
7. Templates
8. Campaigns
9. Content Studio
10. Calendar
11. Settings

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
