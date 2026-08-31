# K-COMMS-001D — Governed Publication Execution, Attribution & Outcome Evidence

## Milestone result

Implemented from required baseline HEAD `92c28e8a9e02ce09221ef7331b5978e876486a2a` after inspecting the dirty worktree and preserving all pre-existing unrelated changes. K-COMMS-001D now connects explicit Website publication to Communications Calendar completion through immutable, Church-scoped Website draft lineage. No scheduler, queue, target-time listener, Communications publishing service, or completion shortcut was introduced.

K-COMMS-001 can close as a sprint: planning, approved Content, Website draft handoff, Website adaptation, explicit human publication, canonical WebsitePublication evidence, attributable lineage, and factual Calendar outcome are now connected with the locked boundaries intact.

## Exact attribution mechanism

- Added `website_publication_provenances`, an immutable bounded join from canonical `WebsitePublication` to canonical `WebsiteContentProvenance`.
- Each row carries only `church_id`, `website_publication_id`, `website_content_provenance_id`, and timestamps. It contains no Content body, prompt, Care data, private Media URL, or duplicated Website snapshot.
- `WebsitePublisher` remains the sole publication boundary. Inside its existing publication transaction, after the trust gate accepts and the immutable `WebsitePublication` is created, it resolves the latest provenance row for each `(church_id, destination, website_record_id)` working record and creates the join evidence.
- A unique `(website_publication_id, website_content_provenance_id)` index prevents duplicate evidence for one publication. Model guards reject mutation/deletion and verify publication, provenance, and attribution share the same Church.
- If attribution creation fails, the whole publication transaction rolls back, including `WebsitePublication` creation and `WebsiteSettings.current_publication_id`; the Calendar therefore cannot claim completion from incomplete evidence.
- Standalone Website publication remains valid with zero provenance rows. Standalone Content Studio → Website handoff remains valid with provenance lacking a CampaignCommunication.
- Website editor divergence after handoff does not erase lineage. The published snapshot contains the adapted Website state while the join records the contributing handoff provenance; attribution is lineage, not byte equality.

## Calendar state model

Stored deliberate state:

- `CampaignCommunication.cancelled_at` remains the existing bounded human cancellation evidence.
- No generic status engine or mutable outcome column was added.

Derived state:

- **Published / completed**: Website channel only, and only when its `WebsiteContentProvenance` has a joined real `WebsitePublication`.
- **Superseded**: an unpublished handoff provenance has a newer provenance for the same Church/destination/working record. The deliberate replacement handoff is the evidence.
- **Overdue / not executed**: `target_at` is past and no higher-priority cancellation, publication, or supersession evidence exists.
- **Due**: target is today and not yet past.
- **Ready**: canonical Content readiness is prepared/approved, but no execution evidence exists.
- **Planned**: remaining planned work.
- **Cancelled** takes precedence and never masquerades as successful execution, even if historical publication evidence exists.

`target_at` remains planning intent. Reaching or passing it performs no mutation and never invokes `WebsitePublisher`.

## UX outcome

- Calendar entries now carry separate preparation and factual outcome badges.
- A summary immediately reports items needing attention, ready-but-unexecuted items, and published items.
- Overdue language states that no attributable execution was recorded and never implies that missing a target published or failed a publishing job.
- Completed Website entries link to Website publication/source evidence.
- Website Overview source cards show optional Campaign/Communication lineage and whether the source was included in a publication. Ordinary Website publishing does not require Communications.

## Security, authorization, and isolation

- Browser input never supplies Church, provenance, or publication ownership to the attribution writer; all are resolved server-side from `TenantContext`, the current Publisher transaction, and scoped provenance queries.
- `WebsitePublisher`, `PublicationTrustGate`, `WebsitePublish`, tenant scoping, and existing Media/public-rendition handling remain canonical and unchanged in authority.
- `WebsiteContentManage` still does not imply `WebsitePublish`; the existing capability and authorization suites cover inactive/suspended memberships, Organization membership non-authority, and Care role isolation.
- Calendar evidence relationships remain under `BelongsToChurch`; cross-Church model creation is rejected and another Church’s publication cannot satisfy scoped attribution.
- Care models, tables, fields, and data do not enter the publication/provenance/Calendar path.
- Repository inspection found no scheduled command, job, cron seam, or queue path invoking `WebsitePublisher`; the only application publication action remains the explicit Website UI action.

