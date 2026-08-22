# Keryon Background Execution Foundation

## Document Type

Engineering Hardening Standard

## Parent Documents

- Keryon Blueprint v1.3.1 Engineering Hardening Addendum
- Keryon_Identity_Membership_Authorization_Architecture_v1.4.1.md
- Logging_Standard.md
- K-ASYNC-001 Product Office directive

## Status

Approved Standard — established by K-ASYNC-001

## Purpose

A background/queued process does not inherit the HTTP request's authenticated
User, session, or automatically-resolved `TenantContext`. This document
describes the reusable, tenant-aware execution contract every future
background operation (FaithFlow generation, publishing, Media Library
processing, Design Studio rendering, imports, scheduled communications, and
any other tenant-owned background work) must use — not a FaithFlow-specific
mechanism.

> A background process must never lose the Church context, actor
> provenance, ownership boundary, or execution identity that existed when
> the work was requested.

---

## The three concepts

- **TENANT** — which Church owns this work. Always required, always
  explicit. Never derived from `$user->church_id`.
- **ACTOR** — which human (if any) requested the work. Nullable — some
  background work is system-originated (scheduled publishing, automatic
  retries) and has no requesting human. Actor provenance is *not*
  automatically authorization.
- **EXECUTOR** — the queue worker process currently performing the work.
  Has no tenant identity of its own; it must be told which tenant it is
  acting for, for the duration of exactly one job.

## The contract

```text
REQUEST CONTEXT (HTTP, Auth/session-derived)
        ↓  TenantContext::capture()
EXECUTION CONTEXT (TenantExecutionContext — church_id + nullable actor_user_id)
        ↓  queued, serialized
QUEUED WORK
        ↓  TenantContext::runFor($context, $callback)
VALIDATE CONTEXT (Church active? actor's membership still active?)
        ↓
RESTORE SAFE TENANT CONTEXT (for the duration of $callback only)
        ↓
EXECUTE ($callback / Job::execute())
        ↓
ALWAYS CLEAR (finally block — success or exception)
```

### Dispatch-time capture — `TenantContext::capture()`

Call this from inside an already-authenticated, already-authorized request
(after any `Gate::authorize()` calls) to produce a `TenantExecutionContext`
— a small, plain, serializable object (`churchId: int`, `actorUserId: ?int`)
safe to pass into a Job's constructor. `capture()` takes no parameters: it
can only ever reflect whatever `TenantContext` has already resolved via the
normal, trusted HTTP path — there is no way to ask it for an arbitrary
Church/User combination.

For system-originated work (no requesting human — §9), construct
`TenantExecutionContext` directly with `actorUserId: null` instead of using
`capture()`.

### Execution-time restoration — `TenantContext::runFor()`

The *only* way to activate a restored tenant identity inside a Job. Always:

1. Re-resolves the Church fresh from the database and rejects if missing or
   inactive.
2. If the context carries an actor, re-resolves that actor's membership at
   that Church fresh from the database and rejects if it is no longer
   active (covers a membership revoked, or the actor removed, between
   dispatch and execution).
3. Activates the restored identity — `TenantContext::currentChurchId()` /
   `currentMembership()` / `currentChurch()` now return the restored
   values, exactly as they would under an authenticated HTTP request with
   that membership active.
4. Runs the callback.
5. **Always** clears the restored identity in a `finally` block — including
   when the callback throws.

A rejected restoration throws `App\Support\Exceptions\
UntrustedTenantExecutionException` — never silently falls back to any
other identity.

**The queue payload is never trusted on its own.** `TenantExecutionContext`
is plain data; the trust decision lives entirely in `runFor()`'s
fresh-from-database re-validation, not in the object's constructor. A
stale, forged, or hand-constructed context grants nothing — it is
re-checked every time.

### `App\Jobs\TenantAwareJob`

The reusable abstract base every future tenant-owned background operation
should extend. Subclasses implement `execute()` only — `TenantAwareJob`
handles context restoration, structured log context (`church_id`,
`actor_user_id`, `job`, `attempt` — via Laravel's `Context` facade, never
full domain content, per Logging_Standard.md), and fail-fast handling for
`UntrustedTenantExecutionException` (a context/tenant-state failure is
never retried — retrying with the same context will deterministically fail
again).

Inside `execute()`, existing domain code — Policies, Actions,
`BelongsToChurch`-scoped Eloquent queries — behaves exactly as it does
today under an authenticated HTTP request, because the same `TenantContext`
service is active either way.

## Dispatch-time vs. execution-time authorization

