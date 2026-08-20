<?php

namespace App\Support;

/**
 * The small, deterministic, serializable payload a background job carries
 * across the request/worker boundary — see K-ASYNC-001 §6/§16. Deliberately
 * plain data (durable IDs only, never a serialized Eloquent model): the
 * trust decision does not live here, it lives entirely in
 * `TenantContext::runFor()`, which re-validates both `churchId` and
 * `actorUserId` fresh against the database at execution time. A stale or
 * even a hand-constructed instance of this class grants nothing on its own
 * — see K-ASYNC-001 report §7 for why that is a deliberate design choice,
 * not an oversight (mirrors K-FAITHFLOW-001E-R1's own "the boundary is the
 * restoration step, not the constructor" precedent).
 *
 * `churchId` is always required — every background operation is
 * tenant-owned (§7). `actorUserId` is nullable for system-originated work
 * (§9) — a scheduled/automatic operation acting on behalf of a Church with
 * no requesting human to attribute it to.
 */
final readonly class TenantExecutionContext
{
    public function __construct(
        public int $churchId,
        public ?int $actorUserId = null,
    ) {}
}
