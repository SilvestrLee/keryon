# Keryon Filament Product Experience Review

## Document Type

Engineering Hardening Review

## Parent Documents

- Keryon Master Blueprint v1.3
- Keryon Blueprint v1.3.1 Engineering Hardening Addendum
- Scope Challenge Protocol
- CLAUDE.md

## Status

Approved Review Document

## Purpose

This document defines how Keryon should use Filament without allowing the product to feel like a generic admin panel.

Filament is approved as the application foundation.

However, Keryon must feel like a guided church communications platform, not a database management backend.

---

## Product Experience Principle

Keryon should feel:

```txt
Calm
Productive
Guided
Human
Ministry-aware
Clear
```

Keryon should not feel like:

```txt
WordPress Admin
cPanel
phpMyAdmin
ERPNext
Salesforce
Generic CRUD backend
Database table-first interface
```

The guiding rule:

```txt
Use Filament for speed.
Use product design for experience.
Do not let CRUD define the product.
```

---

## Current Filament Inventory

```txt
Resources:
  - CongregationResource (app/Filament/Resources/CongregationResource.php)

Pages:
  - ListCongregationMembers (CongregationResource/Pages)
  - ViewCongregationMember (CongregationResource/Pages)
  - EditCongregationMember (CongregationResource/Pages)
  - No dedicated create page — creation happens via a slide-over CreateAction
    triggered from the list page and from the empty state action.

Widgets:
  - CongregationStatsWidget (app/Filament/Widgets) — StatsOverviewWidget showing
    Total Members, Visitors, Birthdays This Month, Inactive Members.
  - AccountWidget (Filament default)
  - FilamentInfoWidget (Filament default)

Custom pages:
  - ChurchSetup (app/Filament/Pages/ChurchSetup.php) — single-step form page
    used to create the Church record and attach it to the authenticated user
    when they have no church_id yet. Not registered in navigation.

Current dashboard state:
  - Default Filament\Pages\Dashboard::class (AdminPanelProvider.php), no custom
    dashboard page. Widgets auto-discovered from app/Filament/Widgets plus the
    two Filament default widgets registered explicitly.
```

Only the above exists. No Church resource, no Care Center, no Communications Hub, no Campaigns screens exist yet.

---

## Current Screen Classification

| Screen / Resource | Current Type | Current Status | Product Experience Classification | Notes |
|---|---|---|---|---|
| Congregation list | Filament Resource table | Implemented | Needs future product refinement | Functional directory table with photo, status badges, birthday filter, and a guided empty state. Reasonable for the current stage but is still a standard resource table. |
| Congregation create | Slide-over CreateAction (no dedicated page) | Implemented | CRUD acceptable for now | Lightweight slide-over form matches the "quick add a person" task well; does not need to become a wizard. |
| Congregation view/profile | Custom infolist | Implemented | Needs future product refinement | Already grouped into sensible sections (Member Summary, Contact, Church, Record Info) rather than a raw field dump — a good foundation to build a fuller profile experience on. |
| Congregation edit | Filament Resource form | Implemented | CRUD acceptable for now | Simple field set, low cognitive load, appropriate as a form. |
| Dashboard | Default Filament\Pages\Dashboard with stats widgets | Implemented | Must become custom product experience | Currently a stats-only landing page (member counts). This is exactly the "statistics dump" pattern the blueprint says the dashboard must not become. |
| ChurchSetup | Custom Filament Page | Implemented | Needs future product refinement | Single-page onboarding form. Functionally fine for a one-field-set setup step, but as onboarding expands (team invites, service times, first content) it will need to become a guided multi-step flow rather than one long form. |
| Church resource | Not present | N/A | N/A | No Filament resource exists for Church; only the ChurchSetup page touches the model. |

---

## CRUD Acceptable Areas

Generic Filament CRUD is acceptable for low-frequency or internal management screens.

Examples:

```txt
Simple settings
Platform configuration
Super-admin management screens
Simple lookup data
Internal admin tables
Low-frequency maintenance screens
```