## Migration and indexes

Migration: `2026_08_31_130000_create_website_publication_provenances_table.php`.

Verified on local MySQL `8.0.30`:

- migration completed successfully;
- unique BTREE `web_publication_provenance_uq (website_publication_id, website_content_provenance_id)`;
- bounded lookup BTREE `web_publication_lineage_idx (church_id, website_content_provenance_id, website_publication_id)`;
- explicit FKs `web_pub_prov_publication_fk` and `web_pub_prov_lineage_fk` plus Church FK;
- explicit shortened names avoid MySQL’s 64-character identifier limit.

## Query impact

Calendar attribution is eager loaded in bounded relationship queries; no per-row publication or provenance query was introduced. The query ceiling test renders six rows at no more than 24 queries. Publication lineage adds two bounded eager-load queries independent of row count. Supersession uses one bounded eager relationship with an indexed anti-successor predicate.

## Critical negative proof

`test_past_target_with_approved_prepared_draft_never_publishes_and_is_overdue` establishes:

- Website Communication target is in the past;
- Content is approved;
- Website draft and provenance exist;
- Calendar says `Overdue / not executed` and `No attributable execution was recorded`;
- `website_publications` count is zero;
- `WebsiteSettings.current_publication_id` remains null.

No scheduled publication path exists, so time passage cannot change that result.

## Test coverage and verification

New focused coverage proves:

- attributable explicit publication completes the Calendar;
- unrelated publication does not complete a Communication;
- cross-Church/browser-supplied IDs cannot manufacture attribution;
- standalone Content → Website provenance and standalone publication remain valid;
- diverged Website working copy preserves historical provenance and attribution;
- later replacement handoff derives superseded without success;
- cancellation takes precedence over historical success evidence;
- repeated explicit publications do not duplicate evidence within a publication;
- attribution failure rolls back publication and outcome evidence;
- target time passage never publishes.

Existing regression coverage additionally proves inactive/suspended membership fail-closed behavior, WebsiteContentManage/WebsitePublish separation, Organization non-authority, Media rights, Design/Content approval, Trust, Tenant, Authorization, and Care isolation.

Verification results:

- Focused Communications/Website: **24 passed, 76 assertions**.
- Required Communications, Website, ChurchWebsite, ContentStudio, Campaigns, FaithFlow, Design, Trust, Authorization, Tenancy, CareCenter, Organization, Dashboard regressions: **770 passed, 2,382 assertions**.
- Complete suite: **1,007 passed, 3,207 assertions** (run directly with PHP `memory_limit=1G`; the default 128 MB runner otherwise exhausts memory in the pre-existing large Media MIME fixture).
- Scoped Pint: passed; formatting fixes applied.
- Vite production build: passed (`vite v8.1.3`, 7 modules transformed).
- `git diff --check`: passed.
- MySQL 8.0.30 migration/index/FK verification: passed.

## Browser evidence

Authenticated Chrome proof used an isolated temporary Church/member fixture, removed after capture:

- Desktop `1440×1000`: overdue and published outcomes visible, attention summary visible, no horizontal overflow.
- Mobile `390×844`: same factual states and actions visible in a single-column layout, no horizontal overflow.
- Temporary screenshots: `/private/tmp/k-comms-001d-desktop.png` and `/private/tmp/k-comms-001d-mobile.png`.

## Final repository state

The K-COMMS-001D milestone is committed independently. No push was performed. Pre-existing unrelated worktree changes in `.gitignore`, `database/seeders/DatabaseSeeder.php`, `docs/06-Engineering/Local_Development.md`, `.agents/`, `.claude/skills/`, `AGENTS.md`, `skills-lock.json`, and `stubs/` were intentionally excluded from the milestone commit.
