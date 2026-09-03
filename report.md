# K-ORG-COMMS-001A — DOMAIN & PERSISTENCE IMPLEMENTATION REPORT

## Executive result

K-ORG-COMMS-001A is implemented and verified as a bounded, Organization-owned communications foundation. The implementation adds the approved three-table aggregate, immutable revisions and approved materials, scoped Organization authorization, explicit lifecycle services, low-volume governance audit events, and comprehensive security/regression coverage.

The implementation preserves the locked authority boundary: Organization communications never establish `TenantContext`, never manufacture Church authority, and do not create or depend on Church Campaign, Content Studio, Media, FaithFlow, Website, Care, congregation, financial, or attendance records.

No Organization authoring UI, distribution, assets, Church inbox/import, dashboard integration, notification transport, or Organization FaithFlow behavior was implemented.

**Close recommendation:** K-ORG-COMMS-001A can be classified **CLOSED**, subject to Product Office review of this uncommitted implementation.

## Repository and Git state

- Starting HEAD: `0e3626a23e39737047600dfa2d7083f0431fa26b`
- Final HEAD: `0e3626a23e39737047600dfa2d7083f0431fa26b`
- Repository changes: source, migrations, tests, and this governed rolling report only; no commit was created.
- Staging index: empty.
- Commit status: **not committed**, as directed.
- Push status: **not pushed**.
- Packages: none added or changed.
- Existing unrelated work remains present and unstaged: `.gitignore`; Congregation enum/resource changes; `DatabaseSeeder`; local-development documentation; agent/skill/governance/lock/stub files.

## Existing conventions confirmed

- Organization identity is modeled separately through `Organization`, `OrganizationMembership`, `OrganizationRoleAssignment`, `OrganizationUnit`, and closure-table-backed unit paths.
- `OrganizationContext` resolves an active Organization membership from the selected Organization workspace; it is independent of `TenantContext`.
- Scope authorization combines active membership, active role assignment, role capability, assigned Unit, and descendant resolution through `OrganizationScopeResolver`.
- Organization models use integer primary keys plus UUID stable external identity where needed; enum-backed strings, Eloquent casts, database transactions, row locks, action/domain services, and Laravel policies are established conventions.
- `OrganizationAuditEvent` uses Organization ownership, bounded event/subject enums, actor user attribution, optional Unit attribution, and JSON state metadata.
- Church Campaigns, Content Items, Media Assets, FaithFlow, Website content, Care, and congregation data are tenant-owned and remain outside Organization communications.

## Persistence foundation

### Migrations and tables added

Exactly the approved three migrations were added:

1. `2026_09_02_170000_create_organization_communications_table.php`
2. `2026_09_02_170010_create_organization_communication_revisions_table.php`
3. `2026_09_02_170020_create_organization_communication_materials_table.php`

Tables:

- `organization_communications`: stable Organization-owned root; UUID; Organization, governing Unit, and creator OrganizationMembership foreign keys; kind; root state; timestamps; soft deletion.
- `organization_communication_revisions`: ordered version-owned content and guidance; creator/submitter/reviewer/approver OrganizationMembership attribution; revision state and review timestamps.
- `organization_communication_materials`: typed canonical Organization-owned copy/resources attached only to a revision, with deterministic ordering.

No target, distribution, delivery, Organization asset, Church import, notification, or telemetry table was added.

### Indexes and constraints

- Unique root UUID.
- Unique `(organization_communication_id, version)` revision identity.
- Unique `(organization_communication_revision_id, sort_order)` material order.
- Root indexes for Organization/state/update time and governing Unit/state.
- Revision indexes for communication/state and review queue state/submission time.
- Material index for revision/type.
- Restrictive foreign keys protect Organization, Unit, and actor membership governance records.
- Revision/material ownership cascades only with physical parent deletion; root soft deletion remains the normal draft-deletion path.
- Application-level model invariants require Organization, governing Unit, and all actor memberships to belong to the same Organization.
- No nullable mixed owner, `church_id`, ContentItem foreign key, or MediaAsset foreign key exists.

Migration verification used a clean temporary SQLite database: all migrations applied, the three new tables were present, `migrate:rollback --step=3` removed exactly the new three migrations, and migration status returned them to pending. The temporary database was removed afterward.

## Domain models and lifecycle

### OrganizationCommunication

- Stable aggregate root owned by one Organization.
- Requires one governing Organization Unit and creator OrganizationMembership from that same Organization.
- Supports `communication` and `campaign` kinds; it has no relationship to the existing Church `Campaign` model.
- Server-managed identity, ownership, governing Unit, creator, kind, UUID, and state are guarded from mass assignment.
- Governing scope and stable identity are immutable after creation.
- Soft deletion is permitted only for a never-distributed Draft; force deletion and deletion of non-Draft roots are rejected.

