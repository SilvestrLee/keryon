# Keryon Blueprint v1.3.2

## Marketplace Distribution & Self-Hosted Edition Addendum

Document Type: Commercial Packaging Addendum
Parent Document: Keryon Master Blueprint v1.3
Previous Addendum: Keryon Blueprint v1.3.1 â€” Engineering Hardening Addendum
Status: Approved for strategic consideration
Purpose: Ensure Keryon is built in a way that can later support marketplace distribution without weakening the SaaS business
Scope: Commercial packaging, licensing awareness, deployment readiness, documentation planning
Blueprint Impact: Does not change v1.3 product modules

# 1. Addendum Purpose

Keryon is primarily a SaaS-first church communications platform.

However, Product Office has approved a strategic consideration to make a packaged self-hosted edition available through marketplaces such as:

CodeCanyon

Codester

CodeGrape

Gumroad

Lemon Squeezy

Keryonâ€™s own website

This addendum ensures that the product is built with future marketplace readiness in mind.

The goal is not to turn Keryon into a cheap script.

The goal is to create an additional distribution channel while protecting the long-term value of Keryon Cloud.

# 2. Strategic Position

## 2.1 Primary Business

The primary business remains:

Keryon Cloud â€” hosted SaaS for churches.

This version provides:

Managed hosting

Automatic updates

Security maintenance

Backups

Support

Premium roadmap access

Future AI and integrations

Recurring subscription revenue

Keryon Cloud is the main company product.

## 2.2 Secondary Business

The secondary business may become:

Keryon Self-Hosted â€” packaged Laravel edition for churches, agencies, and developers.

This version may be sold to:

Church website agencies

Laravel developers

Freelancers

Church media consultants

Independent churches that prefer self-hosting

Software resellers, where licensing permits

The self-hosted edition is a distribution channel, not the core company identity.

# 3. Product Office Decision

Keryon may support a marketplace edition in the future.

However:

The SaaS product must be built first.
The self-hosted edition must be packaged later.
Marketplace distribution must not compromise Keryon Cloud.

# 4. Edition Strategy

Keryon should eventually have clear editions.

## 4.1 Keryon Cloud

Type: Hosted SaaS
Audience: Churches
Revenue: Monthly/annual subscription
Primary Use Case: Churches want the product managed for them.

Includes:

Multi-tenant hosted platform

Automatic updates

Managed storage

Support

Future integrations

Future FaithFlow / Keryon AI

Future social publishing

Future website sync

Premium templates

Billing/subscription management

## 4.2 Keryon Self-Hosted

Type: Marketplace / downloadable package
Audience: Developers, agencies, self-hosting churches
Revenue: One-time license or controlled package sale
Primary Use Case: Buyer installs Keryon on their own hosting.

Should include:

Single installation

Church setup

Congregation

Care Center

Website Content

Media Library

Campaigns

Basic dashboard

Basic settings

Demo data

Installation guide

Update guide

## 4.3 Keryon Agency Edition

Type: Future premium self-hosted or direct-sale package
Audience: Agencies managing multiple church clients
Revenue: Higher-priced license
Primary Use Case: Agencies want to deploy and manage Keryon for multiple churches.

May include:

Multi-church management

Agency branding options

Client management features

Extended documentation

Priority support

Deployment playbook

This edition is not part of v1.3.

# 5. Marketplace Product Boundary

## 5.1 Marketplace Edition Should Include

The self-hosted marketplace version may include:

Congregation module

Care Center module

Communications Hub basics

Website Content management

Media Library

Campaigns

Dashboard

Church settings

User roles

Demo data

Documentation

Installation wizard or setup guide

## 5.2 Marketplace Edition Should Not Include

The marketplace edition should not include the full long-term SaaS advantage.

Exclude or reserve for Keryon Cloud:

FaithFlow

Keryon AI

Social API publishing

SaaS billing infrastructure

Hosted subscription system

Advanced analytics

White-label SaaS reseller features

Premium template marketplace

Deep website synchronization

Advanced integrations

Agency dashboard

Managed backups

Cloud update service

# 6. Product Protection Principle

Keryon must avoid becoming:

â€œJust another CodeCanyon script.â€

The marketplace edition should be useful and valuable, but it should not give away the full strategic platform.

The SaaS product must remain the best, easiest, most supported version.

