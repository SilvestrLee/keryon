# Keryon Trust Architecture & Product Invariants

**Milestone:** K-TRUST-001B
**Status:** Approved architecture
**Date:** 29 August 2026
**Authority:** Keryon Product Office

This document governs whether Keryon may process, transfer, generate from,
publish, retain, or redistribute information and content. It does not replace
K-AUTH: identity, active ChurchMembership, TenantContext, capabilities,
same-Church ownership, and fail-closed authorization remain authoritative.

## 1. Data classification

Keryon uses five lifecycle-aware classifications:

- **PUBLIC:** intentionally approved for public exposure.
- **INTERNAL:** operational information not intended for public exposure.
- **PERSONAL:** information relating to an identifiable person.
- **SENSITIVE:** information whose context or misuse creates heightened harm.
- **HIGHLY_RESTRICTED:** information confined to a tightly constrained
  security and processing boundary.

Published Church identity and Website content are PUBLIC. Drafts and Campaign
planning are INTERNAL. Accounts, contact details, memberships, and attribution
actors are PERSONAL. Congregation data, FaithFlow source material, unpublished
photographs, internal communications, and Design reference media are SENSITIVE.
Prayer/Care content, authentication secrets, and private Marketplace source
packages are HIGHLY_RESTRICTED.

Classification may change only through an explicit lifecycle action. An
unpublished Church asset is SENSITIVE; an intentionally approved public
rendition may become PUBLIC.

## 2. Care boundary

Care data is HIGHLY_RESTRICTED. This includes prayer request text, pastoral or
Care notes, follow-up content, and requester contact details in Care context.
It must not automatically flow to another Keryon domain.

Care data must not be sent to an AI provider. No exception is approved.
AI experiences must state the prohibition. Known Care-origin data must be
denied by server-side request construction where provenance is available.
Bounded warning/attestation and proportionate rejection evidence are permitted.
Free-text detection is not treated as complete or reliable.

Any future exception requires a separate Product Office decision covering
purpose, minimization, provider, disclosure, retention, training, legal basis,
human review, deletion, and audit evidence. There is no generic bypass flag.

## 3. AI data and processor boundary

AI eligibility by data class is:

| Class | Rule |
|---|---|
| PUBLIC | Eligible where the approved capability permits it |
| INTERNAL | Eligible only in an explicitly supported workflow |
| PERSONAL | Restricted; requires purpose, minimization, and approved provider |
| SENSITIVE | Restricted to an explicitly approved workflow |
| HIGHLY_RESTRICTED | Prohibited by default |

A configured adapter or credential is not processor approval. Unknown terms
mean the processor is not approved for production customer data.

Before production use, Keryon must approve the provider/legal entity,
capability and purpose, data classes, region, retention, training/use policy,
deletion mechanism, subprocessor role, contract/DPA position where applicable,
disclosure requirement, models/configuration, reviewer, and review date.

FaithFlow's technical AI integration is implemented. Its production processor
approval is **pending Trust and legal review**. This record does not disable
FaithFlow or remove provider configuration.

Users must be able to understand when content is AI-assisted or generated.
Keryon preserves internal provider, model, generation time, source workflow,
and human-approval provenance without unnecessarily exposing provider internals.
AI generation is not publication. Human review remains required before
AI-generated content enters a publishing workflow.

## 4. Media privacy and public renditions

Canonical Church-uploaded originals are private by default. Existence,
Design approval, generation completion, or Campaign association does not make
an asset public.

The target boundary is:

```text
private canonical original
    -> explicit approved public use
    -> public rendition or controlled public delivery
```

K-TRUST-004B implements the additive transition foundation. New canonical
Church uploads, private staging files, and new Design output MediaAssets use
the logical `media-private` disk and record SHA-256. Existing `disk=public`
rows remain valid dual-read legacy records and are not moved or rewritten.

Website publication copies validated canonical raster bytes to an opaque
`media-public` rendition, records its source and rendition hashes, and stores
rendition UUIDs in the immutable snapshot's `public_media` usage map. Active
bounded `website_publication` references authorize anonymous delivery. An
unpublish deactivates only that publication's references; canonical originals
and historical snapshots remain. Unreferenced renditions become eligible for
idempotent cleanup after the configurable 24-hour grace period. New public
rendering never requires a private path or private MediaAsset UUID.

The transition deliberately retains legacy snapshot resolution. K-TRUST-004C
must inventory deployed environments, backfill hashes/renditions, decide
legacy URL compatibility from real evidence, and migrate only verified rows.
No bulk file movement or legacy cleanup is part of K-TRUST-004B.

## 5. Asset rights

Upload possession does not establish public-publishing, AI-reference,
training, derivative-generation, redistribution, commercial-use, or
attribution rights.

