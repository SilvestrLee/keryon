# K-SHELL-001 — GLOBAL WORKSPACE HEADER & NAVIGATION INFRASTRUCTURE REPORT

1. **Executive result.** K-SHELL-001 is implemented and verified. Church and Organization panels now share one responsive Keryon header with governed workspace switching, capability-scoped database search, canonical public-Website access, and user-owned locale selection. Authority planes remain separate.
2. **Starting HEAD.** `339ea3b232554b55c4f02cb7187c4d6aea65fd2e` (accepted K-COMMS-001D state).
3. **Final HEAD.** The milestone commit containing this report (`HEAD`); its exact immutable SHA is recorded in the closeout handoff because embedding a commit's own SHA in its contents is self-referential.
4. **Shared shell architecture.** `KeryonWorkspaceHeader` is a shared Livewire component rendered from one Filament partial in both panels. `WorkspaceType`, `WorkspaceRegistry`, `GlobalSearchService`, `ChurchPublicUrlResolver`, and `UserLocaleResolver` keep responsibilities bounded.
5. **Church header implementation.** Shows current Church identity/type, switcher, Church-scoped search, public Website action when resolvable, language, and the existing Filament account affordance.
6. **Organization header implementation.** Uses the same component and visual grammar while resolving Organization identity, Organization-only providers, and no Website action.
7. **Future Central compatibility.** `WorkspaceType` includes a non-exposed `Central` type. Registry and switch routing expose only implemented Church/Organization destinations; no Central authority is fabricated.
8. **Workspace switcher architecture.** `WorkspaceRegistry` returns immutable `WorkspaceOption` values from active direct memberships. POST switches are server-resolved by `SwitchWorkspaceController`.
9. **Church workspace enumeration.** Active `ChurchMembership` plus active Church only; inactive, suspended, removed, or unrelated Churches are excluded.
10. **Organization workspace enumeration.** Active `OrganizationMembership` plus active Organization only; hierarchy visibility is not used.
11. **Multi-Church behavior.** All legitimate active memberships are grouped in the switcher. Unresolved multi-Church login redirects to the neutral `/workspaces` chooser for deliberate selection.
12. **Multi-Organization behavior.** Multiple direct active memberships are listed and require explicit selection.
13. **Church→Organization switch.** Revalidates the Organization membership, clears active Church state, marks the Organization authority plane, forgets both context singletons, and redirects to `/organization`.
14. **Organization→Church switch.** Revalidates the Church membership, clears active Organization state, marks the Church plane, forgets both contexts, and redirects to `/admin`.
15. **Context clearing/revalidation.** `active_workspace_type` is a session guardrail. `TenantContext` and `OrganizationContext` return null in the incompatible plane and middleware re-resolves DB truth.
16. **Livewire behavior.** The shared component derives its plane from the active Filament panel on each request. Server-side context guards cover refresh and Livewire requests; stale membership fails closed.
17. **Hierarchy-vs-membership separation.** Organization scope never creates a Church option. Tests prove a hierarchy-visible Church without ChurchMembership is absent.
18. **Global search architecture.** Bounded, database-backed explicit providers are orchestrated by `GlobalSearchService`; no global index or external engine was added.
19. **Search provider contract.** Providers declare eligibility for the current `WorkspaceType`, run an authorized bounded query, and return typed `SearchResult` values with canonical URLs.
20. **Church search domains implemented.** Content, Campaigns, Congregation, staff/access, Website, and capability-gated Care.
21. **Organization search domains implemented.** Organization Units and Churches as governance objects using SQL-bound `OrganizationScopeResolver` subqueries. No Church operational tables are searched.
22. **Care search isolation.** `CareView` is checked before provider execution. Unauthorized searches issue no `prayer_requests` query; authorized results contain only title and status, never body text.
23. **Entitlement behavior.** Website search and public Website resolution require Website product availability. Other providers retain their canonical capability checks.
24. **Search result model/grouping.** Results carry type, title, optional safe description, and canonical URL; the UI groups by result type.
25. **Query minimum/debounce.** Two-character minimum and 250 ms Livewire debounce.
26. **Result limits.** Four per provider and twelve combined, centrally configured.
27. **Keyboard/command-palette behavior.** `⌘K`/`Ctrl+K` opens search; focus moves into the dialog; Escape closes search and menus; controls remain native keyboard-operable.
28. **Mobile search.** Search becomes a full-width fixed surface; the persistent field collapses to a labelled trigger.
29. **Privacy/logging behavior.** Raw query text is not logged or persisted; no recent-search or analytics state was introduced.
30. **ChurchPublicUrlResolver.** A dedicated resolver checks active Church, Website entitlement, and a current publication, then delegates URL construction to canonical `PublicWebsiteUrl`.
31. **Current Keryon subdomain behavior.** Uses the existing first-party public Website route/host rules; no URL is assembled in Blade.
32. **Custom-domain future seam.** Future verified-domain precedence can replace resolver internals without changing the header. No arbitrary domain field is trusted today.
33. **Published/unpublished Website behavior.** Only a Church with `current_publication_id` produces an action. Unpublished Websites produce no broken destination.
34. **Organization Website-action exclusion.** Organization header browser and feature proofs show zero Website actions.
35. **New-tab/security behavior.** Public action uses `target="_blank"` and `rel="noopener noreferrer"`.
36. **Localization architecture.** `UserLocaleResolver`, `ApplyUserLocale`, bounded config, POST controller, and `lang/en/shell.php` establish the shell seam.
37. **User locale persistence.** Nullable `users.locale`; explicit preference persists to the User and session and survives workspace switches/login.
38. **Supported production locales.** English only, because it is the only approved complete catalogue. No incomplete language is advertised.
39. **Translation-resource architecture.** All new shared header, chooser, search, Website, and language strings resolve through `shell.php`; catalogue entries come from config.
40. **Hard-coded translation debt.** Future localization should inventory Dashboard, Communications, Content Studio, Campaigns, Website Studio, Congregation, Staff, Care, Design, Media, FaithFlow, Organization governance, settings, validation, notifications, and legacy Filament labels.
41. **Church/Website locale separation.** Locale writes only User/session state and never Content, Campaign, Website content, Church, or Organization.
42. **Timezone preservation.** Locale selection does not write or alter Church/application timezone; tests assert the Church timezone remains unchanged.
43. **Header visual architecture.** Context is left/central, search is prominent at desktop widths, and Website/language/account controls are compact on the right, using restrained ink/amber surfaces.
44. **Wordmark/logo treatment.** Existing approved Keryon symbol/wordmark remains in the panel shell; no competing identity asset was introduced.
45. **Desktop behavior.** At 1440×1000, full workspace identity, search field, Website, language, switcher, and account affordances fit without overflow.
46. **Tablet behavior.** At 900×900, search deliberately collapses to a trigger while context/actions remain usable; no horizontal overflow.
47. **Mobile behavior.** At 390×844, workspace and search stay in the top row; Website/language move into the compact actions menu; no horizontal overflow or squeezed controls.
48. **Accessibility.** Labelled buttons, native menu controls, dialog semantics, `aria-live`, result types, current state text, visible focus rings, focus trap, Escape close, and keyboard shortcut are present. Browser proof found no trapped/overflowed surface.
49. **Workspace-switch security.** Browser IDs are never trusted as authority: each switch re-queries an active direct membership and active workspace; manipulated IDs return 403.
50. **Search security.** Provider eligibility checks membership context and capability before query execution; canonical scoped models/resolvers are retained.
51. **Website URL security.** Only canonical first-party URL construction is used; no unverified custom/arbitrary URL can become the header target.
52. **Locale input security.** Input is validated against configured locale keys; arbitrary/path-like locales are rejected.
53. **Header query count.** Feature ceiling: base Church header render ≤10 queries, including both bounded membership lists and Website resolution.
54. **Search query count.** Feature ceiling: Church search interaction ≤22 total queries; ineligible providers do not run.
55. **N+1 proof.** Workspace relationships are eager-loaded; provider limits and labels are selected in bounded queries. Query-ceiling tests passed.
56. **Migration(s).** `2026_08_31_140000_add_locale_to_users_table` only.
57. **Indexes/defaults.** `locale` is nullable `varchar(12)`, default null, with no index because it is not queried as a lookup key.
58. **MySQL proof.** Applied successfully on MySQL 8.0.30 as batch 33; inspected column: `varchar(12)`, nullable, no key, null default.
59. **Exact files created.** `app/Enums/WorkspaceType.php`; controllers `SelectLocaleController.php`, `SwitchWorkspaceController.php`, `WorkspaceSelectionController.php`; `ApplyUserLocale.php`; `app/Livewire/KeryonWorkspaceHeader.php`; `app/Localization/UserLocaleResolver.php`; all 12 files under `app/Search/`; `app/Website/ChurchPublicUrlResolver.php`; both files under `app/Workspace/`; `config/keryon.php`; locale migration; `lang/en/shell.php`; `resources/css/filament/shell.css`; shared Filament/Livewire/chooser views; `tests/Feature/Shell/WorkspaceShellTest.php`.
60. **Exact files modified.** `SelectOrganization.php`, `EnsureUserHasChurch.php`, `ResolveOrganizationContext.php`, `User.php`, both Filament panel providers, `OrganizationContext.php`, `TenantContext.php`, `resources/css/app.css`, both panel theme CSS files, `routes/web.php`, and `OrganizationWorkspaceTest.php`.
61. **Packages.** None added or changed.
62. **Shell focused tests.** 10 tests / 41 assertions in the dedicated shell class; final combined shell/context run 47 tests / 140 assertions.
63. **Workspace switching tests.** Active-only enumeration, direct-membership separation, cross-plane clearing, manipulated IDs, and current identity pass.
64. **Church search tests.** Capability gating, Church isolation, grouping, canonical navigation, wildcard-safe ORM binding, and bounded limits pass.
65. **Organization search tests.** Unit/Church scope, no operational-table access, direct membership status, and SQL-bound scope pass.
66. **Care isolation tests.** DB listener proves no Care table query without `CareView`; full Care regression passes.
67. **Website URL tests.** Entitlement/current-publication requirements, null unavailable state, first-party resolution, Organization exclusion, and safe link attributes pass.
68. **Locale tests.** Default/explicit persistence, unsupported rejection, user isolation, Church/content/timezone non-mutation, and cross-workspace preservation pass.
69. **Identity/Tenancy regression.** Included in the 516-test focused regression and 1,017-test complete suite; green.
70. **Authorization/Care regression.** Included; green with fail-closed membership/capability behavior.
71. **Organization regression.** Included; updated accepted assertion confirms Organization selection clears stale Church session; green.
72. **Dashboard regression.** Included; existing composition and Care isolation remain intact; green.
73. **Communications regression.** Included; K-COMMS publication/outcome behavior remains green.
74. **Website regression.** Included; Website publication, provenance, trust, and public runtime remain green.
75. **Full-suite result.** PASS: 1,017 tests, 3,248 assertions, 66.757 seconds, `memory_limit=1G`.
76. **Build.** PASS: Vite 8.1.3 production build; 7 modules transformed, completed in 5.80 seconds.
77. **Pint.** PASS: scoped `./vendor/bin/pint --dirty`.
78. **Diff checks.** PASS: `git diff --check` and `git diff --cached --check`.
79. **Sensitive/privacy scan.** New search/shell paths contain no logging, raw SQL interpolation, global-scope bypass, prompt/body/private URL exposure, or query-history persistence. Care output is minimized.
80. **Desktop browser proof.** PASS at 1440×1000 for Church switcher/search/Website/language/long names/narrow results and Organization header. Evidence: `/private/tmp/k-shell-001-desktop-workspaces.png`, `-desktop-search.png`, `-desktop-organization.png`.
81. **Tablet browser proof.** PASS at 900×900 with deliberate compact search and zero horizontal overflow. Evidence: `/private/tmp/k-shell-001-tablet.png`.
82. **Mobile browser proof.** PASS at 390×844 with usable switcher/search/actions/account and zero horizontal overflow. Evidence: `/private/tmp/k-shell-001-mobile.png`.
83. **Keyboard accessibility proof.** Browser automation proved Meta/Ctrl+K opening, focused search, Escape closing, native focusable switch/language/Website controls, menu dismissal, and no focus trap. New-tab attributes were inspected.
84. **Final Git status.** Milestone paths are clean and committed. Remaining status is only pre-existing modified `.gitignore`, `database/seeders/DatabaseSeeder.php`, `docs/06-Engineering/Local_Development.md`, plus pre-existing untracked `.agents/`, `.claude/skills/`, `AGENTS.md`, `skills-lock.json`, and `stubs/`.
85. **Unrelated-state preservation.** All listed pre-existing state was left unstaged and unmodified by this milestone. Browser fixture records were removed and the local proof server stopped.
86. **Commit hashes/subjects.** Final `HEAD` — `feat(shell): add global workspace header infrastructure`; exact immutable SHA is recorded in the closeout handoff.
87. **Push confirmation.** No push performed.
88. **Product Office decisions still required.** Approve additional production locale catalogues before exposure; define K-DOMAIN verified-custom-domain precedence; decide whether future search domains/actions or Organization public sites merit separate milestones.
89. **Whether K-SHELL-001 can close.** Yes. Required architecture, security boundaries, responsive/browser proof, performance ceilings, migration verification, regression, build, and commit are complete.
90. **Recommended next milestone.** K-DOMAIN for verified custom-domain lifecycle and canonical-domain precedence, followed by a separately approved localization-coverage milestone.

## Design-skill influence

The repository's `design-taste-frontend` guidance led to an audit-first, restrained operational shell: one visual grammar, existing typography/amber tokens, minimal surfaces, responsive reduction instead of control compression, and no decorative redesign of Dashboard or Communications.
