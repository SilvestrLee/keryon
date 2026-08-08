# Keryon Deployment Guardrails

## Document Type

Engineering Hardening Guardrail

## Parent Documents

- Keryon Blueprint v1.3.1 Engineering Hardening Addendum
- Scope Challenge Protocol
- CLAUDE.md

## Status

Approved Guardrail (interim — not the production runbook)

## Purpose

Keryon does not yet have a staging or production deployment runbook. This document is not that runbook. It exists to prevent unsafe assumptions before that runbook is written — so that no deployment command gets run against a generic "Laravel deployment guide" default rather than Keryon's actual target environment.

A full reproducible deployment procedure (local / staging / production, per KO-GOV-001 §50) remains a deferred item — see "Deferred" below.

---

## Inspect Before Choosing Commands

Before any deployment command is chosen, identify:

```txt
hosting environment
PHP version
Composer version
Node version
web server
document root
database engine
queue availability
scheduler availability
filesystem permissions
symlink behaviour
persistent storage location
object storage configuration
environment-variable handling
frontend-build process
backup availability
rollback approach
```

Do not treat a generic Laravel deployment guide as universally safe for Keryon. Cross-reference `Media_Storage_Configuration_Review.md` and `Media_Path_Strategy.md` for the persistent-storage and object-storage pieces of this list specifically.

---

## Commands Are Contextual, Not Universal

Commands such as:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
```

may be correct in some environments. They are not standing instructions. Each one must be justified against the actual target environment identified above. In particular:

```txt
migrations can mutate production data
storage:link depends on hosting/server structure
caching can expose or preserve bad configuration
frontend builds require compatible Node tooling
local filesystem assumptions may not match production persistence
```

This is the deployment-specific application of CLAUDE.md's existing rule: do not run `php artisan migrate` (or any deployment command with real-world effect) without it having been explicitly shown and approved first.

---

## PowerShell Safety

Where PowerShell automates a deployment or development task that makes meaningful changes, scripts should:

```txt
use explicit paths
fail on error rather than continue silently
validate target files before replacing them
avoid destructive wildcard deletion
avoid embedding secrets in source
report what action is being performed
verify the final state
```

```powershell
$ErrorActionPreference = "Stop"
```

is preferred for any multi-step modification script, so a failed step does not silently cascade into a broken later step. This is a safety principle for scripts that make real changes — it does not mean every one-line command needs to become a script.

---

## Deferred

The following remain future work, not covered by this guardrail document:

```txt
Full staging/production deployment runbook
Object storage (S3-compatible) provisioning
Queue/scheduler infrastructure setup
Backup and rollback procedure documentation
CI/CD pipeline definition
```

## Product Office Decision

Approved as the minimum standing guardrail until a full deployment runbook exists. Supersede this document's relevant sections (not the whole file) as each piece of real deployment infrastructure is built and documented.