# 7. Engineering Considerations While Building

Engineering should build Keryon in a way that does not block future packaging.

This does not mean building the marketplace edition now.

It means avoiding decisions that would make packaging painful later.

## 7.1 Configuration Discipline

Avoid hardcoding SaaS-only assumptions.

Use configuration where appropriate for:

App name

App URL

Storage driver

Mail driver

Queue driver

Support email

Company branding

Default timezone

File upload limits

## 7.2 Environment Separation

The application should support different deployment contexts:

local

staging

production

self_hosted

A future self-hosted edition may require a different setup flow from Keryon Cloud.

## 7.3 Installation Readiness

The codebase should eventually support:

Clear .env.example

Database migration instructions

Seed data

Admin user creation

Storage linking

Queue setup instructions

Scheduler setup instructions

File permission guide

Future self-hosted buyers must be able to install the product without needing the founder directly.

## 7.4 Demo Data

Engineering should eventually maintain safe demo seeders.

Demo data should include:

Demo church

Demo pastor

Demo media lead

Demo care team user

Demo congregation members

Demo prayer requests

Demo campaigns

Demo media records

Demo website content

Demo data must not include real church data.

## 7.5 Documentation-First Packaging

Every major feature should be documented enough that it can later become part of:

User manual

Admin guide

Installation guide

Developer guide

Marketplace documentation

Poor documentation will reduce marketplace approval chances and increase support burden.

# 8. Licensing Awareness

Before marketplace release, Keryon must have clear licensing rules.

The license must distinguish between:

Personal church use

Agency client use

Commercial resale

SaaS resale

White-label deployment

Redistribution

## 8.1 Normal Use Case

A church buys the self-hosted package and installs it for its own internal use.

This is acceptable.

## 8.2 Agency Use Case

An agency buys the package and installs it for one client.

This may require a different license depending on the marketplace rules.

## 8.3 SaaS Resale Use Case

A buyer installs Keryon and charges other churches to use it as a hosted SaaS.

This should not be allowed under the standard marketplace license.

This should require a separate commercial agreement, if allowed at all.

## 8.4 Redistribution

Buyers must not be allowed to:

Resell the source code

Upload it to another marketplace

Rebrand it as their own marketplace product

Share the package publicly

Create competing downloadable scripts from it

# 9. Marketplace Readiness Checklist

Before Keryon is submitted to any marketplace, the following must be ready.

[ ] Stable release build

[ ] Clean installation process

[ ] .env.example file

[ ] Installation documentation

[ ] Update documentation

[ ] User manual

[ ] Admin manual

[ ] Server requirements

[ ] Demo database seeders

[ ] Screenshots

[ ] Product video

[ ] Online demo

[ ] Demo login accounts

[ ] Support policy

[ ] License terms

[ ] Changelog

[ ] Version number

[ ] Known limitations

[ ] Security notes

[ ] File permissions guide

[ ] Queue/scheduler setup instructions

[ ] Storage setup instructions

[ ] Mail setup instructions

# 10. Recommended Marketplace Positioning

Do not sell the marketplace package as just:

Keryon

Use edition-specific naming.

Recommended names:

## Option A

Keryon Self-Hosted â€” Laravel Church Communications Platform

## Option B

Keryon Church Communications Suite

## Option C

Keryon for Agencies â€” Church Website & Communications CMS

## Option D

Keryon ChurchComms â€” Laravel CMS for Churches

Preferred:

Keryon Self-Hosted â€” Laravel Church Communications Platform

This keeps the main brand intact while making the package clear to marketplace buyers.

# 11. Marketplace Sales Copy Direction

The sales page should focus on clarity.

Recommended positioning:

Keryon Self-Hosted is a Laravel-based church communications platform that helps churches manage website content, prayer requests, media, campaigns, and congregation communication from one clean dashboard.

Emphasize:

Church communications

Website content updates

Prayer request workflow

Campaign planning

Media organization

Laravel + Filament foundation

Easy installation

Clean admin experience

Avoid positioning as:

Church ERP

Accounting software

Donation system

Attendance system

Full church management system

Page builder

# 12. Marketplace Feature Summary

Suggested marketplace feature bullets:

Church dashboard

Congregation directory

Prayer request management

Website content manager

Media library

Campaign management

Role-based access

Church profile settings

Clean Laravel architecture