CRUD is acceptable when:

```txt
the user already understands the task
the workflow is simple
the screen is low-frequency
the screen does not carry the core product promise
the table/form pattern reduces complexity
```

Applied to what exists today: the Congregation create (slide-over) and edit forms qualify — they are simple, well-understood, low-risk data entry tasks.

---

## Custom Product Experience Required Areas

The following areas must not be built as generic CRUD:

```txt
Dashboard
Care Center dashboard
Prayer Request workflow
Communications Hub
Website Content workflow
Content Studio
Content Calendar
Publishing Queue
Campaigns
Onboarding flow
```

Reason:

These areas carry Keryon's core promise.

They should feel guided, intentional, and ministry-aware.

Applied to what exists today: the Dashboard is the most urgent case — it currently is a stats-only page and must be redesigned (in a future implementation sprint) into a briefing-style landing experience. ChurchSetup is the seed of the future Onboarding flow and should evolve rather than be replaced.

---

## Module-Level Experience Direction

### Dashboard

Must answer:

```txt
What needs my attention today?
```

Should include:

```txt
weekly briefing
quick actions
care alerts
upcoming birthdays
website/content reminders
campaign status
empty states
```

Must not become:

```txt
a statistics dump
a generic admin landing page
a chart-heavy analytics screen
```

Current state: default Filament dashboard with stat cards only — matches the anti-pattern above and is flagged as "Must become custom product experience."

---

### Congregation

Current resource-based CRUD is acceptable for the early foundation.

However, it should evolve toward a calmer directory experience.

Approved direction:

```txt
clear member directory
useful filters
status badges
birthday indicators
profile view
tenant-safe actions
safe soft delete
```

Avoid:

```txt
overloaded table columns
ERP-style member management
family/department/attendance expansion without approval
```

Current state: list table already includes status badges and a birthdays filter, and the profile view uses sectioned infolists. This is a solid base for the "calmer directory" direction without further module expansion.

---

### Care Center

Must become a guided care workflow.

Should feel like:

```txt
care queue
prayer request review
follow-up workspace
pastoral attention list
```

Must not feel like:

```txt
generic prayer_requests table
CRM pipeline
support ticket system
```

Current state: not started. No Care Center code exists.

---

### Communications Hub

Must become a product workspace.

Should feel like:

```txt
content operations center
planning workspace
publishing dashboard
media/content command center
```

Must not feel like:

```txt
generic post CRUD
WordPress admin clone
page builder
file manager
```

Current state: not started. No Communications Hub code exists.

---

### Website Content

Must be structured and guided.

Approved pattern:

```txt
fixed sections
editable content fields
draft
preview
publish
clear publishing status
```

Must not become:

```txt
Elementor
Webflow
custom HTML builder
drag-anywhere editor
page builder
```

Current state: not started.

---

### Media Library

Must be tenant-safe and communication-focused.

Approved direction:

```txt
organized media assets
tenant-prefixed storage
categories/collections
preview
reuse
upload validation
quota awareness
```

Must not become:

```txt
generic cloud drive
public file dump
unscoped upload manager
```

Current state: not started. Tenant media path rules were already defined in `Media_Path_Strategy.md` (T-04); no upload UI exists yet.

---

### Campaigns

Must feel like a campaign workspace.

Should include:

```txt
campaign summary
timeline
related content
website/social communication tasks
CTA
status
```

Must not become:

```txt
fundraising platform
event registration system
CRM workflow
generic campaign table only
```

Current state: not started.

---

### Content Calendar

Must be visual and planning-focused.

Should support:

```txt
day/week/month views
scheduled content
campaign dates
draft deadlines
rescheduling
```

Must not be only:

```txt
a raw scheduled_posts table
```

Current state: not started.

---

### Onboarding

Must be guided.

Should help a church:

```txt
create church profile
invite team
add first members
configure service times
prepare first website content
create first communication
```

Must not be:

```txt
a settings dump
a long generic form
```

