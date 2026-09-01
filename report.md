# K-DOMAIN-001B — Provider-Neutral Custom Domain & Canonical Host Resolution Report

## 1–18. Executive result and governance

1. **Executive result:** COMPLETE. Keryon now has a provider-neutral, Church-scoped custom-domain lifecycle, deterministic DNS/TLS test seams, exact inbound host resolution, canonical URL selection, resilient first-party fallback, and immutable publication rendering. No production DNS/TLS provider was selected or invoked.
2. **Starting HEAD:** `c0b2b638028bbeaa8181e9b45da2b26d06b1c497` (`feat(shell): add global workspace header infrastructure`).
3. **Final implementation HEAD:** `924aa0e` (`feat(domains): add verified host and canonical resolution`). The report commit follows it; its exact hash is in the final handoff.
4. **ChurchDomain schema:** custom claims only; UUID, Church, normalized/display hostname, independent domain/TLS status, hashed token, ownership/routing/TLS timestamps, primary flag, health/failure data, lifecycle timestamps, creator membership, timestamps.
5. **Domain status enum:** `pending_verification`, `verified`, `active`, `degraded`, `disabled`, `released`.
6. **TLS status enum:** `not_started`, `provisioning`, `ready`, `failed`.
7. **Failure-code model:** bounded enum for invalid/platform/conflict/DNS/timeout/expiry/certificate/provider/disabled outcomes; raw provider errors are never persisted.
8. **Hostname normalization:** trims, lowercases, removes one terminal dot, enforces ASCII FQDN/label lengths and interior-hyphen syntax, and requires at least two labels.
9. **Platform-host protection:** one configured reserved-label/host seam rejects Keryon root, `www`, `app`, `central`, infrastructure labels, and first-party Church hosts from custom claims.
10. **Global uniqueness:** `normalized_hostname` has a DB unique index; case/trailing-dot equivalents collapse before insertion.
11. **Quarantine:** release preserves its unique row and records `released_at`. Quarantine is 30 days; there is no automatic reassignment. A later support path must require fresh claim and all proofs.
12. **Alias limit:** transactional Church lock allows at most two non-released claims: one primary candidate and one companion.
13. **One-primary invariant:** `makePrimary` locks the Church/domain rows and demotes/promotes in one retryable transaction. The Church row serializes concurrent switches.
14. **Keryon-subdomain computation:** no domain row; `PublicWebsiteUrl` computes `{slug}.{base_domain}`.
15. **Slug immutability:** activated Churches, and historical Churches with a WebsitePublication, reject slug mutation. Display-name edits and provisional provisioning remain valid.
16. **Administrator capability:** `WebsiteDomainManage` is now in Administrator only.
17. **Primary governance:** mutation additionally requires active same-Church TenantContext membership and `is_primary=true`.
18. **Policy:** `ChurchDomainPolicy` applies the same fail-closed rule; Organization authority grants nothing.

## 19–33. Claims, verification, lifecycle and evidence

19. **Claim service:** `RequestChurchCustomDomain` authorizes, normalizes, locks, checks limits/uniqueness/quarantine, generates a token, stores its hash, creates pending evidence, and returns the raw token once.
20. **Token handling:** raw tokens are never columns, events, logs, or jobs. Regeneration replaces the hash, clears ownership proof, emits evidence, and returns a new token once.
21. **DNS ownership:** TXT lookup uses `_keryon-verification.<hostname>` and constant-time SHA-256 comparison. Missing/mismatched evidence remains retryable.
22. **DNS routing:** separate verification checks configured CNAME ingress or configured apex A/AAAA observations. No production IP is hard-coded.
23. **DnsResolver:** narrow typed TXT/CNAME/address observations distinguish found, not found, timeout, mismatch, and unavailable.
24. **Fake DNS:** deterministic in-memory fake; automated tests make no public DNS calls.
25. **DomainProvisioner:** minimal provider-neutral request/check/deactivate contract, separate from DNS.
26. **Fake safety:** fake adapters throw in production; production defaults bind unavailable adapters and fail closed.
27. **TLS lifecycle:** provisioning/ready/failed are independent. Ready needs both DNS proofs; activation also needs an active Church and clean lifecycle state.
28. **Activation eligibility:** active Church + both verification timestamps + TLS ready/timestamp + active domain + not degraded/disabled/released.
29. **Lifecycle:** `ChurchDomainLifecycle` exclusively owns verified, TLS, activation, degradation, primary, disable, and release transitions under locks.
30. **Evidence:** immutable `ChurchDomainEvent` records bounded Church/domain/actor/type/failure/correlation/time only—no content, DNS payload, secret, or token.
31. **Job:** queued `VerifyChurchDomain` carries only domain ID/correlation, re-queries state, performs provider calls outside transactions, then applies typed transitions.
32. **Retry/idempotency:** bounded timeout/backoff, `ShouldBeUnique`, locks, and unique `(domain,event,correlation)` evidence prevent duplication.
33. **Failures:** counters increment safely; degradation requires ≥3 confirmed failures spanning ≥24h. No scheduler was added; later daily jittered checks can reuse this architecture.

