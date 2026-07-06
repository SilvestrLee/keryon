# Keryon Product Language Standard

## Document Type

Product Experience Standard

## Status

Approved Product Office Standard

## Purpose

This document defines how Keryon should speak to users across the product.

Keryon must sound calm, clear, ministry-aware, and human.

Keryon must not sound like a generic admin panel, helpdesk, CRM, ERP, or database tool.

---

## Core Voice

Keryon should sound:

```txt
calm
warm
clear
pastoral
practical
respectful
ministry-aware
```

Keryon should not sound:

```txt
clinical
robotic
salesy
technical
corporate-heavy
ticketing-focused
case-management-focused
CRM-like
ERP-like
```

---

## Personalization Principle

Keryon may use the current church name when it improves clarity.

However, the church name must fit the sentence naturally.

Do not blindly replace:

```txt
your church
```

with:

```txt
{Church Name}
```

Instead, rewrite the sentence.

Bad:

```txt
Prayer requests submitted by Demo Church will appear here.
```

Good:

```txt
Prayer requests for Demo Church will appear here.
```

Better in some contexts:

```txt
Prayer requests shared with Demo Church will appear here.
```

---

## Church Name Usage Rules

Use the church name when referring to:

```txt
care activity at the church
the congregation belonging to the church
prayer requests for the church
communications prepared for the church
campaigns run by the church
website content for the church
```

Prefer natural phrases:

```txt
at {Church Name}
for {Church Name}
with {Church Name}
from {Church Name}
{Church Name}'s congregation
```

Avoid awkward phrases:

```txt
submitted by {Church Name}
your {Church Name}
{Church Name} church
the church named {Church Name}
```

---

## Fallback Rule

If no church name is available, use:

```txt
your church
```

or a natural fallback phrase.

The UI must not crash when:

```txt
no user is authenticated
user has no church_id
church relationship is null
```

---

## Possessive Rule

Use possessive church names only when the phrase is natural.

Good:

```txt
Demo Church's congregation
Grace Harmony Ministry's care activity
```

If a name ends in `s`, use a readable possessive form.

Example:

```txt
St James' congregation
```

Avoid awkward phrases like:

```txt
your church congregation directory
```

Prefer fallback:

```txt
your church's congregation directory
```

---

## Care Center Language

Care Center should feel pastoral and calm.

Use:

```txt
Care Center
Prayer Requests
Needs Attention
Currently Praying
Recently Submitted
Care Team
Review
Closed
```

Avoid:

```txt
tickets
cases
incidents
severity
priority
escalation
pipeline
resolved
assigned
SLA
```

---

## Prayer Request Copy Rules

A prayer request is usually submitted by a person, not by the church.

Avoid:

```txt
Prayer requests submitted by {Church Name}
```

Prefer:

```txt
Prayer requests for {Church Name}
Prayer requests shared with {Church Name}
Recent prayer requests for {Church Name}
```

For empty states:

```txt
No prayer requests yet. Prayer requests for {Church Name} will appear here.
```

Fallback:

```txt
No prayer requests yet. Prayer requests for your church will appear here.
```

---

## Care Dashboard Copy Rules

Preferred dashboard intro:

```txt
A calm overview of prayer requests and care activity at {Church Name}.
```

Fallback:

```txt
A calm overview of prayer requests and care activity at your church.
```

Needs Attention description:

```txt
New prayer requests waiting for review.
```

Currently Praying description:

```txt
Prayer requests currently being prayed over at {Church Name}.
```

Fallback:

```txt
Prayer requests currently being prayed over at your church.
```

Recently Submitted heading:

```txt
Recent prayer requests for {Church Name}.
```

Fallback:

```txt
Recent prayer requests for your church.
```

---

## Congregation Copy Rules

Use congregation language when referring to people records.

Good:

```txt
Start by adding the first person to {Church Name}'s congregation.
```

Fallback:

```txt
Start by adding the first person to your church's congregation.
```

Avoid:

```txt
Add your first database record.
Manage church users.
Member table is empty.
```

---

## Dashboard Layout Language

Dashboard copy should guide attention.

Use headings that answer:

```txt
What needs attention?
What is currently active?
What happened recently?
What should I do next?
```

Avoid headings that only describe data structures.

Bad:

```txt
PrayerRequest Stats
Status Count
Records
```

Good:

```txt
Needs Attention
Currently Praying
Recently Submitted
Open Requests
```

---

## Empty State Standard

Every empty state should explain:

```txt
what is missing
why it matters
what happens next or what action to take
```

Example:

```txt
No prayer requests yet. Prayer requests for Demo Church will appear here.
```

Example with action:

```txt
No one has been added yet. Start by adding the first person to Demo Church's congregation.
```

---

## Action Language

Actions should be simple and human.

Use:

```txt
Add Prayer Request
View All Prayer Requests
Add Person
Review Request
```

Avoid:

```txt
Create Record
Submit Entity
Resolve Ticket
Escalate Case
```

---

## Product Office Decision

Future UI and copy changes must follow this language standard.

If a sentence sounds awkward after inserting the church name, rewrite the sentence.

Natural language takes priority over mechanical placeholder replacement.
