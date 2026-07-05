# Keryon Development Guide

## Project

Keryon is a Church Communications Platform.

Tagline: Your Church's Digital Communications Staff Member

Project path: `C:\Users\VALPOSHIE\Herd\keryon`

Blueprint version: v1.3

Primary domain: `keryon.app`

## Stack

- Laravel 12
- Filament v4
- Livewire 3
- MySQL
- Laravel Herd
- VS Code
- PowerShell
- Claude Code
- 21st.dev / Magic MCP
- UI UX Pro Max Skill
- Git

## Product Scope

Keryon helps churches:

- Know their people
- Care for their people
- Reach their people
- Stay consistent online

Core v1 modules:

- Congregation
- Care Center
- Communications Hub
- Campaigns

Keryon is NOT a Church ERP or full Church Management System.

Do not add these in v1 unless explicitly approved:

- Attendance
- Donations
- Accounting
- Payroll
- Volunteer Management
- Events
- Sermons
- LMS
- Inventory
- Page Builder
- AI
- Social API publishing

## Multi-Tenancy

Keryon uses simple `church_id` tenancy.

Do not install a tenancy package.

Do not implement domain or subdomain tenancy in v1.

Every church-owned model should use the `BelongsToChurch` trait.

Every tenant-owned query must be scoped by `church_id`.

## Filament Direction

Use Product-first Filament.

Prefer:

- Custom pages
- Custom widgets
- Custom dashboard
- Product-style screens

Avoid:

- Generic CRUD feeling
- ERP interfaces
- WordPress admin feel
- cPanel/phpMyAdmin feel

## Design System

Primary: `#1E5631`

Accent: `#D4AF37`

Background: `#F8FAFC`

Text: `#111827`

Typography: Inter

Spacing: 8px system

Card radius: 16px

Button radius: 12px

No dark mode in v1.

Avoid gradients and excessive animations.

Empty states are required.

## Website Content Rule

Keryon is not a page builder.

Website sections are fixed:

- Hero Banner
- Pastor Welcome
- Service Times
- Ministries
- Footer

Only text, images, buttons, links, and banners should be editable.

Website publishing workflow:

- Draft
- Preview
- Publish

Never publish immediately after Save.

## Installed Skills

### UI UX Pro Max Skill

Path:

`.claude/skills/ui-ux-pro-max-skill`

Overrides:

`.claude/skills/ui-ux-pro-max-skill/KERYON_OVERRIDES.md`

Use for:

- Dashboard refinement
- Communications Hub
- Content Studio
- Campaigns
- Calendar
- Empty states
- Product-first Filament pages
- Widget hierarchy
- Spacing and layout recommendations

Do not use for:

- Migrations
- Models
- Traits
- Policies
- Authentication
- Queues
- Tests
- Database structure
- Tenancy architecture

`KERYON_OVERRIDES.md` takes precedence over the skill's general recommendations.

### 21st.dev / Magic MCP

Use for:

- UI/component assistance
- Dashboard components
- Empty states
- Cards
- Layout suggestions
- Filament UI refinement

Do not use it to introduce new features.

## Claude Working Process

Claude may directly edit files for small, reversible development steps.

Before editing files:

1. Explain which files will be inspected or changed.
2. Explain why the change is needed.

After editing files:

1. List every changed file.
2. Summarize the changes.
3. Provide exact verification commands.
4. Explain expected results.

Claude must ask before:

- Running migrations
- Installing packages
- Deleting files
- Destructive commands
- Authentication changes
- Major refactors
- Schema changes beyond the current requested task

Do not run `php artisan migrate` until the migration code has been shown and approved.

## Architecture Review

Claude should challenge decisions that:

- Violate Blueprint v1.3
- Break tenant isolation
- Add scope creep
- Increase support burden
- Introduce unnecessary complexity

Prefer simple, boring, maintainable Laravel code.

If two implementations are possible, choose the one a mid-level Laravel developer can understand six months later.

## Scope Challenge Authority

Claude is empowered to challenge, pause, or refuse execution when a request appears to violate Keryon Master Blueprint v1.3, v1.3.1 Engineering Hardening Addendum, v1.3.2 Marketplace Distribution Addendum, or Product Office directives.

