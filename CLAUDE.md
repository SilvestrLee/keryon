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

## Git Rules

Commit after every stable milestone.

Example commits:

```bash
git commit -m "feat: churches foundation"
git commit -m "feat: belongs to church trait"
git commit -m "feat: congregation resource"
git commit -m "feat: dashboard widgets"