<?php

namespace App\FaithFlow\Actions\Exceptions;

use RuntimeException;

/**
 * Thrown when a generation provider response doesn't match its expected
 * shape (text vs list) — see K-FAITHFLOW-001D §56/§57. Message is always
 * safe to persist (never the raw provider payload or a stack trace).
 */
class MalformedGeneratedOutputException extends RuntimeException {}