## 34–57. Runtime, canonical URLs and isolation

34. **Host resolver:** exact first-party slug or exact indexed eligible custom hostname; no suffix/first-Church fallback.
35. **First-party host:** active slug resolves exact Church and its current immutable publication.
36. **Custom host:** only active, ownership/routing-verified, TLS-ready, non-disabled/released domain of an active Church resolves.
37. **Alias:** healthy non-primary alias issues 308 to effective canonical, preserving path/query.
38. **Unknown host:** arbitrary/unknown first-party hosts return 404 and never reach marketing.
39. **Platform hosts:** explicit marketing root; `www` 308 to root; app/central/reserved hosts cannot resolve as Churches. No Central surface added.
40. **PublicWebsiteContext:** receives public Church/host only; creates neither authentication nor TenantContext. Sequential-host tests prove isolation.
41. **ChurchPublicUrlResolver:** same shell contract selects one eligible primary or fallback while preserving active Church, entitlement, settings, and publication gates.
42. **Fallback:** Keryon host always serves the same publication and never redirects.
43. **Canonical custom:** healthy eligible primary becomes shell/public canonical URL.
44. **Degraded:** custom host fails closed and canonical immediately returns to first-party without content mutation.
45. **WebsiteSeo:** canonical, OG URL, and JSON-LD use effective canonical—not arbitrary Host.
46. **Sitemap:** absolute entries use effective canonical, including from fallback.
47. **Robots:** sitemap reference uses effective canonical.
48. **Internal links:** public page navigation is host-relative; authenticated preview routes remain unchanged.
49. **Media:** renditions remain on configured Keryon-owned `asset_origin`; no private/customer-domain delivery dependency.
50. **Entitlement:** existing Website availability rules remain; domain records are preserved.
51. **Unpublished:** no current WebsitePublication is 404 on every host; no placeholder/publication side effect.
52. **WebsitePublisher:** unchanged canonical boundary.
53. **Trust:** PublicationTrustGate and public-reference health unchanged.
54. **Communications:** provenance/outcomes untouched; only canonical link resolution can change.
55. **Care:** zero Care models, queries, fields, or capability flow.
56. **Organization:** OrganizationContext/scope/membership confer no domain authority.
57. **Shell:** no Blade hostname logic/redesign. Go to Website follows the resolver; Organization header is unchanged.

## 58–67. Performance, schema and files

58. **Queries:** response tests enforce ≤4 DB queries for first-party, custom, and alias paths before asset delivery; canonical selection is bounded and N+1-free.
59. **Indexes:** unique UUID/hostname; Church/status; status/last-check; Church/primary/status; release/hostname; event domain/time, Church/type/time, and idempotency.
60. **Migrations:** `2026_09_01_090000_create_church_domains_table.php`; `2026_09_01_090010_create_church_domain_events_table.php`. Additive only.
61. **SQLite:** full 1,036-test RefreshDatabase suite passes.
62. **MySQL:** 8.0.30 applied both migrations without reset; InnoDB schema, defaults, unique index, FKs, and named indexes inspected.
63. **Uniqueness:** normalization and service/DB tests cover equivalent and cross-Church collisions.
64. **Concurrency:** Church-row serialization plus retries leaves one primary; no generated-column workaround.
65. **Created:** `app/Domain/` (21 files); six domain enums plus `PublicWebsiteHostType`; job; two models; policy; three public-host/canonical classes; two migrations; three Domain test files.
66. **Modified:** Church role/model/membership, provider, public controller/middleware/context/media/SEO, shell URL resolver, public config/routes, three Proclaim views, focused Authorization/ChurchWebsite/Onboarding/Trust tests.
67. **Packages:** none.