Root lifecycle:

- `Draft`: authoring/review foundation; no distribution fact exists.
- `Active`: reserved for future 001C distribution evidence and cannot be entered in 001A.
- `Withdrawn`: reserved for an Active communication; no Draft-to-Withdrawn transition is allowed.
- `Closed`: terminal historical/inactive state; a Draft or future Active root may close.

Direct model attempts to claim `Active` from Draft fail. No transition in this phase manufactures distribution evidence.

### OrganizationCommunicationRevision

- Version-owned fields: title, summary/purpose, requested Church action, advisory adaptation policy, campaign start/end guidance, recommended response date, suggested publish-by date, availability start/end, lifecycle/review state, and actor/timestamp attribution.
- Revision identity is immutable.
- Approved and future Distributed revisions reject substantive field mutation.
- Date validation enforces campaign end on/after start and availability end on/after start.

Revision lifecycle:

- `Draft → In Review`
- `In Review → Changes Requested → Draft`
- `In Review → Approved`
- `Approved → Distributed` is reserved structurally for 001C; no distribution action exists in 001A.
- Invalid state transitions fail at both workflow and model invariant boundaries.

### Revision-number allocation and next-revision behavior

- New versions are allocated inside a database transaction while the communication root and source revision are locked.
- Database uniqueness on communication/version is the final concurrency invariant.
- An Approved or future Distributed revision can intentionally seed a new Draft revision.
- Canonical fields and ordered materials are copied; the approved source remains unchanged.
- Repeated next-revision creation produced ordered versions `1, 2, 3`; an attempted duplicate version failed at the database constraint.

### OrganizationCommunicationMaterial

- Materials are Organization-owned through `OrganizationCommunication → Revision → Material` only.
- Supported bounded types: general, announcement, social caption, devotional, prayer points, discussion questions, campaign copy, website copy, and WhatsApp/status copy.
- Material body is required and non-empty; optional title is normalized.
- Sort order is deterministic and unique within a revision; deletion compacts remaining positions.
- Create/update/delete operations are allowed only while the parent revision is Draft.
- Materials on Approved revisions are immutable.
- Material type is enum-constrained in domain actions and Eloquent casting.

### Adaptation policy and date semantics

Advisory adaptation vocabulary:

- Use as provided
- Local adaptation encouraged
- Local details required
- Reference material only

These values communicate Organization intent only. They grant no control over future Church-owned copies and implement no DRM. Reference-only import behavior remains deferred to the Church import milestone.

Dates are deliberately guidance-oriented: campaign start/end, recommended response date, and suggested publish-by date do not schedule or publish anything. Availability start/end is persisted for future acceptance/import rules; no acceptance behavior exists yet.

## Capabilities and role mappings

Added stable Organization capability vocabulary:

- `organization.communications.view`
- `organization.communications.create`
- `organization.communications.edit`
- `organization.communications.approve`
- `organization.communications.distribute`
- `organization.communications.withdraw`
- `organization.communications.deliveries.view`

Default mappings:

- Organization Administrator: all current Organization capabilities, including view/create/edit/approve/distribute/withdraw/delivery facts, within authorized scope.
- Unit Administrator: view/create/edit and future delivery facts within assigned Unit plus permitted descendants; no approve, distribute, or withdraw.
- Organization Viewer: view only.

Distribution and delivery capabilities are catalogue-only seams in 001A; they do not expose behavior.

## Action/service and policy architecture

- `OrganizationCommunicationAuthorizer`: re-resolves current `OrganizationContext` authority on every action and applies `OrganizationScopeResolver` capability checks to the governing Unit.
- `OrganizationCommunicationManager`: creates roots/revision 1, edits Drafts, manages ordered Draft materials, creates locked next revisions, and soft-deletes eligible Drafts.
- `OrganizationCommunicationWorkflow`: performs submit, request changes, return to Draft, approve, close, and truthful withdraw transitions.
- `OrganizationCommunicationAudit`: records bounded, low-volume governance evidence without communication bodies.
- Policies cover root, revision, and material view/mutation operations and repeat Organization ownership, current membership, capability, and Unit scope checks.

`OrganizationScopeResolver::hasCapabilityForUnit()` exposes the existing closure-table scope decision as a reusable domain seam; it does not introduce a second scope model.

All action inputs derive Organization ownership, governing authority, actor memberships, lifecycle state, revision version, approval timestamps, and audit identity on the server. Models are guarded against mass assignment.

## Authorization and persona results

