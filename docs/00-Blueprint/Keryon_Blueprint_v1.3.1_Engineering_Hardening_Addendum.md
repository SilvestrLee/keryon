# Keryon Blueprint v1.3.1

## Engineering Hardening Addendum

Document Type: Blueprint Addendum
Parent Document: Keryon Master Blueprint v1.3
Status: Approved
Purpose: Engineering hardening, tenant safety, media scalability, and product-experience protection
Scope: No new product modules introduced
Blueprint Impact: Strengthens v1.3 without changing the product positioning

# 1. Addendum Purpose

Keryon Master Blueprint v1.3 establishes the core product direction, modules, architecture, business strategy, and roadmap.

This v1.3.1 addendum exists to harden the implementation before the product becomes too large.

The purpose is to address early architectural risks while preserving the original product philosophy:

Keep the product simple.
Make the engineering stricter.
Add guardrails, not bloat.

This addendum does not authorize scope expansion.

It does not introduce donations, accounting, attendance, event management, page building, or ERP functionality.

# 2. Summary of Approved Changes

The following changes are officially accepted into the Keryon blueprint family.

## Accepted for v1.3.1

Tenant security audit tests

Cross-church data isolation tests

Production S3-compatible media storage requirement

Tenant-prefixed media upload paths

Storage quota enforcement rules

Filament product-experience guardrails

Claude Code operating guardrail reinforcement

Clarification of member status versus record lifecycle

## Deferred to v1.4

Curated section stacking

Website section show/hide controls

Homepage block reordering

Ministry block reordering

## Rejected for v1.3.1

Adding archived to CongregationStatus

Giving/payment webhooks

Payment provider event ingestion

Tenancy package adoption before proven need

Any page-builder-style website editing

Any financial transaction storage

# 3. Tenancy Hardening

## 3.1 Existing Blueprint Position

Keryon v1.3 uses simple church_id tenancy.

This remains the approved architecture.

Keryon will not adopt a dedicated tenancy package in v1.3.1.

## 3.2 Identified Risk

Manual church_id tenancy introduces a security risk if a tenant-owned model, query, policy, or custom page forgets to enforce church isolation.

A single unscoped query could expose one church's data to another church.

This is unacceptable.

## 3.3 Product Office Decision

Keryon will continue using simple church_id tenancy, but tenant protection must be enforced through tests and engineering guardrails.

The system must fail loudly during development if a tenant-owned model is missing tenant protection.

## 3.4 New Engineering Rule

Every tenant-owned model must:

Include a church_id column.

Use the BelongsToChurch trait.

Apply tenant scoping.

Auto-assign church_id when appropriate.

Be covered by tenant isolation tests.

Be covered by authorization policy tests where applicable.

## 3.5 Tenant-Owned Models

The following records are tenant-owned:

Congregation Members

Prayer Requests

Website Content

Media Assets

Templates

Campaigns

Scheduled Content

Notifications

Activity Logs

Church Settings

Future Content Studio records

Future Calendar records

Every future tenant-owned model must be added to this list or explicitly exempted.

## 3.6 Required Tests

Engineering must add the following test categories.

### Tenant Scope Audit Test

Purpose:

Verify that every tenant-owned model implements required tenant protection.

Expected failure:

If a model is marked tenant-owned but does not use tenant scoping, the test must fail.

### Cross-Church Visibility Test

Purpose:

Verify that Church A cannot view Church B records.

Applies to:

Tables

Forms

View pages

Edit pages

Widgets

Custom queries

Filament resources

### Tenant Assignment Test

Purpose:

Verify that newly created records automatically receive the authenticated user's church_id.

### Policy Enforcement Test

Purpose:

Verify that access control is enforced beyond the UI.

The UI hiding an action is not enough.

The backend must reject unauthorized access.

## 3.7 Approved Position on Tenancy Packages

Packages such as stancl/tenancy or spatie/laravel-multitenancy are not approved for v1.3.1.

They may be reconsidered if:

Keryon grows beyond simple single-database tenancy.

Tenant isolation requirements become more complex.

Domain/subdomain tenancy becomes necessary.

Enterprise customers require stricter isolation.

The custom tenancy layer becomes harder to maintain than a package-based approach.

Until then:

Simple tenancy remains approved.
Weak tenancy is not approved.