Claude must not blindly execute instructions that introduce scope drift, weaken tenant isolation, add excluded modules, or create long-term maintenance risk.

Claude must raise a Scope Challenge before implementation when a request appears to add or imply:

- Church ERP functionality
- Donations, payments, giving records, receipts, accounting, or financial reporting
- Attendance, event registration, volunteer scheduling, payroll, LMS, or inventory
- Page-builder behavior
- AI/FaithFlow features in v1.3
- Native social API publishing in v1.3
- Marketplace/self-hosted packaging features before approval
- Unapproved enum values
- Unapproved schema changes
- Unapproved packages
- Weak tenant scoping
- Destructive operations

When challenging scope, Claude must explain:

1. The requested action
2. The concern
3. The relevant blueprint rule
4. The risk if implemented
5. The recommended approved path
6. The Product Office decision needed

Claude must then wait for approval before editing files.

Guiding rule:

Implement approved scope.
Challenge scope drift.
Protect the blueprint.

See `docs/06-Engineering/Scope_Challenge_Protocol.md` for the full protocol.

## Git Rules

Commit after every stable milestone.

Example commits:

```bash
git commit -m "feat: churches foundation"
git commit -m "feat: belongs to church trait"
git commit -m "feat: congregation resource"
git commit -m "feat: dashboard widgets"

## API Outage Protocol

If Claude API returns 500 or 529 errors:

1. Do not change implementation plans.
2. Product Office creates task files via PowerShell.
3. Resume work only by reading the task file.
4. Never skip approval checkpoints because of API outages.

<!-- KERYON-BLUEPRINT-GOVERNANCE:START -->
## Keryon Blueprint Governance

The Keryon blueprint family is binding for this project.

### Governing Documents

- `docs/00-Blueprint/Keryon_Master_Blueprint_v1.3.md`
- `docs/00-Blueprint/Keryon_Blueprint_v1.3.1_Engineering_Hardening_Addendum.md`
- `docs/00-Blueprint/Keryon_Blueprint_v1.3.2_Marketplace_Distribution_Addendum.md`
- `docs/00-Blueprint/Blueprint_Index.md`

### Authority

Keryon Master Blueprint v1.3 is the source of truth.

Keryon Blueprint v1.3.1 is binding for engineering hardening.

Keryon Blueprint v1.3.2 is a future marketplace/self-hosted packaging consideration only. It must not change current sprint scope.

### Product Scope Lock

Keryon is a Church Communications Platform.

Do not introduce these unless Product Office explicitly approves a versioned blueprint update:

- Donations
- Tithes
- Offerings
- Payment processing
- Payment webhooks
- Receipts
- Financial reporting
- Accounting
- Attendance
- Event registration
- Volunteer scheduling
- Church ERP functionality
- Page-builder functionality
- Marketplace installer
- Licensing system
- White-label reseller panel

### Tenancy Rules

Every tenant-owned model must:

- Include `church_id`
- Use the approved tenancy protection pattern
- Be scoped by church
- Be covered by tenant isolation tests where applicable

Simple `church_id` tenancy remains approved.

Weak tenancy is not approved.

### Congregation Status Rule

Approved `CongregationStatus` values only:

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

### Claude Working Rules

Before implementation, explain:

- Files to inspect
- Files to change
- Reason for change
- Risks
- Verification plan

After implementation, provide:

- Changed files
- Summary of changes
- Verification commands
- Expected result
- Commit message

Ask before:

- Running migrations
- Installing packages
- Deleting files
- Authentication changes
- Destructive operations
- Major architectural refactors
- Schema changes beyond the approved task

### Current Sprint

Continue Congregation implementation.

Current task:

**Congregation C-04 â€” Status Enum**

Then continue:

1. C-07 â€” Fillable Guard
2. C-05 â€” Full Name Accessor
3. C-06 â€” Church Relationship
4. C-02 â€” Delete Action
5. C-03 â€” Soft Deletes
6. C-01 â€” Member Profile Page
<!-- KERYON-BLUEPRINT-GOVERNANCE:END -->