- Organization Administrator: can create, submit, explicitly self-approve, request changes, close, and create a later Draft revision inside authorized scope.
- Unit Administrator: can create/edit/submit inside assigned Unit and descendants; cannot approve, distribute, withdraw, or act in a sibling Unit.
- Organization Viewer: can view authorized records but cannot mutate.
- Organization-only identity: works with no ChurchMembership and no `TenantContext`.
- Church-only identity: denied.
- Platform-only identity: denied.
- Sibling-scoped identity: direct policy and action access denied.
- Different-Organization identity: direct policy and action access denied.
- Suspended Organization membership: stale session authority revalidation denies the next action.
- Removed Organization membership: denied.
- Revoked role assignment: denied.
- Cross-Organization Unit/creator/actor association: rejected by authorization and ownership-chain invariants.
- Self-approval: explicitly allowed for a scoped Organization Administrator and records approver membership/time plus audit evidence.

## Audit and privacy

Low-volume `OrganizationAuditEvent` types were added for:

- communication created
- next revision created
- revision submitted
- changes requested
- revision approved
- communication withdrawn
- communication closed

Audit subjects distinguish communication roots and revisions. Events store factual lifecycle/version/kind data plus actor membership ID, actor user ID, Organization, and governing Unit. They do not store titles, summaries, requested actions, material bodies, or review feedback. Tests explicitly verified canonical communication text and change-request feedback are absent from audit JSON.

The high-volume delivery event/telemetry model remains deferred.

## Context and private-data boundaries

- `OrganizationContext` is mandatory and freshly resolved for every domain action.
- `TenantContext` is never established, consulted, or modified by the new domain.
- No fallback exists through `users.church_id`, ChurchMembership, selected Church, or customer-plane shortcuts.
- No dependency or foreign key targets Church Campaign, ContentItem, MediaAsset, FaithFlow, Website drafts, Prayer Requests, Care, congregation members, private Church notes, finance, or attendance.
- Focused tests verify creation leaves Church Campaign, ContentItem, MediaAsset, and ChurchMembership counts unchanged for an Organization-only actor.
- Source scan across new domain/actions/policies/migrations found no prohibited Church/Tenant/private-data dependency.

Church model changes: none.

Church Campaign changes: none.

Content Studio changes: none.

Media changes: none.

FaithFlow changes: none.

TenantContext changes: none.

## Deferred implementation status

- Distribution targets/batches/deliveries: **not implemented**; belongs to K-ORG-COMMS-001C.
- Organization communication assets: **not implemented**; belongs to K-ORG-COMMS-001B.
- Organization authoring/review UI: **not implemented**; belongs to K-ORG-COMMS-001B.
- Church inbox/receipt: **not implemented**.
- Church import/adaptation: **not implemented**; belongs to K-ORG-COMMS-001E.
- Notifications/email/SMS/WhatsApp/social transport: **not implemented**.
- Organization FaithFlow: **not implemented**.
- Dashboard integration: **not implemented**.
- Browser proof: not required because no user-visible surface changed.

## Verification results

### Focused and architecture tests

- K-ORG-COMMS-001A focused foundation: **11 tests, 82 assertions — PASS**.
- Focused foundation plus Organization scope authorization: **22 tests, 138 assertions — PASS**.
- Complete Organization feature directory: **51 tests, 349 assertions — PASS**.

Focused coverage includes lifecycle, approval, self-approval, changes requested, immutability, next revision, version uniqueness, materials, schema/foreign keys/indexes, role mappings, sibling scope, cross-Organization denial, stale memberships/roles, identity personas, context isolation, mass assignment, audit privacy, and Church-model exclusion.

### Regression groups

- Shell / Identity / Authorization / Tenancy: **175 tests, 619 assertions — PASS**.
- Content Studio / Campaigns / Communications: **149 tests, 511 assertions — PASS**.
- FaithFlow: **195 tests, 373 assertions — PASS**.
- Church Website / Trust / Central: **183 tests, 906 assertions — PASS**.
- Organization architecture/context/dashboard regression: covered by the 51-test Organization run and final full suite — **PASS**.
- Church authorization and TenantContext regression: covered by grouped and final full suite — **PASS**.
- Content Studio regression: **PASS**.
- Campaign regression: **PASS**.
- FaithFlow regression: **PASS**.
- Media regression: covered by grouped and final full suite — **PASS**.
- Central regression: **PASS**.
- Shell regression: **PASS**.

### Full suite

- Authoritative result: **1,122 tests, 4,072 assertions — PASS**.
- Accepted baseline: 1,111 tests / 3,990 assertions.
- Delta: +11 tests / +82 assertions; no unexplained reduction.

Two initial `php artisan test` attempts exhausted the existing 128 MB CLI limit while rendering the pre-existing Care Center `PrayerRequestResourceTest`; neither reported a test assertion failure. The authoritative full run used the direct PHPUnit runner with a 512 MB process limit and completed successfully without changing repository or application configuration.

