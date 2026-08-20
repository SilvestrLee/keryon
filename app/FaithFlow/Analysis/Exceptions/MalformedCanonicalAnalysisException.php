<?php

namespace App\FaithFlow\Analysis\Exceptions;

use RuntimeException;

/**
 * Thrown by CanonicalAnalysis::fromProviderResponse() when the provider's
 * structured-output payload is missing a required field or has the wrong
 * shape — see K-FAITHFLOW-001C §10/§18. The message is always safe to
 * persist to FaithFlowRun::analysis_error (never the raw provider payload
 * or a stack trace).
 */
class MalformedCanonicalAnalysisException extends RuntimeException {}
