<?php

namespace App\Domain\Provisioning;

use RuntimeException;

/**
 * Thrown when a configured DomainProvisioner driver is missing mandatory
 * configuration (e.g. Cloudflare zone id, API token, or API base URL).
 *
 * Never include configuration values (especially API tokens) in the
 * exception message — see docs/06-Engineering/Logging_Standard.md.
 */
final class ProvisioningConfigurationException extends RuntimeException {}
