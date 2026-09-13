<?php

namespace App\Domain;

/**
 * Ongoing domain health is not binary — see K-DOMAIN-001E §6.
 *
 * Healthy: every authoritative check for an active custom domain succeeded.
 *
 * ConfirmedUnhealthy: Keryon received reliable evidence the domain
 * configuration itself is wrong (TXT absent/mismatched, routing definitely
 * elsewhere, provider terminal TLS failure). May contribute to degradation.
 *
 * Indeterminate: an operational visibility failure (DNS timeout, resolver
 * or provider API unavailable, malformed response, transitional TLS state).
 * Never proof the customer's domain is broken — must never degrade a domain
 * or reset/advance the confirmed-failure streak.
 */
enum HealthOutcome
{
    case Healthy;
    case ConfirmedUnhealthy;
    case Indeterminate;
}