Filament admin panel

Demo data included

Installation guide included

Future premium cloud-only features should not be advertised as included in the self-hosted edition.

# 13. Support Policy

Marketplace support must be tightly defined.

Support may include:

Installation guidance

Bug fixes

Documentation clarification

Basic configuration help

Support should not include:

Free custom development

Server administration

Website redesign

Payment integration

Custom feature requests

Hosting management

Third-party plugin debugging

Rebranding work

Unlimited consulting

A clear support policy prevents the marketplace edition from becoming an unpaid agency workload.

# 14. Update Strategy

The self-hosted edition must have a versioned release process.

Each release should include:

Version number

Changelog

Migration notes

Breaking changes

Update instructions

Backup recommendation

Example:

Keryon Self-Hosted v1.0.0

Keryon Self-Hosted v1.0.1

Keryon Self-Hosted v1.1.0

The SaaS version may move faster than the marketplace version.

# 15. Security Considerations

Marketplace distribution increases exposure.

Before release:

Remove test credentials

Remove development API keys

Remove local paths

Remove .env

Provide .env.example

Audit routes

Audit permissions

Audit policies

Disable debug mode

Confirm tenant scoping

Confirm upload restrictions

Confirm CSRF protection

Confirm rate limiting where needed

# 16. Branding Considerations

Keryon Self-Hosted may allow limited branding configuration.

Allowed:

Church logo

Church name

Church contact details

Public website content

Email sender name

Not allowed in standard edition:

Removing Keryon identity completely

Reselling as a new SaaS brand

Marketplace redistribution under another name

White-label licensing, if introduced, must be priced separately.

# 17. Build-Time Guardrails

Engineering should not overbuild marketplace features during the SaaS MVP.

The following are not required now:

License activation server

Marketplace-specific installer

Auto-update server

White-label panel

Reseller dashboard

Marketplace packaging scripts

Multi-license management

These may be added later after Keryon Cloud stabilizes.

# 18. What Engineering Should Do Now

Engineering should only make choices that keep the option open.

Immediate consideration, not implementation:

[ ] Avoid hardcoded cloud-only assumptions.

[ ] Keep configuration clean.

[ ] Maintain clear seeders.

[ ] Keep documentation updated.

[ ] Keep migrations clean.

[ ] Keep demo data separate from real data.

[ ] Avoid storing local machine paths in committed code.

[ ] Keep setup steps reproducible.

[ ] Maintain versioning discipline.

# 19. What Engineering Should Not Do Now

Engineering should not pause the SaaS build to create a marketplace package.

Do not build yet:

[ ] Marketplace installer

[ ] Licensing system

[ ] White-label engine

[ ] Envato-specific package

[ ] Codester-specific package

[ ] Self-hosted updater

[ ] Reseller panel

[ ] Marketplace demo site

Those become relevant after the core MVP is stable.

# 20. Recommended Timeline

## Phase 1 â€” Build Keryon Cloud MVP

Focus:

Congregation

Care Center

Communications Hub

Campaigns

Dashboard

Tenant safety

Media storage

Core UX

## Phase 2 â€” Pilot With Real Churches

Focus:

Feedback

Stability

UX refinement

Documentation gaps

Support patterns

## Phase 3 â€” Prepare Self-Hosted Edition

Focus:

Installer

Documentation

Demo site

Demo data

Packaging

Screenshots

Video walkthrough

## Phase 4 â€” Marketplace Launch

Recommended order:

Own website

Codester

CodeGrape

Gumroad / Lemon Squeezy

CodeCanyon, when available and appropriate

# 21. Product Office Declaration

Keryon may pursue marketplace distribution, but the SaaS product remains the primary business.

The marketplace edition must be treated as a carefully packaged derivative, not the heart of the product.

The governing principle is:

Build Keryon Cloud as the best product.
Package Keryon Self-Hosted as a controlled distribution channel.
Never sacrifice long-term SaaS value for short-term script sales.

# 22. Engineering Directive

Add this addendum to the Keryon documentation set as:

docs/00-Blueprint/Keryon_Blueprint_v1.3.2_Marketplace_Distribution_Addendum.md

Engineering should treat it as a future packaging consideration, not an immediate implementation directive.

No current sprint should change because of this addendum unless Product Office explicitly issues a marketplace preparation task.
