# Private Design Marketplace Architecture

## Rights publication gate

K-TRUST-002 makes Marketplace technical validation and rights clearance
independent, mandatory conditions:

```text
valid source + verified rights + valid Marketplace/tenant/entitlement state
= eligible for availability, publication, acquisition, and delivery
```

Pending, rejected, or revoked rights deny every future acquisition and
download, including re-download through a historical acquisition. Existing
acquisition and issued-download evidence is preserved. A legacy published item
without explicit verification is migrated to `pending` and `unpublished`;
its historical `published_at` is retained.

`ReviewMarketplaceSourceRights` is the only application action that records
verification, rejection, or revocation. Verification requires explicit
creator, rightsholder, licence, redistribution, font, provenance/evidence, and
bounded interim `platform_operator` attribution. The interim reference is
not a second identity system and must yield to canonical Central operator
identity for new reviews when that architecture exists.

Technical source withdrawal remains distinct from rights revocation. Neither
technical possession, validation, checksum success, previous publication,
acquisition, nor download is evidence of redistribution rights. The Sunday
Service reference product remains a technically valid proof with rights
`pending` until genuine evidence is supplied.

## Trusted PSD ingestion contract

K-MARKET-002 establishes a reusable, operator-controlled ingestion path. A trusted publisher supplies a real PSD and a genuine raster preview. `MarketplacePsdInspector` validates the file boundary without pretending to be a Photoshop parser: readable regular file, `.psd` extension, configured size ceiling, `8BPS` signature, bounded PSD header fields, byte size, and SHA-256. Header inspection does not prove layer editability, font licensing, linked-asset integrity, or visual quality.

`MarketplacePackageBuilder` constructs a deterministic, small allowlisted ZIP containing only the root files `Sunday-Service.psd` and `README.txt`. It rejects absolute, nested, traversal, drive-qualified, and non-allowlisted names and checks entry count, uncompressed size, and expansion ratio. The registered immutable `MarketplaceSourceVersion` is the customer-deliverable ZIP; the original PSD retains its own filename, header facts, byte size, and SHA-256 inside bounded compatibility metadata. Both identities are therefore independently verifiable without adding a second public or tenant asset system.

`MarketplaceReferenceProductImporter` is the temporary trusted operator interface used by the repository-owned `marketplace:import-sunday-service` command. It is idempotent by stable item slug and original PSD SHA-256, creates a replacement version only for changed source bytes, and registers private previews and source bytes. Technical success leaves rights pending and does not make the source available or publish the item. A separately attributed rights review and source/publication transition are required. A future Central publisher must call the same application services rather than introduce a second ingestion implementation.

Font dependencies are publisher-declared facts. Font files are never bundled merely because a PSD references them; absent declarations remain `pending_publisher_declaration`. Creator, rightsholder, redistribution authority, and licence reference likewise remain explicitly pending when not supplied. Engineering must not infer legal rights.

Previews are genuine supplied exports or visually inspected outputs from already-approved local tooling. They are stored as private platform assets with opaque keys, checksums, dimensions, alt text, and gated delivery. The PSD itself is never used as a browser preview.

Secure delivery continues through the K-MARKET-001 acquisition and delivery services: authenticated active Church context, `DesignsManage`, entitlement recheck, Church-owned acquisition, current published/available source, immediate checksum verification, private streaming, and append-only download audit. The useful customer filename is independent from the opaque storage key. Editing remains entirely external to Keryon.

**Milestone:** K-MARKET-001
**Date:** 22 August 2026
**Status:** Implemented domain foundation

## Boundary

Keryon's Design Marketplace is a private Keryon-Church capability for
controlled distribution of professional source packages edited outside
Keryon. It is not AI Design, tenant Design, Institutional Media, a template
registry, a public marketplace, or an in-browser editor.