# 4. Media Storage Hardening

## 4.1 Existing Blueprint Position

Keryon uses Spatie Media Library for media handling.

This remains approved.

## 4.2 Identified Risk

Local storage is acceptable for local development but unsuitable for production SaaS.

Risks include:

Poor horizontal scalability

Difficult backups

Storage quota enforcement issues

Risk of media path collision

Harder tenant separation

Deployment fragility

## 4.3 Product Office Decision

Production media storage must use S3-compatible object storage.

Approved options include:

AWS S3

DigitalOcean Spaces

Cloudflare R2

Wasabi

Other S3-compatible providers approved later

## 4.4 Required Storage Path Structure

All tenant media must use a tenant-isolated prefix.

Approved path pattern:

tenants/{church_id}/media/{media_id}/

Example:

tenants/42/media/938/banner.jpg

This ensures media files are logically separated by church.

## 4.5 Storage Rules

Production media uploads must:

Be assigned to a church.

Use tenant-prefixed paths.

Respect storage quotas.

Avoid shared public root folders.

Avoid unscoped media access.

Support future migration to another object storage provider.

## 4.6 Quota Enforcement

Storage quota enforcement must occur before upload completion.

The system should prevent a church from exceeding its allocated storage limit.

Quota checks should consider:

Current used storage

Incoming file size

Church plan limits

File type restrictions

Maximum file size limits

## 4.7 Local Development Exception

Local storage is allowed only for development.

Local development may use:

storage/app/public

But production must not depend on local server disk for persistent media storage.

# 5. Congregation Status Clarification

## 5.1 Existing Blueprint Position

The approved CongregationStatus enum remains:

active

visitor

inactive

This remains frozen.

## 5.2 Identified Risk

The critique suggested adding archived to the status enum to support future soft deletes.

Product Office rejects this approach.

## 5.3 Product Office Decision

CongregationStatus describes a person's ministry relationship to the church.

It must not be used to describe record lifecycle state.

## 5.4 Correct Separation

### Relationship Status

Use CongregationStatus:

active

visitor

inactive

### Record Lifecycle

Use separate lifecycle mechanisms:

deleted_at

archived_at

is_archived

Preferred future approach:

deleted_at

through Laravel Soft Deletes, once C-03 is approved.

## 5.5 Engineering Rule

Do not add lifecycle states to CongregationStatus.

Do not add:

archived

deleted

pending

prospect

lead

member

to the enum.

If record removal is needed, use soft deletes or a dedicated lifecycle field approved by Product Office.

# 6. Website Management Hardening

## 6.1 Existing Blueprint Position

Keryon is not a page builder.

Website content uses fixed, structured sections.

## 6.2 Identified Product Risk

A completely rigid website structure may frustrate churches when they want minor changes to page ordering or visibility.

However, excessive flexibility would turn Keryon into a page builder, which violates the blueprint.

## 6.3 Product Office Decision

For v1.3.1, no change is made to the website editor.

The following concept is deferred to v1.4:

Curated Section Stacking

## 6.4 Future v1.4 Direction

Curated Section Stacking may allow churches to:

Reorder approved homepage sections.

Hide approved sections.

Show approved sections.

Reorder ministry cards.

Reorder leadership profiles.

## 6.5 Permanent Restrictions

Keryon must never allow:

Custom HTML blocks

Free-form layout building

Dragging text boxes anywhere

Custom CSS by church users

Arbitrary containers

User-created layout sections

Elementor/Webflow-style editing

# 7. Giving Campaign Boundary Reinforcement

## 7.1 Existing Blueprint Position

Keryon does not process donations.

This remains unchanged.

## 7.2 Product Office Decision

Giving/payment webhooks are rejected for v1.3.1.

Keryon must not receive, process, verify, store, or reconcile financial event data.

## 7.3 Allowed Giving Campaign Fields

A communication-only Giving Campaign may include:

Campaign title

Description

External giving link

Display goal amount

Manual progress percentage

Suggested communication assets

## 7.4 Not Allowed

Keryon v1.3.1 must not include:

Payment processing

Donation recording

Receipts

Donation analytics

Payment provider integrations

Payment webhooks

Transaction logs

Giving reconciliation

Donor statements

Financial reports

## 7.5 Future Consideration