The approved conceptual rights contract is:

- rights source and status;
- rightsholder;
- reference, training, generation, public-publish, redistribution, and
  commercial-use decisions;
- attribution requirement and text;
- licence identifier and version;
- verifier and verification time;
- expiry or scope where applicable.

Rights states are `pending`, `verified`, `rejected`, and `revoked`.
Pending is not permission. Exact persistence/normalization is an Engineering
decision for a separately authorized milestone.

Identifiable-person images require source/provenance, subject/identity-image
classification, AI-reference permission, generation permission, and
public-publishing permission. Upload does not imply consent, and Keryon will
not build biometric identification for this purpose.

## 6. Marketplace and font rights

A Marketplace source cannot become available or published until required
rights are verified: creator/rightsholder, redistribution authority, licence,
font dependency and bundled-font clearance, source provenance, verifier, and
verification time. Technical validation is insufficient.

The Sunday Service product is a valid technical proof but is not
production-rights-cleared. It remains preserved and must not be treated as
release-ready until creator, rightsholder, licence, and font facts are resolved.

Gilroy remains approved only for the recorded Keryon application/marketing
scope. It is not automatically approved for Church Website themes,
Marketplace PSD packages, or third-party redistribution. A generalized Font
Rights Registry is deferred until licensed-asset breadth warrants it.

Design Intelligence/Canon activation is blocked until machine-readable
`reference_allowed`, `training_allowed`, `generation_allowed`, and
`redistribution_allowed` decisions exist independently.

## 7. Public publication

Authorization to publish does not establish permission to publish underlying
content. Future public workflows must use a bounded attestation covering:

- publishing authority;
- intentional public exposure;
- personal/sensitive-information review;
- media/person/content rights;
- human review of AI-assisted content where applicable.

This is not a generalized consent engine. Immutable Website publication
snapshots remain the correct historical model and must be preserved.

## 8. Retention, deletion, logs, and queues

Keryon does not retain data indefinitely merely because deletion is absent.
Each major class needs a documented operational, customer-controlled,
audit/legal, or security basis. No arbitrary duration is approved here.

Deletion distinguishes logical deletion, physical erasure, audit preservation,
and legal/operational hold. Soft deletion is not erasure. Database and file
deletion must be coordinated.

Care retention requires explicit Product Office/legal treatment of Church
control, deletion, recovery, backups, audit, and holds before broad production.
Marketplace acquisition/download evidence is not casually deletable; closure
must address minimization or pseudonymization and legitimate licensing,
financial, and audit retention.

Sensitive and HIGHLY_RESTRICTED content must not intentionally enter general
logs. Future controls include central field redaction, safe exception
reporting, bounded failed-job retention, and evidence of production log
channels/retention.

Queue payloads should contain IDs, scoped references, and bounded metadata
instead of raw sensitive content. FaithFlow's ID-oriented payload is the
preferred pattern.

Account/Church closure is P1 and must reconcile database data, Media files,
Care, Website/AI data, Marketplace acquisitions, immutable evidence, and
licence records.

## 9. Processor Registry

The approved conceptual registry records only actually reviewed processors:

- provider and legal entity;
- capability, purpose, approved data classes;
- region and transfer basis where applicable;
- retention, training policy, deletion mechanism;
- subprocessor status and disclosure requirement;
- contract reference;
- approval state;
- reviewer and review date;
- configuration constraints.

States are `discovered`, `under_review`, `approved`, `restricted`,
`rejected`, and `retired`. Credentials do not imply approval.

Adding any SDK/processor that receives customer information triggers Trust
review, including AI, storage, analytics, error monitoring, communications,
payments, and motion generation.

The current no-nonessential-tracker baseline is preserved. Future analytics,
pixels, replay, or advertising triggers cookie/storage, disclosure, consent,
and Church-public-site review. No cookie-consent product is authorized now.

## 10. Product-specific future requirements

- Communications should receive only the minimum Congregation projection
  needed for its workflow. K-AUTH capabilities are unchanged.
- AI Design is blocked on person/reference asset rights and approved processor
  governance.
- Motion graphics triggers review of image/video/music/voice/synthetic-voice,
  provider, person, and publication rights.
- Premium Marketplace, trackers, formal data requests/exports, and regional
  transfers remain capability-triggered.
- Legal documents must describe implemented product truth and require external
  counsel; this architecture supplies facts, not legal conclusions.

## 11. Capability Compliance Gate

Every material Product Office directive answers:

1. Does it collect new data?
2. Expose existing data differently?
3. Introduce a processor?
4. Send data to AI?
5. Generate content?
6. Accept user-generated content?
7. Publish publicly?
8. Change retention/deletion?
9. Change rights, consent, or jurisdiction exposure?