## 68–90. Verification and delivery

68. **Security tests:** normalization, ASCII/IDN/punycode/scheme/path/port/wildcard/IP/local/platform rejection, collision, limit, quarantine, immutable evidence.
69. **Authorization:** Primary Administrator allowed; non-Primary Administrator, capability-less Primary, Communications, Care, Organization-only, cross-Church, suspended, removed, manipulated actors denied.
70. **Verification:** TXT match/mismatch/timeout, separate routing, TLS gate, token regeneration, fake determinism, duplicate job/evidence idempotency.
71. **Routing:** marketing/platform, exact first-party/custom, ineligible 404, alias 308, fallback, sequential/authenticated context isolation.
72. **Canonical/SEO:** first-party/custom/degraded, fallback custom canonical, canonical/OG/JSON-LD/sitemap/robots agreement, relative navigation.
73. **Slug:** activated/historically published mutation denied, display name allowed, provisional allocation retained.
74. **Website regression:** included in focused 249-test run and full suite; publication, preview, media, publisher, and unpublished boundaries pass.
75. **Shell regression:** focused run passes; resolver and Organization exclusion preserved.
76. **Communications regression:** all pass; attribution/outcome unchanged.
77. **Authorization/Tenancy/Care regression:** all pass; Care remains excluded.
78. **Organization regression:** all pass; hierarchy does not manufacture authority.
79. **Full suite:** PASS—1,036 tests, 3,375 assertions, PHP memory limit 1G.
80. **Build:** PASS—Vite 8.1.3, 7 modules transformed.
81. **Pint:** PASS—scoped files.
82. **Diff checks:** working and cached checks pass.
83. **Sensitive scan:** only `verification_token_hash` persistence; no raw token/provider secret/DNS payload. New domain provider queries do not bypass tenancy; lifecycle unscopes only authoritative IDs under locks.
84. **Browser first-party:** PASS at 1440×1000 and 390×844; HTTP 200, immutable heading, relative link, custom canonical.
85. **Browser custom:** PASS at both sizes; HTTP 200, same immutable publication, custom canonical, relative navigation. Screenshots remained temporary.
86. **Alias/fallback:** alias 308 Location `https://domain-proof.test/about?campaign=proof`; fallback HTTP 200/custom canonical. Isolated fixture deleted exactly.
87. **Final status:** milestone committed; pre-existing unrelated `.gitignore`, seeder/docs and agent/skill artifacts remain unstaged. Exact status is in handoff.
88. **Preservation:** no stash/reset/clean/pull/merge/rebase/broad add. Only explicit 001B paths/report staged.
89. **Commits:** `6abd0c1 feat(domains): add governed custom-domain claims`; `924aa0e feat(domains): add verified host and canonical resolution`; report commit in handoff.
90. **Push:** not performed.

## 91–95. Remaining infrastructure and closure

91. **Production ingress blockers:** approved DNS resolver, ingress/apex targets, TLS adapter/API/credentials, callbacks, and production proof remain. Production fails closed.
92. **Wildcard blockers:** `*.keryon.app` DNS/TLS remain K-DOMAIN-001D deployment prerequisites.
93. **Entitlement/grace:** lapsed-Website behavior remains a Product Office policy decision; records and current runtime semantics are preserved.
94. **Can 001B close?** Yes. Model, governance, verification, routing, canonical/fallback, security, MySQL/SQLite, browser, build, and regressions are proven.
95. **Is 001C ready?** Yes. UI can bind to real actions/lifecycle, safely show one-time instructions, retain Primary+Administrator governance, and never simulate production TLS readiness.
