# K-DOMAIN-001C — Church Domain Management Experience Report

## Result and authority

1. **Executive result:** COMPLETE. Keryon now provides a production-quality, Church-facing Website → Domains experience over the canonical 001B lifecycle. It is truthful when production DNS/TLS infrastructure is unavailable and never simulates readiness.
2. **Starting HEAD:** `810099462219e55d9553740e237cf50de5be7e93` (`docs(domains): record K-DOMAIN-001B verification`).
3. **Final implementation HEAD:** `71241c5` (`feat(domains): add Church domain management experience`). The report commit follows; its exact hash is in the final handoff.
4. **Page/location:** purpose-built Filament page `ManageDomains` in the existing Church Website cluster at `/admin/website/manage-domains`.
5. **Navigation:** Domains appears as the final Website sub-navigation destination. No Organization or generic Settings destination was introduced.
6. **Access governance:** read and mutation require current active same-Church membership, `WebsiteDomainManage`, and `is_primary`.
7. **Primary Administrator:** Livewire and browser proof confirm access, claim, regenerate, verify, primary, disable, and release paths.
8. **Non-Primary denial:** page access is hidden/denied; stale loaded-page action reauthorizes and fails closed.
9. **Communications denial:** Communications alone cannot access or mutate domains.
10. **Care denial:** Care alone cannot access or mutate domains.
11. **Organization-only denial:** OrganizationMembership grants no page or action authority.

## Addresses, claims, and setup

12. **Keryon address:** computed with `PublicWebsiteUrl`, prominent, copyable, and explicitly described as permanent fallback. No ChurchDomain row is created.
13. **Official address:** supplied by `ChurchPublicUrlResolver`; healthy custom primary is shown, otherwise the Keryon address. The page does not calculate canonical state in Blade.
14. **Empty state:** guided “Connect your domain” surface, no technical table or domain-purchase claim.
15. **Claim flow:** focused hostname form invokes `RequestChurchCustomDomain`; server resolves Church/member/creator/state.
16. **Normalization:** 001B `DomainNameNormalizer` remains authoritative; `WWW.GraceHall.org.` displays as `www.gracehall.org`.
17. **Validation copy:** scheme/path/port, IDN, platform host, invalid labels, collision, and limit errors are translated into safe Church-friendly guidance.
18. **Limit UX:** after two non-released claims, Add is removed and the one-primary-plus-one-companion limit is explained before another request.
19. **One-time token:** raw value is displayed only in the current Livewire component immediately after claim/regeneration, with an accessible copy control.
20. **Returning token:** reload has a null token, does not reconstruct it, and explains why it is unavailable.
21. **Regeneration:** deliberate confirmation warns that the old TXT value stops working, then calls `RegenerateChurchDomainToken`.
22. **DNS instructions:** exact TXT type/name/one-time value are shown; routing record is derived only from configured ingress expectations.
23. **Provider-neutral:** guidance references the Church’s DNS provider generically and warns not to remove MX/SPF/DKIM/email records.
24. **Verification action:** “Check connection” dispatches canonical `VerifyChurchDomain`; no DNS logic exists in page/view.
25. **Async behavior:** queued dispatch returns a truthful “check started” notification; no blocking lookup or polling loop.
26. **Propagation:** missing records are described as not visible yet, with propagation/retry guidance rather than terminal failure language.
27. **Ownership:** displayed independently from routing and backed only by `ownership_verified_at`.
28. **Routing:** displayed independently as Waiting for DNS or Connected from `routing_verified_at`.
29. **HTTPS:** Not started, Setting up HTTPS, HTTPS ready, and Needs attention derive from the TLS enum.
30. **Unavailable production adapter:** routing values/actions are withheld and the page says activation is unavailable in this environment. No fake terminology is customer-visible.

## Readiness, resilience, and lifecycle actions

31. **Readiness:** a five-part stored-state sequence shows Domain added, Ownership verified, Domain connected, HTTPS ready, Active. `ChurchDomain::isEligible()` remains authority.
32. **Make Primary:** visible only for eligible non-primary domains; confirmation explains official-address change and permanent fallback; action calls `MakeChurchDomainPrimary`.
33. **Primary success:** official card switches to custom URL while the permanent Keryon card remains visible.
34. **Companion alias:** eligible non-primary hostname is labelled Companion domain and copy explains current one-plus-one product limit. Existing 308 runtime remains unchanged.
35. **Apex:** model remains permissive; UI avoids universal CNAME promises and notes that root domains may require A/AAAA/ALIAS/flattening and confirmed production infrastructure.
36. **Health:** Connected and last-checked data are restrained, textual, and use stored state only.
37. **Degraded/fallback:** issue panel says content is safe and shows the resolver-backed Keryon fallback as official.
38. **Failure messages:** DNS absent/mismatch/timeout, certificate pending/failed, and provider unavailable map through `ChurchDomainStatusPresenter`; raw exceptions never render.
39. **Disable:** governed confirmation explains serving stops, content is unaffected, fallback remains, and history is preserved; action calls `DisableChurchDomain`.
40. **Release:** higher-consequence confirmation explains disconnection/history/30-day quarantine; action calls `ReleaseChurchDomain`.
41. **Quarantine:** released history explicitly shows the 30-day protection and remains non-actionable.
42. **History:** bounded “Previous domains” disclosure is implemented for released records. Detailed event timeline was intentionally omitted to keep the page focused; `ChurchDomainEvent` remains canonical evidence.

## Integrations and boundaries

