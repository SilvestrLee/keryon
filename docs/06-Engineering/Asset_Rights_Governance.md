# Asset Rights Governance

**Milestone:** K-TRUST-006
**Date:** 29 August 2026
**Status:** Implemented foundation

## Governing boundary

Possession, ownership, storage, internal use, publication, AI processing,
reference use, training, commercial use, and redistribution are independent
rights. K-AUTH still decides who may act; `AssetRightsPolicy` decides whether
the asset is compatible with the requested use. Both gates must pass.

The code-defined uses are `store`, `internal_use`, `publish`, `ai_process`,
`reference`, `train`, `commercial_use`, and `redistribute`. Reference does not
imply training. Publication does not imply AI processing or redistribution.

## Church operational media

New ordinary Church uploads receive a one-to-one `MediaAssetRights` profile:

- provenance: `church_declared`;
- status: `declared`;
- permitted: private storage, authorized internal use, and an explicit
  Church-requested publication workflow;
- not inferred: AI processing, reference, training, Keryon commercial use, or
  redistribution.

This is a product representation of the required uploader authority concept,
not final uploader warranty language. Existing MediaAssets without a rights
row receive the same narrow operational compatibility default in policy; no
historical verification or broader consent is fabricated.

Deterministic Keryon Design renderer outputs record `keryon_created`
provenance with the same bounded operational uses. This does not represent AI
generation, and future AI Design must additionally satisfy AI provider,
source-asset, identity/person, and generation-rights controls.

Rights states are `unverified`, `declared`, `verified`, `restricted`,
`expired`, `disputed`, and `withdrawn`. Restricted, expired, disputed, and
withdrawn Church media remains preservable for storage/internal evidence but
cannot create a new public rendition. A restrictive decision deactivates its
public references and revokes public rendition delivery without erasing the
private canonical original.

## Marketplace convergence

Marketplace keeps its stronger, already-implemented source-version clearance
model. `MarketplaceRightsStatus`, `MarketplaceRightsGate`, and
`ReviewMarketplaceSourceRights` are **kept**. The shared policy delegates
Marketplace publish/commercial/redistribution decisions to that gate rather
than duplicating its evidence contract.

Marketplace clearance requires verified creator, rightsholder, licence,
redistribution authority, source provenance, evidence, platform verifier, and
font declaration. Technical validation, Free/Premium classification,
possession, publication history, acquisition, and prior delivery never imply
rights. A replacement source version begins pending and cannot inherit the
previous artifact's verification.

The Sunday Service ZIP is a valid technical proof stored privately. Its README
records creator/rightsholder/licence/font declarations as pending and contains
no bundled font files. Its real clearance therefore remains pending and all
future source delivery remains blocked. Synthetic tests prove both denial and
the explicit verified-clearance path without claiming real legal permission.

## Rights, publication, and AI

`PublicMediaRenditionManager` enforces `publish` before copying canonical bytes
to a public rendition. Website authorization cannot override that denial.
Future media AI must pass both K-TRUST-003 provider/data policy and
`ai_process`; private storage or publication alone is insufficient. Care data
remains outside this reusable media boundary and categorically AI-ineligible.

No generalized rights dashboard, seller system, AI Design feature, motion
feature, consent platform, or legal document is introduced. The schema is a
small Church-Media-specific profile; Marketplace retains its separate
artifact-specific evidence because its redistribution risk is materially
higher.

## Legal review required

Professional wording and decisions remain required for the Church uploader
representation, public-media authority, person/identity-image consent,
Marketplace customer usage licence and source-resale prohibition, copyright
complaints/takedowns, third-party embedded assets, licence expiry, and prior
acquirer rights after later withdrawal. Gilroy remains authorized only for
Keryon application/marketing use; it is not authorized for Marketplace or
Church-theme redistribution by this record.
