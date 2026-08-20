<?php

namespace App\Support\Exceptions;

use RuntimeException;

/**
 * Thrown by `TenantContext::runFor()` when a background execution context
 * fails re-validation at execution time — see K-ASYNC-001 §11/§12/§21. This
 * is the SECURITY/CONTEXT and TENANT-STATE failure category: the Church is
 * missing/inactive, or the actor's membership at that Church is no longer
 * active. Retrying with the exact same context will deterministically fail
 * again — this is never a transient-failure category, and
 * `App\Jobs\TenantAwareJob` deliberately fails the job immediately on this
 * exception rather than consuming a retry attempt.
 */
class UntrustedTenantExecutionException extends RuntimeException {}
