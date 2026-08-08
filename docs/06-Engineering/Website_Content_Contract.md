# Keryon Website Content Contract Standard

## Document Type

Engineering Hardening Standard

## Parent Documents

- Keryon Master Blueprint v1.3
- Keryon Blueprint v1.3.1 Engineering Hardening Addendum
- Scope Challenge Protocol
- Filament Product Experience Review
- CLAUDE.md ("Website Content Rule")

## Status

Approved Standard

## Purpose

CLAUDE.md's Website Content Rule establishes the principle: Keryon is not a page builder, website sections are fixed, and only text/images/buttons/links/banners are editable through a Draft → Preview → Publish workflow.

This document does not redesign that architecture. It defines the reusable **contract format** that every editable website component must have before it is built, so the Keryon admin, the structured content it produces, and the public frontend component that renders it stay in agreement.

Do not create database fields, migrations, or an actual CMS implementation from this document alone — it is a template for specifying components, not an implementation.

---

## Fixed Experience, Flexible Content

```txt
Keryon owns approved content.
The frontend owns layout.
```

Church staff may eventually control approved content such as text, images, video, links, calls-to-action, service information, profiles, and announcements.

Church staff must never receive unrestricted access to HTML, CSS, arbitrary containers or sections, custom layouts, unrestricted fonts, unrestricted colors, or free-positioned elements. This is what keeps Keryon from becoming a page builder — it is a permanent rule, not a v1 scope limitation.

---

## Contract Format

Every editable website component must be specified using this template before implementation begins:

```txt
COMPONENT
<Name>

PURPOSE
<One sentence — what this component is for on the page.>

FIELDS
<field_name>
<field_name>
...

REQUIRED FOR PUBLICATION
<fields that must be filled in before the page can be published>

CONDITIONAL VALIDATION
<rules that only apply when another field is present, e.g.
"If a button label exists, its URL must also exist.">

FRONTEND BEHAVIOUR
<what the frontend does with each optional field when it is absent>

PREVIEW
<how preview renders this component — see "Preview Fidelity" below>

PUBLICATION
<confirmation that draft values do not silently replace published values>
```

### Worked example

```txt
COMPONENT
Homepage Hero

PURPOSE
The primary introduction on the church homepage.

FIELDS
eyebrow
heading
description
primary_button_label
primary_button_url
secondary_button_label
secondary_button_url
background_image
mobile_background_image
image_alt_text

REQUIRED FOR PUBLICATION
heading

CONDITIONAL VALIDATION
If a button label exists, its URL must also exist.
If an image exists, accessible alt text must be supplied where appropriate.

FRONTEND BEHAVIOUR
Secondary button is hidden when not configured.
Mobile-specific media is used where configured, otherwise the primary
background image is used at all breakpoints.
Missing optional content must not create empty UI elements (no empty
button shells, no broken image icons).

PREVIEW
Preview renders using the same frontend Hero component used on the
public site, not a raw field dump.

PUBLICATION
Draft values do not automatically replace published values. Publication
is an explicit, separate action.
```

Do not build a frontend component whose editable content cannot be represented reliably in this format. Do not add a CMS field the frontend has no defined behaviour for.

---

## Content Fallback Rules

Every optional field must have one of these defined behaviours when empty:

```txt
Hide the associated element.
Use an approved fallback value.
Require the field before publication.
Display a preview-only placeholder (never shown on the live site).
```

The live, published site must never expose any of the following:

```txt
null
Lorem Ipsum
broken image icons
undefined array indexes
empty buttons
invalid or empty links
developer/debug placeholders
```

---

## Preview Fidelity

Where technically practical, preview must render through the same frontend component the public site uses — not a database field dump. The purpose of preview is to give the editor confidence before publishing: heading length, image crop, button placement, and missing content should all be visible in preview exactly as they would appear live.

---

## Publication

Saving a draft must never be presented as "now live." The interface must distinguish:

```txt
Draft saved
Unpublished changes exist
Preview available
Publication successful
Publication failed
```

If publication fails, the previously published website must remain intact wherever the underlying architecture allows it — see the Error Messages section of `UX_Feedback_and_System_State_Standard.md` for how failure must be communicated.

---

## Product Office Decision

Approved as the required specification format for every future editable website component. No component may be built directly from a database table without first being written up against this contract.
