# Keryon Central production security runbook

## Production gate

Central is a privileged platform plane at `central.keryon.app`. Production readiness requires `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, `CENTRAL_DOMAIN=central.keryon.app`, `SESSION_SECURE_COOKIE=true`, `SESSION_SAME_SITE=lax`, and a host-scoped session cookie (`SESSION_DOMAIN` unset/null). Configure the actual trusted proxy topology at deployment; never trust every proxy/header by default. HSTS belongs at the edge. Validate any CSP against Filament, Livewire, Vite, and QR setup before enforcing it.

Queue workers, scheduler, production mail, DNS resolver, TLS/domain provisioner, error monitoring, failed-job alerts, and backups must be operational. Provider absence is degraded state, never simulated success.

## Staff access and MFA

Grant access only to a verified existing User through the governed bootstrap/staff process. The staff member must enroll an authenticator app before entering Central, verify the first code, and store the ten one-time recovery codes offline. There is no skip, remember-me MFA grant, shared account, master password, hidden route, or bypass token.

Central MFA satisfaction expires after 30 minutes idle or eight hours absolute. Credential-security changes require proof no older than ten minutes. Customer workspace authentication and memberships do not grant Central.

Review active PlatformMemberships periodically. Suspension/removal fails closed on the next request. Do not delete all User sessions because that could destroy unrelated customer sessions.

## Lost authenticator and recovery

The staff member first uses password plus one unused recovery code. The code is consumed. They should immediately re-verify and regenerate recovery codes from Central Security.

If both authenticator and recovery codes are lost, escalate to a Platform Administrator or Trust & Security operator. The operator verifies identity out of band, establishes fresh MFA, supplies their password, selects a bounded reason, and records a note through MFA Recovery. Reset destroys target factor material, increments credential version, audits the event, and forces re-enrollment. No other role may reset platform MFA.

## Suspected compromise

1. Suspend/remove the PlatformMembership.
2. Review immutable Platform Audit and safe security logs.
3. Reset MFA and rotate affected external credentials where appropriate.
4. Assess governed operations performed by the account.
5. Escalate the incident and preserve evidence without credential payloads.
6. Restore only after identity, password, MFA, device, and incident review.

Authentication/MFA attacks must be observable by safe identifier, IP, outcome, and rate-limit state—never password, OTP, secret, recovery code, session ID, or token.

## Provider and job incidents

- Activation mail outage: inspect Invitation Delivery/failed jobs; never expose activation links or mark delivery successful manually.
- DNS/TLS outage: report unavailable/degraded and use only canonical retry after recovery.
- Queue failure: restore workers, inspect `failed_jobs`, and use only domain-specific retry seams.
- Scheduler failure: restore it and review time-sensitive activation/domain work.

Monitor Central auth/MFA/recovery failures and throttling, MFA resets, membership changes, governed-operation failures, failed jobs, delivery/domain failures, and unexpected exceptions.

## Audit and emergency restriction

Authorized staff review immutable evidence in Platform Audit. Audit never contains credentials.

To shut Central down, disable its deployment route or deliberately fail production readiness configuration. No read-only emergency mode, master password, shared account, hidden bypass, or raw-database workflow exists.