### Build, style, syntax, and diff

- `npm run build`: **PASS** (Vite production build).
- Scoped Laravel Pint on every milestone PHP path: **PASS**.
- PHP syntax validation on every new enum/model/service/policy/migration/test: **PASS**.
- `git diff --check`: **PASS**, no whitespace errors.
- Migration fresh/rollback proof: **PASS**.
- Package files: unchanged.

## Security review

- IDOR/cross-Organization: policy and action-level ownership/scope checks deny foreign IDs.
- Scope bypass: governing Unit is freshly resolved and checked through closure-table-backed `OrganizationScopeResolver`.
- Stale membership/role: `OrganizationContext` cache is cleared and membership/role state is re-resolved before each action.
- Unauthorized transitions: explicit workflow guards plus model lifecycle invariants reject invalid transitions.
- Approved mutation: canonical revision fields and materials cannot mutate after approval; edits require a new Draft revision.
- Direct Active claim: rejected until a future distribution action provides the factual basis.
- Mass assignment: ownership, authority, actor, state, version, approval, and audit fields are guarded/server-managed.
- Cross-Organization association: model ownership-chain validation rejects foreign Unit and membership attribution.
- Tenant leakage: no TenantContext dependency and Organization-only operation is tested.
- Sensitive-data leakage: source scan and audit-content assertions pass.

## Performance review

- Root/revision creation and lifecycle transitions use bounded queries inside short transactions.
- Revision allocation locks the aggregate root and relies on a unique database invariant.
- Materials use deterministic database ordering.
- Relationships are explicit and support eager loading; the domain does not recursively traverse hierarchy in PHP.
- Authorization delegates descendant checks to the existing closure-table resolver.
- No delivery fan-out or 10,000-recipient work exists in this phase, so queues/batching are correctly deferred.
- No obvious N+1 path was introduced into a user-facing surface because no UI/query listing surface was added.

## Exact milestone file set

Modified:

- `app/Enums/OrganizationAuditEventType.php`
- `app/Enums/OrganizationAuditSubjectType.php`
- `app/Enums/OrganizationCapability.php`
- `app/Enums/OrganizationRole.php`
- `app/Models/Organization.php`
- `app/Models/OrganizationMembership.php`
- `app/Models/OrganizationUnit.php`
- `app/Organizations/OrganizationScopeResolver.php`
- `tests/Feature/Organization/OrganizationScopeAuthorizationTest.php`
- `report.md`

Added:

- `app/Enums/OrganizationCommunicationAdaptationPolicy.php`
- `app/Enums/OrganizationCommunicationKind.php`
- `app/Enums/OrganizationCommunicationMaterialType.php`
- `app/Enums/OrganizationCommunicationRevisionState.php`
- `app/Enums/OrganizationCommunicationState.php`
- `app/Models/OrganizationCommunication.php`
- `app/Models/OrganizationCommunicationMaterial.php`
- `app/Models/OrganizationCommunicationRevision.php`
- `app/Organizations/Communications/OrganizationCommunicationAudit.php`
- `app/Organizations/Communications/OrganizationCommunicationAuthorizer.php`
- `app/Organizations/Communications/OrganizationCommunicationManager.php`
- `app/Organizations/Communications/OrganizationCommunicationWorkflow.php`
- `app/Policies/OrganizationCommunicationMaterialPolicy.php`
- `app/Policies/OrganizationCommunicationPolicy.php`
- `app/Policies/OrganizationCommunicationRevisionPolicy.php`
- `database/migrations/2026_09_02_170000_create_organization_communications_table.php`
- `database/migrations/2026_09_02_170010_create_organization_communication_revisions_table.php`
- `database/migrations/2026_09_02_170020_create_organization_communication_materials_table.php`
- `tests/Feature/Organization/OrganizationCommunicationFoundationTest.php`

## Deviations and stop conditions

- No material deviation from the approved three-table boundary.
- No distribution/target/delivery persistence was added.
- No Church ownership model was altered.
- No polymorphic ownership was introduced.
- No package was installed.
- No Organization architecture refactor was required.
- No stop condition was encountered.

## Closure and recommended next step

K-ORG-COMMS-001A satisfies its domain, persistence, authorization, lifecycle, audit, isolation, migration, and regression criteria and **can close** after Product Office review.

Recommended next step: authorize **K-ORG-COMMS-001B — Organization authoring, review UX, and Organization-owned communication assets**. That phase should consume this domain service boundary, keep approval explicit, avoid exposing unfinished distribution navigation, and must not weaken Church ownership or tenancy protections.
