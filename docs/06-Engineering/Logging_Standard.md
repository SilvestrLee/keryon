# Keryon Logging Standard

## Document Type

Engineering Hardening Standard

## Parent Documents

- Keryon Blueprint v1.3.1 Engineering Hardening Addendum
- Scope Challenge Protocol
- CLAUDE.md

## Status

Approved Standard

## Purpose

Keryon handles pastoral information, including prayer requests and care notes. Logging must be deliberately conservative: enough to diagnose a problem, never enough to unnecessarily reproduce private church data outside the system of record.

> Log enough to diagnose the operation, not enough to unnecessarily reproduce private church data.

---

## What Operationally Meaningful Logs May Include

Where relevant to the failure being logged:

```txt
action
user identifier
church identifier
record identifier
operation
failure category
exception class
timestamp
correlation/reference identifier
```

---

## What to Avoid Logging

Avoid logging full content bodies unnecessarily. In particular, do not duplicate into logs:

```txt
full prayer request text
pastoral notes
private congregation notes
sensitive form bodies
```

If a failure needs to be diagnosed, log the record identifier and church identifier so the actual content can be looked up in the database by an authorized user — not the content itself.

---

## What Must Never Be Logged

```txt
passwords
session secrets
access tokens
API secrets
private keys
.env values
authentication credentials
```

This applies to `Log::*` calls, exception context, queue job payloads that get logged on failure, and third-party error-reporting integrations alike.

---

## Product Office Decision

Approved as the standing logging discipline for all Keryon application code. Applies immediately — no implementation work is deferred by this document.