Current state: ChurchSetup page covers only "create church profile" as a single form. The remaining onboarding steps (invite team, add first members, service times, first content) are not yet built and should be designed as a guided multi-step flow rather than additional standalone forms.

---

## Empty State Standard

Every major screen must have an empty state.

Empty states should explain:

```txt
what this screen is for
why it matters
what to do next
```

Examples:

```txt
No congregation members yet. Add your first person to begin building your church directory.
No prayer requests yet. Requests submitted through your website will appear here.
No campaigns yet. Create a campaign for Easter, Christmas, Youth Week, or a community outreach.
No scheduled content yet. Use a template to prepare your first church communication.
```

Do not display empty tables without guidance.

Current state: the Congregation list already implements this standard (`emptyStateHeading`, `emptyStateDescription`, `emptyStateIcon`, and an "Add First Member" empty-state action). Future modules must match this bar.

---

## Action Design Rules

Actions should be clear and safe.

Preferred action style:

```txt
primary action for the main next step
secondary actions for lower-priority tasks
destructive actions separated and clearly labeled
confirmation for destructive actions
no bulk destructive actions unless approved
```

Dangerous actions:

```txt
delete
force delete
bulk delete
restore
archive
publish
unpublish
```

must require careful Product Office review where appropriate.

Current state: Congregation table actions are View, Edit, Delete — no bulk actions are registered, which already matches the "no bulk destructive actions unless approved" rule.

---

## Navigation Rules

Navigation should reflect product language, not database language.

Prefer:

```txt
Congregation
Care Center
Communications
Campaigns
Settings
```

Avoid:

```txt
Congregation Members Table
Prayer Requests CRUD
Website Contents
Scheduled Posts Records
```

Future navigation should group related work around user intent.

Current state: CongregationResource already uses the navigation label "Congregation" under a "People" navigation group — consistent with product language rather than database language.

---

## Filament Implementation Guardrails

Engineering may use:

```txt
Resources
Custom Pages
Widgets
Infolists
Tables
Forms
Actions
Panels
```

Engineering should avoid:

```txt
raw CRUD-only experiences for flagship modules
overloaded table columns
too many row actions
unexplained empty states
database-first naming
duplicated UI patterns
unapproved destructive actions
```

If a feature feels like generic CRUD but belongs to a flagship workflow, Engineering should raise a product-experience review before implementation.

---

## UI Skill and MCP Usage

The UI UX Pro Max Skill and 21st.dev / Magic MCP may be used for:

```txt
layout suggestions
dashboard refinement
empty states
component hierarchy
spacing
cards
widgets
visual polish
```

They must not be used to:

```txt
introduce new product modules
expand scope
change database architecture
add page-builder behavior
add AI features
add marketplace features
override blueprint rules
```

Blueprint and Product Office decisions remain higher authority than UI tools.

---

## Required UI Tooling Check

Before implementing or polishing Filament UI, Engineering must consult the UI UX Pro Max Skill, KERYON_OVERRIDES.md, and the Product Language Standard.

21st.dev / Magic MCP may be used for UI review and layout guidance, but all suggestions must remain inside the approved sprint scope.

If a UI appears unstyled because Tailwind/Filament theme classes are not loading, this should be raised as a theme wiring issue and handled in a dedicated sprint.

---

## Product Office Decision

T-05 is complete when:

```txt
current Filament screens are inventoried
current and future screens are classified
CRUD-safe areas are identified
custom product-experience areas are identified
empty-state and action rules are documented
no UI implementation has started
```

All conditions above are satisfied by this document.

---

## Important Restrictions

Do not modify Filament source files.

Do not create pages.

Do not create widgets.

Do not change navigation.

Do not change tables.

Do not change forms.

Do not change actions.

Do not modify migrations.

Do not modify models.

Do not install packages.

Do not use Magic MCP to generate UI.

Do not begin Care Center.

Do not begin Communications Hub.

Do not begin Campaigns.

Do not clean unrelated stray files.