43. **Website Overview:** now shows the effective public address with Visit and governance-aware Manage domains actions.
44. **Shell:** Go to Website remains visually unchanged and follows `ChurchPublicUrlResolver`; healthy-primary regression passes.
45. **Unpublished Website:** page remains available but states that domain configuration does not make the Website public.
46. **Publication:** no domain action invokes WebsitePublisher, creates WebsitePublication, or mutates working content; publication-count/current-publication assertions pass.
47. **Trust:** PublicationTrustGate and immutable publication rendering are untouched.
48. **Canonical/SEO:** 001B canonical/OG/JSON-LD/sitemap/robots behavior is unchanged and covered by full Domain/Website regressions.
49. **Entitlement:** current Website availability semantics are retained; no expiry/grace policy or record deletion was invented.
50. **Organization:** no Organization destination, read model, or authority path.
51. **Care:** zero Care models/queries/fields.
52. **Communications:** no workflow, provenance, calendar, or outcome changes.
53. **Lifecycle reuse:** claim, regeneration, verification job, primary, disable, and release all use 001B actions/services.
54. **Direct mutation audit:** no page/view lifecycle `update`, `forceFill`, state assignment, or browser-owned Church/actor/timestamp/TLS/primary input exists.

## Security and experience proof

55. **Token security:** DB contains SHA-256 only; raw value is absent after reload and from query strings, logs, events, local storage, and model fields; regeneration invalidates old value.
56. **Cross-Church:** domain lookup is both TenantContext-scoped and explicitly same-Church filtered; manipulated Church B ID fails closed.
57. **Suspended/removed:** loaded page actions re-resolve DB membership and deny both states.
58. **Primary transfer:** old Primary loses action authority immediately; new qualifying Primary Administrator succeeds.
59. **Accessibility:** semantic headings/lists/dl, labelled form/copy controls, adjacent errors, text plus color status, visible focus rings, keyboard actions, and meaningful native confirmations.
60. **Responsive:** core workflow uses cards and wrapping code containers, not tables; buttons stack at narrow widths.
61. **Desktop browser:** authenticated Chrome 1440×1000 proved empty, claim/token, healthy primary, confirmation, and exact 1440px document width.
62. **Mobile browser:** authenticated Chrome 390×844 proved returning DNS and degraded states with exact 390px document/scroll width.
63. **Long hostname/TXT:** deliberately long Church identity, `_keryon-verification.www.browser-proof.org`, and 64-character token wrapped without horizontal overflow; token copy remained reachable.
64. **Queries:** measured management render ceiling is 18 including Filament shell/navigation authorization. Two domains render in one domain query, no event N+1, and zero DNS checks on load.
65. **Migrations:** none.
66. **Packages:** none.

## Verification

67. **Focused tests:** `ChurchDomainManagementPageTest` PASS, 10 tests / 81 assertions; combined Domain/Website-management/Shell slice PASS, 62 tests / 381 assertions before final additions.
68. **Domain regression:** PASS in the required 518-test focused aggregate.
69. **Website regression:** PASS, including public marketing and ChurchWebsite runtime.
70. **Shell regression:** PASS; Go to Website and Organization exclusion preserved.
71. **Authorization/Tenancy:** PASS; stale, cross-Church, role, Primary, suspended, removed cases covered.
72. **Organization:** PASS; Organization-only actor denied.
73. **Care:** PASS; no domain data path.
74. **Trust:** PASS; publication/public-media boundaries unchanged.
75. **Communications:** PASS; no domain coupling.
76. **Full suite:** PASS, 1,046 tests / 3,456 assertions with PHP memory limit 1G.
77. **Build:** PASS, Vite 8.1.3 production build, 7 modules transformed.
78. **Pint:** PASS on all scoped PHP files.
79. **Diff checks:** working and cached `git diff --check` pass.
80. **Sensitive scan:** no raw token persistence, provider exception output, browser-owned authority fields, or direct lifecycle mutation found.
81. **Fake safety:** existing 001B constructor/binding tests plus 001C unavailable-environment test prove production cannot bind fake DNS/TLS or offer false activation.

## Files, repository, and closure

82. **Created:** `app/Domain/ChurchDomainStatusPresenter.php`; `app/Filament/Clusters/Website/Pages/ManageDomains.php`; Domains Blade view; claim-form partial; `tests/Feature/Domains/ChurchDomainManagementPageTest.php`.
83. **Modified:** `WebsiteOverview.php` and its Blade view only.
84. **Final Git status:** milestone implementation/report committed; pre-existing `.gitignore`, seeder/docs, `.agents`, `.claude/skills`, `AGENTS.md`, `skills-lock.json`, and `stubs/` remain unstaged. Exact output is in final handoff.
85. **Unrelated preservation:** no reset/clean/stash/pull/merge/rebase/broad add. Browser fixture and DB rows were isolated and cleaned exactly.
86. **Commit:** `71241c5 feat(domains): add Church domain management experience`; report commit hash is in final handoff.
87. **Push:** not performed.
88. **Production blockers:** approved DNS resolver, ingress/apex targets, TLS adapter/API/credentials, callbacks, wildcard DNS/TLS, and production activation proof remain 001D scope.
89. **Entitlement/grace:** lapsed subscription/trial/grace behavior remains Product Office policy; domain records remain preserved.
90. **Can 001C close?** Yes. Governance, truthful workflow, tokens, responsive UX, integrations, security, browser proof, regressions, and quality gates pass.
91. **Is 001D technically ready?** Yes. The product experience and provider-neutral seams are ready for controlled production DNS, ingress, TLS, monitoring, and credential proof.