External giving progress sync may be reconsidered in v3 only as a separate Product Office-approved initiative.

It must not enter v1.3 or v1.3.1 through the Campaigns module.

# 8. Filament Product Experience Guardrails

## 8.1 Identified Risk

Filament provides excellent admin tooling, but default CRUD screens can make Keryon feel generic, technical, or administrative.

Keryon must feel like a focused product, not a backend admin panel.

## 8.2 Product Office Decision

Filament Resources are approved, but not every workflow should feel like generic CRUD.

## 8.3 Custom Product Experiences Required

The following areas should use curated product experiences:

Dashboard

Communications Hub

Website Content workflow

Content Studio

Campaigns

Content Calendar

Publishing Queue

Care Center dashboard

Onboarding flow

## 8.4 Generic CRUD Acceptable For

Generic Filament-style CRUD is acceptable for:

Internal admin tables

Low-frequency settings

Platform configuration

Simple lookup data

Super-admin management screens

## 8.5 UI Standard

Keryon screens should feel:

Calm

Productive

Guided

Human

Ministry-aware

Clear

They should not feel:

ERP-like

cPanel-like

WordPress-admin-like

Database-table-first

Visually noisy

# 9. Claude Code Guardrail Reinforcement

## 9.1 Existing Position

Keryon uses Claude Code as a senior engineering assistant.

The root CLAUDE.md file governs Claude's behavior.

## 9.2 Required Reinforcement

The following instructions must remain active in CLAUDE.md.

Claude must not:

Create migrations without approval.

Run migrations without approval.

Install packages without approval.

Delete files without approval.

Introduce giving/payment logic.

Add unapproved product modules.

Expand member statuses.

Add page-builder behavior.

Bypass tenant scoping.

Create cross-tenant queries without explicit justification.

Replace the product blueprint with AI-generated assumptions.

## 9.3 Claude Must Always Provide

Before implementation:

Files to inspect

Files to change

Reason for change

Risks

Verification plan

After implementation:

Changed files

Summary of changes

Verification commands

Expected result

Commit message

# 10. Required Engineering Checklist

Before a v1.3 module is considered complete, Engineering must confirm:

[ ] All tenant-owned models include church_id.

[ ] All tenant-owned models use BelongsToChurch.

[ ] All relevant policies are in place.

[ ] Cross-church access tests pass.

[ ] Factory tenant assignment tests pass.

[ ] Filament resources are tenant-scoped.

[ ] Dashboard widgets are tenant-scoped.

[ ] Custom queries are tenant-scoped.

[ ] Media uses tenant-aware paths.

[ ] Upload quota rules are enforced.

[ ] No payment or giving logic was introduced.

[ ] No page-builder behavior was introduced.

[ ] No unapproved enum values were introduced.

[ ] Empty states are present where applicable.

[ ] Product Office scope remains intact.

# 11. Engineering Directive

Engineering should treat this addendum as binding.

The next engineering milestone should include the following hardening work:

## Immediate Engineering Tasks

### T-01 â€” Tenant Model Audit Test

Create a test that ensures all tenant-owned models use the required tenancy protections.

### T-02 â€” Cross-Church Access Tests

Verify that one church cannot access another church's records through:

Queries

Filament lists

Edit pages

View pages

Widgets

### T-03 â€” Media Storage Configuration Review

Prepare production-ready configuration for S3-compatible storage.

Do not implement uploads blindly.

Present plan first.

### T-04 â€” Media Path Strategy

Define tenant-prefixed media path strategy:

tenants/{church_id}/media/{media_id}/

### T-05 â€” Filament Product Experience Review

Identify screens that can remain CRUD and screens that require custom product layouts.

# 12. Versioning Decision

This addendum creates:

Keryon Blueprint v1.3.1

It does not create:

Keryon Blueprint v1.4

Reason:

The accepted changes are engineering hardening, not product expansion.

v1.4 is reserved for product behavior changes such as curated section stacking.

# 13. Product Office Declaration

Keryon Blueprint v1.3.1 is approved as an engineering hardening addendum.

It preserves the original v1.3 product direction while strengthening the implementation against tenant leakage, media scalability issues, UI drift, and accidental scope creep.

Final governing principle:

Keryon should remain simple for churches, strict for engineers, and focused for the business.
