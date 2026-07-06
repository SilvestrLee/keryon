# Keryon Project Notes

Keryon is a Church Communications Platform.

The blueprint family lives in:

- `docs/00-Blueprint/Blueprint_Index.md`
- `docs/00-Blueprint/Keryon_Master_Blueprint_v1.3.md`
- `docs/00-Blueprint/Keryon_Blueprint_v1.3.1_Engineering_Hardening_Addendum.md`
- `docs/00-Blueprint/Keryon_Blueprint_v1.3.2_Marketplace_Distribution_Addendum.md`

Current sprint:

- Congregation C-04 â€” Status Enum

Approved congregation statuses:

- `active`
- `visitor`
- `inactive`

Marketplace/self-hosted packaging is a future consideration only. Do not build marketplace features during the current sprint.

## Scope Challenge Authority

Claude must challenge any instruction that appears to conflict with the approved Keryon blueprint or addenda.

Current governing documents:

- Keryon Master Blueprint v1.3
- Keryon Blueprint v1.3.1 Engineering Hardening Addendum
- Keryon Blueprint v1.3.2 Marketplace Distribution Addendum
- CLAUDE.md
- docs/06-Engineering/Scope_Challenge_Protocol.md

Claude should stop and request Product Office clarification before implementing out-of-scope features, risky schema changes, tenant isolation changes, payment/giving logic, page-builder behavior, premature marketplace packaging, or unapproved modules.

## UI/Product Experience Tooling

All UI/product-experience work must consult:

- UI UX Pro Max Skill
- `.claude/skills/ui-ux-pro-max-skill/KERYON_OVERRIDES.md`
- `docs/06-Engineering/Product_Language_Standard.md`
- `docs/06-Engineering/Filament_Product_Experience_Review.md`
- 21st.dev / Magic MCP where useful and available

For UI sprints, final reports must state whether each tool was used or deliberately not used.

21st.dev / Magic MCP may guide layout, hierarchy, spacing, empty states, and Filament polish, but it must not expand scope or override the Blueprint.

Theme wiring, Tailwind/Vite changes, and AdminPanelProvider theme registration require a dedicated approved sprint.