For every **yes** or **unknown**, record the affected class, Trust concern,
current safeguard, required decision, and whether it blocks release. This is a
governance checklist, not software.

## 12. Priority and implementation sequence

**P0:** private originals/public renditions; Care→AI prohibition; AI class
boundary; Marketplace verified-rights publication gate.

**P1:** processor approval; retention/deletion; log/error redaction; Website
publication attestation; account/Church closure; Congregation minimization;
production cookie/security evidence; legal documents.

**P2:** AI Design rights; Design Intelligence; motion; Premium Marketplace;
trackers/consent; formal export where required; regional transfers.

**P3:** generalized font registry; enterprise Trust Center/exports;
sophisticated consent; third-party Marketplace seller governance.

Recommended milestones:

1. **K-TRUST-002 — Marketplace Rights Publication Gate**
2. **K-TRUST-003 — Care / AI Boundary Guard**
3. **K-TRUST-004 — Private Media Architecture**
4. **K-TRUST-005 — Processor Registry + AI Production Approval**
5. **K-TRUST-006 — Retention / Deletion Baseline**
6. **K-TRUST-007 — Public Publishing Trust Gate**

K-TRUST-002 is first because it is the smallest high-leverage correction to
an existing release boundary. No subsequent milestone is authorized by this
record.

### K-TRUST-002 enforcement record

K-TRUST-002 implements the Marketplace-specific gate without creating the
future application-wide rights model. Legacy sources default to `pending`.
Pending, rejected, and revoked sources fail closed for availability,
publication, acquisition, and all future delivery. Historical acquisitions and
issued downloads remain immutable evidence and do not create a perpetual
re-download right. Verification uses explicit evidence and a bounded interim
`platform_operator` reference until Central operator identity is available.

## 13. External legal review

Future counsel briefing must cover controller/processor roles, applicable
jurisdictions, religious/pastoral information, minors, lawful basis, rights,
international transfers, subprocessors, AI use/disclosure, copyright,
photos/person rights, Marketplace licences, font/stock assets,
takedown/DMCA, retention/deletion/holds, and Terms/Privacy/DPA/AUP.
No jurisdiction-specific legal conclusion is made here.
## 14. K-TRUST-003 executable AI-processing boundary

K-TRUST-003 implements the first executable boundary without a general policy
engine or consent platform. FaithFlow is the only current external AI surface.
Every provider call now centrally requires a valid active TenantContext for the
source Church, `faithflow.use`, eligible data, and a registry-approved provider,
capability, model, classification, and payload type. Region, retention,
training, deletion, review-date, and evidence facts must also be resolved.

Unknown providers, models, capabilities, and governance facts fail closed.
Provider/model selection remains Keryon configuration, never tenant input.
Credentials do not confer approval and no provider fallback occurs.

Anthropic defaults to `under_review` with unknown facts. FaithFlow remains
technically integrated but is not approved for production customer data until
deployment supplies Product Office-reviewed evidence. Tests use explicit
test-only approval facts; this is not production approval.

FaithFlow input and canonical analysis are SENSITIVE. Media is not approved.
Known exact PrayerRequest content is rejected before normal UI persistence and
again before transmission. Care origin and HIGHLY_RESTRICTED classification are
categorically rejected regardless of role or Primary status. Exact matching
does not claim to detect every manually copied excerpt, so the categorical rule
and a future UX warning remain necessary.

Denials use bounded messages and metadata-only `processing_denied` evidence and
are not retried. Raw Care text, prompts, responses, credentials, and headers are
not written to denial evidence. Existing ID-only queue payloads, execution-time
capability validation, generation provenance, and human approval are preserved.

Future AI Design, Design Intelligence, and motion processing must consume this
boundary and add separately approved capability, rights, payload-type, and
provider records. Roadmap or adapter presence is not approval.

## 15. K-TRUST-005 Anthropic processor review

The current evidence review is recorded in
`Anthropic_FaithFlow_Processor_Review.md`. `claude-sonnet-5` is an authoritative
current pinned Claude API model identifier, but provider approval remains
`under_review`. Standard API retention, safety exceptions, multi-region
processing/US storage, and commercial no-training terms are documented;
Keryon's actual contracting entity, Commercial Terms/DPA acceptance, account
retention/routing settings, and legal review are not established.

The registry now requires those account-specific facts before either `approved`
or `restricted` can execute. Test approval remains synthetic and isolated.
Production FaithFlow customer processing remains blocked; Care,
HIGHLY_RESTRICTED data, media, files, tools, connectors, AI Design, motion, and
unreviewed models remain prohibited.