Platform-owned global records are `MarketplaceCategory`, `MarketplaceItem`,
`MarketplacePreview`, and immutable `MarketplaceSourceVersion`. They have no
`church_id` and do not use `BelongsToChurch`. Global ownership does not make
them public: all Church-workspace catalogue reads go through
`MarketplaceCatalogue`, which requires an authenticated active membership,
valid `TenantContext`, and `designs.view`.

`MarketplaceAcquisition` is Church-owned and globally scoped through
`BelongsToChurch`. It records the exact source version, access classification,
entitlement basis, trusted acquiring user, and time. One Church may hold only
one acquisition per item; repeated acquisition is idempotent and retains the
original acquired version. Version-upgrade rights remain a future licensing
decision.

`MarketplaceDownload` is append-only Church/user audit evidence. It never
grants entitlement. It records the acquisition/source version, trusted actor,
issued time, safe outcome, and bounded failure category without IP address or
device fingerprint.

## Authorization and entitlement

The fixed v1 authorization rules are:

```text
authenticated active Church context + designs.view
  → view published private catalogue

authenticated active Church context + designs.view + designs.manage
  → acquire/download
```

Free means included for an eligible Keryon Church at no extra Marketplace
charge. The default `MarketplaceEntitlement` allows Free with
`free_included` basis and denies Premium with the bounded
`premium_entitlement_unavailable` category. Staff authorization always occurs
before commercial entitlement. No billing, plan, credit, order, or purchase
model exists.

Administrator, Care, and Primary designation do not imply Marketplace access.
Only the existing composable Communications responsibility supplies Design
capabilities. Acquisition and download model invariants also require the
record Church and actor to match the active membership.

## Publication and versions

Items use `draft`, `published`, or `unpublished`. A publish action fails unless
at least one source version is both `valid` and `available`. Catalogue queries
require the same invariant. Unpublishing immediately prevents acquisition and
delivery without deleting acquisitions or download history.

Source versions are numbered uniquely per item. Registration performs the
minimum generic integrity gate needed by this foundation: non-empty content,
PSD or ZIP extension, matching file signature, byte size, canonical detected
MIME, and lower-case SHA-256. It writes to an opaque UUID key below
`marketplace/sources/` on the private `marketplace` disk. It does not parse PSD
layers or ZIP contents. Once available, technical, compatibility, creator,
rightsholder, license, and font metadata cannot be changed in place; a package
replacement creates a new version. Available sources cannot be deleted and
may only be withdrawn.

Licensing/font metadata are bounded seams for the real-product proof, not
legal terms or a font-management system. K-MARKET-002 must validate archive
safety, the real PSD, licensing completeness, font redistribution rights,
previews, and external edit/export behavior.

## Storage and delivery

Marketplace platform assets use a dedicated private filesystem disk rooted at
`storage/app/marketplace-private` by default, outside tenant MediaAsset paths
and the public disk. Database serialization hides physical storage keys.

`MarketplaceSourceDelivery` re-authorizes the active Church/user, resolves the
Church-scoped acquisition, rechecks item/source/entitlement state, verifies the
stored SHA-256, appends an audit event, and invokes a provider-neutral delivery
mechanism. The current filesystem mechanism returns a private streamed
download. A future remote implementation may return a short-lived signed URL
without changing domain authorization. Permanent raw URLs are prohibited.

`MarketplacePreviewDelivery` also re-authorizes `designs.view`, streams from
private storage, and emits private caching plus `X-Robots-Tag` noindex headers.
No customer route or UI is created in K-MARKET-001.

## Lifecycle and deletion

Only MarketplaceItem uses soft deletion because a catalogue product may need
operator recovery while preserving FKs/history. Categories are simple global
taxonomy and use restrictive item FKs. Source versions use versioning and
withdrawal rather than soft deletion. Acquisitions and downloads are audit
evidence and are neither soft-deleted nor casually destructible; download
events reject update/delete in the model.

No source objects are automatically deleted when catalogue state changes.
Physical retention/takedown, acquired-version updates, Central/operator
publishing, Premium entitlement, and malware scanning remain explicitly
deferred.
