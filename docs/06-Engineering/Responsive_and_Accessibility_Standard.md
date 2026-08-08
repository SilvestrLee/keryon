# Keryon Responsive and Accessibility Standard

## Document Type

Engineering Hardening Standard

## Parent Documents

- Keryon Master Blueprint v1.3
- Keryon Blueprint v1.3.1 Engineering Hardening Addendum
- Filament Product Experience Review
- CLAUDE.md

## Status

Approved Standard

## Purpose

A screen is not complete because it looks correct on the developer's monitor. This document sets the minimum behavioral verification expected for important user-facing Keryon interfaces — admin panel and public website alike.

This is a behavioral checklist, not a fixed set of pixel breakpoints. Where the Design System (CLAUDE.md) already defines concrete tokens (spacing, radius, etc.), those tokens govern; this document does not introduce new ones.

---

## Responsive Verification

Check important interfaces at these representative viewport categories:

```txt
small mobile
large mobile
tablet
standard desktop
wide desktop
```

At each, verify:

```txt
navigation
tables
forms
cards
page headings
modals
long church names
long person names
long prayer-request text
empty states
validation errors
action controls
filters
drawers
content previews
```

Long-content checks are not optional. A long church name or a long prayer request is a normal, expected input for Keryon — not an edge case. If a screen only looks right with a short placeholder name, it is not verified.

---

## Mobile Navigation

Mobile navigation is a designed interaction, not a collapsed desktop sidebar. Where mobile navigation exists, explicitly verify:

```txt
open behaviour
close behaviour
overlay behaviour
active item state
nested navigation behaviour
scroll behaviour
touch target size
keyboard access
focus handling
```

No navigation item may become inaccessible simply because the viewport changed.

---

## Accessibility Baseline

Treat this as product quality, not final polish:

```txt
[ ] Adequate colour contrast
[ ] Visible keyboard focus
[ ] Semantic labels on fields
[ ] Validation errors associated with their field
[ ] Keyboard-accessible controls
[ ] Meaningful button names (not just icons)
[ ] Accessible icon-only actions (labelled for assistive tech)
[ ] Appropriate semantic HTML
[ ] Modal focus behaviour (focus trapped, returned on close)
[ ] Usable touch targets
[ ] Status never communicated by colour alone
[ ] Reduced-motion consideration wherever animation is used
```

Do not rely solely on color to communicate prayer-request or campaign status — pair color with a label or icon, per the state values defined in `UX_Feedback_and_System_State_Standard.md`.

---

## When to Apply

This standard applies to admin-panel Filament work (per the Filament Product Experience Review's curated-experience areas) and to public website work (per the Website Content Contract) alike. It is part of the mandatory UI tooling review, not a separate optional pass — see CLAUDE.md's "Required UI Tooling Report."

## Product Office Decision

Approved as the standing pre-ship review for responsive behaviour and accessibility on any user-facing Keryon interface.