`TenantAwareJob`/`runFor()` only ever re-validate *tenant validity*
(Church active, membership still active) — they do **not** re-run the
original `Gate::authorize()` check from dispatch time. Two categories exist,
and K-ASYNC-001 deliberately does not force one rule onto every future job:

- **Accepted-at-dispatch** — the operation was authorized when requested;
  tenant-validity revalidation at execution time is sufficient. Most
  background work should default to this category.
- **Execution-time re-authorization** — a specific operation's risk profile
  warrants re-running `Gate::forUser($membership->user)->authorize(...)`
  inside `execute()`, using the restored membership. The restored
  membership is available via `TenantContext::currentMembership()` inside
  `execute()`, so this remains possible per-job without any extra
  machinery.

Document which category a given job belongs to in that job's own docblock.

## Tenant deactivation / missing targets

A Church deactivated between dispatch and execution is rejected by
`runFor()` before any domain code runs (see above). A target record deleted
between dispatch and execution is each job's own responsibility to detect
(inside `execute()`, via the normal tenant-scoped query returning
nothing) — `TenantAwareJob` does not, and must not, silently recreate
missing domain records.

## Cross-Church defence

Once `runFor()` restores `churchId`, every existing `BelongsToChurch`-scoped
query automatically enforces tenant isolation — this is the same mechanism
already proven since K-FAITHFLOW-001B, not new code. A target belonging to
a different Church than the restored context simply is not found by a
normal tenant-scoped query; there is no separate "cross-Church defence"
mechanism to build per job.

## Idempotency

K-ASYNC-001 does not provide a generic idempotency framework. Two options,
chosen per job:

1. Rely on the underlying domain Action already being idempotent (every
   FaithFlow generation/approval Action already is, by design — an
   idempotent Action makes its calling Job idempotent for free).
2. Opt into Laravel's native `ShouldBeUnique` for an operation that is not
   naturally idempotent. This is real, already-provisioned infrastructure
   in this environment — the `database` cache driver's lock table
   (`cache_locks`) already exists and supports atomic locks — not a
   sync-driver-only approximation.

## Retries / backoff / failure classification

`TenantAwareJob` defaults: `$tries = 3`, `backoff() = [10, 30, 60]` seconds
— sane platform defaults, always overridable per job.

Failure categories:

- **Context/tenant-state failure** (`UntrustedTenantExecutionException`) —
  never retried; handled generically by `TenantAwareJob::handle()`.
- **Transient failure** (e.g. a provider temporarily unavailable) —
  propagates normally; Laravel's own `tries`/`backoff` apply.
- **Permanent domain failure** (e.g. target deleted) — each job's own
  responsibility to detect inside `execute()` and decide whether to fail
  immediately (`$this->fail()`) rather than exhaust retries.

## Observability

`TenantAwareJob::handle()` adds `job`, `church_id`, `actor_user_id`, and
`attempt` to Laravel's `Context` facade for the duration of the job — these
attach automatically to any log entry written during execution, and are
removed again in a `finally` block. Failures are logged via `Log::warning`
(rejected context) / `Log::error` (`failed()`) with identifiers and a
failure category only — never full source/domain content, provider
credentials, or `.env` values, per Logging_Standard.md.

## Worker-process safety

Laravel's own `queue:work` loop calls `$app->forgetScopedInstances()` at
the top of every iteration, before reserving the next job (see
`Illuminate\Queue\QueueServiceProvider`) — `TenantContext` is bound as
`scoped()` (established in K-IDENTITY-001B-R1), so a real, long-lived
worker process already gets a **framework-native**, zero-custom-code
guarantee that no `TenantContext` state can survive from one job into the
next. `TenantAwareJob`'s own `runFor()`-driven cleanup is a second,
belt-and-suspenders layer on top of that — and the layer that actually
matters for direct/synchronous invocation (as in this milestone's own
tests, which call `->handle()` sequentially in-process without a real
worker loop).

## Adopting this foundation for a new background operation

1. `Gate::authorize(...)` as normal, inside the authenticated request.
2. `$context = app(TenantContext::class)->capture();` (or construct a
   system-originated `TenantExecutionContext` directly).
3. `SomeJob::dispatch($context, ...targetIds)` — pass durable IDs for
   targets, never serialized domain models, per K-ASYNC-001 §16.
4. `SomeJob extends TenantAwareJob`, implements `execute()` calling the
   existing domain Action exactly as an HTTP controller would.
5. Decide and document: accepted-at-dispatch or execution-time
   re-authorization; retry/backoff overrides if the platform defaults
   don't fit; whether the operation needs `ShouldBeUnique`.
