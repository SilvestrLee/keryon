# Keryon UX Feedback and System State Standard

## Document Type

Engineering Hardening Standard

## Parent Documents

- Keryon Master Blueprint v1.3
- Keryon Blueprint v1.3.1 Engineering Hardening Addendum
- Scope Challenge Protocol
- Product Language Standard
- CLAUDE.md

## Status

Approved Standard

## Purpose

This document governs how Keryon communicates system state, progress, success, and failure to users.

It is a companion to `Product_Language_Standard.md`, not a replacement for it.

```txt
Product Language Standard governs:
  Natural Keryon terminology
  Church-name personalization
  User-facing vocabulary
  Avoidance of generic system terminology

UX Feedback and System State Standard governs:
  Workflow state visibility
  Real (non-decorative) system feedback
  Actionable attention explanations
  Success feedback
  Failure feedback
```

Read both. Word choice comes from the Product Language Standard. What gets said, and when, comes from this document.

---

## State Must Be Understandable

Important workflows must expose meaningful system state to the user.

Only use states that exist in the approved domain model. Do not invent a status merely to make an interface look more informative.

### Prayer request

The approved states are `App\Enums\PrayerRequestStatus`: `new`, `reviewed`, `praying`, `closed`. Interface copy may phrase these naturally (see Product Language Standard's Care Dashboard Copy Rules — e.g. "waiting for review" for `new`), but the underlying state shown must always resolve to one of these four values. Do not add a UI-only state such as "Acknowledged" or "Follow-up scheduled" unless it is first added to the enum through an approved schema change.

### Congregation

The approved states are `App\Enums\CongregationStatus`: `active`, `visitor`, `inactive`. This enum describes ministry relationship, not record lifecycle — see the Scope Challenge Protocol's Enum and Status Drift trigger before proposing new values.

### Website publication

Conceptually: `Draft` → `Preview` → `Publish`. This is not yet implemented as a state machine. When it is built, any additional operational state (e.g. a "Publishing" transitional state) must be technically justified and Product Office-approved before it appears in copy — see "No Fake System Progress" below.

### Campaigns

The approved states, per Master Blueprint v1.3 §"Campaign Status", are: `Draft`, `Planned`, `Active`, `Completed`, `Archived`. Do not surface any other campaign state until Campaigns is implemented against this list.

---

## No Fake System Progress

If Keryon says a workflow is happening, that workflow must actually be happening.

```txt
"Publishing..."        → a publish operation must actually be underway.
"Image optimised..."   → image processing must actually have occurred.
"Care team notified..." → the notification must have succeeded, or the
                           copy must accurately describe a queued/pending
                           state rather than claiming completion.
```

Do not use decorative pseudo-progress (spinners, staged fake messages, artificial delays) to make Keryon appear more sophisticated than the underlying operation. Users must be able to trust Keryon's status language without qualification.

---

## Explain Attention States

Do not surface an unexplained warning or count. Every "needs attention" signal must say what needs attention and, where practical, why.

```txt
Weak:    Website needs attention.
Better:  Your homepage service information has not been reviewed recently.

Weak:    3 pending.
Better:  Three prayer requests have not yet been reviewed.

Weak:    Campaign incomplete.
Better:  This campaign has no communication scheduled for its final week.
```

Every explanation must be derived from real system data or an explicit, deterministic rule already present in the codebase (a date comparison, a missing relation, a count against a threshold). Keryon must not fabricate intelligence or imply analysis that was not actually performed. This is the same restraint the Dashboard Philosophy in the Filament Product Experience Review already applies to metrics — this standard extends it to attention/warning copy specifically.

---

## Success Messages

Success feedback must describe what actually happened, not just that "something" succeeded.

```txt
Avoid:   Done. / Saved. / Success.

Prefer:  Prayer request updated.
Prefer:  Homepage changes saved as a draft. They are not yet visible on your website.
Prefer:  Campaign scheduled for 12 August at 9:00 AM.
```

The message should remove uncertainty about the outcome — the user should not have to guess what state the record is now in.

---

## Error Messages

Every error message must answer three questions:

1. What failed?
2. What remains safe (unaffected/unchanged)?
3. What can the user do next?

```txt
Example:
We could not publish the homepage update. Your currently published
website remains unchanged. Review the highlighted fields and try again.
```

Do not expose exception messages, stack traces, class names, SQL, or other implementation terminology in ordinary user-facing errors. That detail belongs in application logs — see `Logging_Standard.md`.

---

## Product Office Decision

Approved as the standing standard for workflow-state, progress, attention, success, and error language across Keryon. Applies wherever such copy is written, regardless of module.
