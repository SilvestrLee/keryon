# Public Publishing Trust Gate

**Milestone:** K-TRUST-007
**Date:** 29 August 2026
**Status:** Implemented foundation

## Canonical boundary

Content existence, editorial approval, and public exposure are separate states.
Keryon's public transition requires an authorized active-Church publisher,
tenant-correct structured content, publication-compatible assets, required AI
human review where provenance is known, and an explicit destination-specific
publish action.

`PublicationTrustGate` is the small reusable decision boundary. Its initial and
only implemented `PublicationDestination` is `church_website`. The gate
composes K-AUTH, `WebsitePublicSchema`, K-TRUST-006 `AssetRightsPolicy`, and
K-TRUST-004B rendition identity. It is not a workflow, scheduling, consent, or
destination framework.

## Church Website proof

The explicit Filament **Publish Website** action invokes `WebsitePublisher`.
Saving Website content, uploading media, approving Content/FaithFlow/Design,
or activating Campaigns does not invoke this boundary.

The flow is:

```text
WebsitePublish authorization
→ explicit structured snapshot
→ same-Church rights-compatible rendition preparation
→ private MediaAsset IDs removed from the public snapshot
→ PublicationTrustGate
→ immutable WebsitePublication + references
→ active WebsiteSettings pointer
→ anonymous public rendering
```

The database transaction creates the publication record/references and changes
the active pointer together. Failure preserves the old pointer and publication.
Unreferenced prepared rendition bytes are safely handled by the existing grace
cleanup seam.

## Public schema and Care

`WebsiteSnapshot` selects named fields rather than serializing models. The
executable `WebsitePublicSchema` validates the final snapshot and rejects extra
sections/fields. It includes only Church public identity/contact fields, brand
presentation, structured Website copy, leadership/ministry presentation,
service times/social links, and opaque public rendition UUIDs.

New snapshots contain no MediaAsset ID, UUID, disk, private path, User,
membership, congregation, Care, internal note, provider, or credential field.
Care cannot become a Website publication source. Historical snapshots retain
the K-TRUST-004B legacy dual-read path without rewrite.

## Evidence and immutability

`WebsitePublication` remains the destination-specific ledger. It now records:

- destination;
- Church and publisher;
- immutable structured snapshot;
- SHA-256 `working_fingerprint` over theme + normalized snapshot;
- previous active publication ID;
- publication time;
- bounded Trust evidence version, explicit-intent fact, schema version,
  content-authority basis, rights result, AI review status, rendition usage map,
  and evaluation time.

The associated `MediaPublicReference` records bind each publication/usage to a
same-Church rendition. Publication records reject application-level update and
delete. Existing historical records remain valid with nullable Trust evidence;
no historical decision is fabricated.

Current Website content has no durable AI provenance link, so evidence records
AI review as `unknown`, not `not_ai`. The explicit publisher action records
review for public exposure without pretending to establish provider history.
Future known AI content must provide `human_reviewed`; `unreviewed` fails closed.

## Unpublish, withdrawal, and health

Unpublish clears only the active Website pointer and deactivates publication
references. Working content, canonical private media, snapshots, publisher,
hash, Trust evidence, and historical references remain.

Anonymous rendition delivery now requires both an active rendition and an
active public reference. Thus unpublish immediately removes delivery authority
while the 24-hour cleanup grace safely retains bytes.

K-TRUST-006 restriction/dispute/withdrawal revokes the affected rendition and
references but does not rewrite the WebsitePublication or automatically take
the whole Website offline. The current policy is a **degraded publication**:
the page remains active and the unavailable image fails safely. A bounded
`WebsitePublicationHealth` service reports `healthy` or `degraded` plus usage
keys. Product Office may later choose whole-site auto-unpublish, but no such
policy is inferred here.

## Future destinations

Campaign planning, approved Content, FaithFlow output, generated Design, and
Design approval remain internal. Future social, Campaign public asset, AI
Design, motion, or scheduled publication must add a bounded destination and
revalidate authorization/Trust at execution. Provider/model metadata need not
be public, but durable AI provenance and human review must survive internally.

Marketplace and Keryon marketing remain separate boundaries: Marketplace is
private/gated source distribution with its own rights gate; marketing is
platform-owned static publication. Neither uses the Church Website contract.

## Legal concepts requiring counsel

Final wording remains required for Church authority to publish supplied text
and media, person/photo consent, AI-assisted public-content disclosures,
Church/Keryon responsibility allocation, and takedown/dispute consequences.
No legal copy or checkbox platform is implemented here.
