# Communications Design and Rendering Architecture

**Milestone:** K-DESIGN-001  
**Status:** Architecture decision for foundation implementation  
**Date:** 2026-08-22

## Boundary

Keryon Design is a structured communications renderer, not a canvas or page
builder. Churches may choose a curated template/version/variant, enter bounded
slot values, select same-Church Institutional Media, and request supported
formats. Template code owns composition, typography scale, safe areas, crop
policy, semantic colour roles, logo placement, and export dimensions. Tenant
input can never contain HTML, CSS, coordinates, layers, fonts, effects, or
renderer code.

The product UI uses the Keryon Signature System. Rendered communication assets
use the Church Brand Profile and the selected Keryon template. Those identities
must never be conflated.

## Architecture Decision

Templates use a hybrid architecture:

- immutable contracts and renderers are curated application code;
- the registry resolves the exact `template-key@integer-version` identity;
- family, purpose, slots, variants, supported formats, dimensions, and rules
  are contract metadata exposed by code;
- a Church-owned `Design` persists the selected identity plus normalized
  working inputs and a Brand Profile snapshot;
- `DesignMedia` persists selected Institutional Media by declared image-slot
  key;
- `DesignOutput` persists one requested format and its independent render
  outcome, optionally linking the canonical generated `MediaAsset`;
- no template-builder, tenant template table, plugin loading, marketplace,
  arbitrary schema language, or template-owned media repository exists.

An existing version is never edited in place. A material family change creates
a new coherent family version, and every format renderer in that release shares
that version. Existing Designs retain their original key/version and snapshots.

## Vocabulary

`DesignPurpose` is intentionally separate from `ContentType` and
`CommunicationChannel`. ContentType classifies copy shape, while DesignPurpose
classifies visual communication intent. The initial bounded purposes are
`service`, `announcement`, `scripture`, `quote`, and `campaign`. More specific
church-event labels remain slot content or curated template compatibility, not
a giant enum.

The typed slot vocabulary is: short text, long text, date, time, scripture,
call-to-action, church identity, image, logo, social handle, and location. A
slot contract declares requiredness and bounded constraints such as character
and line limits. Values that exceed a hard composition limit fail validation;
they are not silently truncated or shrunk without limit. Renderers may expose a
code-defined alternate layout or bounded fallback type scale only where the
template contract explicitly permits it.

## Brand and Media Resolution

Brand Profile values enter a Design through semantic roles, never as scattered
raw colours. The creation service snapshots the resolved palette and approved
heading/body font choices. A renderer receives semantic `background`, `text`,
`emphasis`, `accent`, and `cta` tokens. Each template owns safe fallbacks and
must reject or substitute an unsafe combination rather than sacrifice contrast.

Logo/mark references and image-slot selections remain explicit MediaAsset
foreign keys. All are verified against the active Church. Source geometry is
preserved; the contract controls logo kind, placement, safe area, maximum
proportion, and light/dark treatment. Images support only deterministic `cover`
or `contain`. Focal-point editing is deferred because MediaAsset has no focal
metadata. Remote fetching and tenant-controlled SVG/HTML execution are
prohibited.

## Formats and Rendering

Initial format contracts are square (1080x1080), portrait (1080x1350), and
story (1080x1920). Each family provides a layout-aware renderer for each
supported format. Resizing or cropping one output into another is prohibited.
Landscape and print formats are deferred.

The stable application boundary is a renderer that accepts an immutable,
Eloquent-free `DesignRenderingContext` and one output format, and returns bytes,
MIME type, extension, and exact dimensions. Rendering can run synchronously in
tests or behind the existing `TenantAwareJob` seam later without changing the
contract.

No installed dependency currently provides production-quality HTML/SVG to
raster rendering. The recommended implementation gate is Playwright/Chromium,
using server-rendered, application-owned HTML/CSS and local curated fonts,
captured at exact viewport dimensions. This best fits layout-aware typography,
image composition, format variants, and local/production parity. It requires a
separate runtime/package authorization; K-DESIGN-001 installs nothing and does
not implement raster output.

Native GD is insufficient for high-quality typography and layout. Imagick is
not an application dependency and still requires substantial manual layout.
Pure SVG is deterministic and safe when fully application-owned, but complex
text wrapping and font parity are weaker. Browser rendering has the largest
runtime footprint, so production must pin browser/font versions, disable
network access, reject tenant HTML/CSS/SVG execution, enforce time/memory
limits, and sandbox the process.

PNG is the canonical initial export. JPEG, PDF, A4, and A5 are deferred. Storage
uses safe generated paths under the existing
`tenants/{church_id}/media/{uuid}/` strategy. Friendly display filenames are
metadata only and are never interpolated directly into physical paths.

## Persistence, Lifecycle, and Provenance

A `Design` is a Church-owned structured creative instance. It may explicitly
reference a source ContentItem, Campaign, and CampaignCommunication. Working
inputs are captured at creation and never live-update when source copy changes.
CampaignCommunication must belong to the selected Campaign and all provenance
must belong to the active Church.

The minimum truthful Design lifecycle is `draft` and `approved`. Rendering is
an output condition, not a Design state. Approval means every requested output
has rendered successfully and a human with `designs.manage` accepted the
visuals. Approval does not publish anything. A later edit or regeneration must
clear approval.

Each requested format has `pending`, `rendered`, or `failed` status. Multi-format
generation is per-format durable rather than transactional across external file
work. Product success is reported only when every requested output is rendered;
partial results remain visible and retryable, never described as complete.

Rendered files become MediaAsset records and link through DesignOutput. The
relation itself supplies generated origin, source Design, and output format;
MediaAsset's existing dimensions, MIME type, filename, storage path, and alt
text supply the remaining canonical metadata. No arbitrary metadata JSON or
second asset store is added. A Campaign association reuses CampaignMedia after
successful rendering; the renderer does not mutate Campaigns itself.

## Authorization and Tenancy

The minimum capability pair is `designs.view` and `designs.manage`, granted only
to the Communications responsibility. Approval uses `designs.manage`; a third
approval capability would not express a distinct current responsibility.
Administrator, Primary designation, and Care do not inherit Design access.
Entitlement checks may later wrap registry visibility or render requests, but
remain separate from authorization.

Every persisted Design record uses `BelongsToChurch`. Creation derives Church
from TenantContext, never caller input. Unscoped lookups are used only inside
explicit ownership validation, followed by exact active-Church comparison.
Foreign Brand Profiles, MediaAssets, ContentItems, Campaigns, or Campaign
communications fail before persistence or context construction.

## Failure and Quality Contract

Missing template/version, unsupported format/variant, missing required slot,
invalid slot value, missing media, unsafe brand resolution, and foreign source
references are validation failures before rendering. Runtime failures mark only
the affected output failed with a bounded machine-readable category; they do
not create a MediaAsset or change Design approval.

Template QA must cover long Church names and titles, absent optional values,
brand colour extremes, light/dark logo treatments, optional image absence,
multiple source aspect ratios, overflow, contrast, safe text sizes, hierarchy,
and exact export dimensions. Contract tests should assert registry identity,
validation, resolved context, dimensions, and tenant assets. Pixel snapshots
are deferred; a small reviewed golden-image set may be added once a pinned
renderer exists.

## Future Product Boundary

K-DESIGN-002 may build a structured side-panel workflow around these contracts:
template-compatible choices, slot forms, explicit format selection, responsive
previews, inline overflow errors, and approval. Users may change structured
copy, selected Institutional Media, template, format, and curated variant. They
may not manipulate positions, layers, raw type sizes, arbitrary colours, CSS,
effects, or widgets.

No Design Studio UI, general-purpose canvas, AI imagery, publishing integration,
template marketplace, print-production suite, background removal, image editor,
or package installation is authorized by this decision.

## Linux Renderer Proof and Production Seam

The repository-owned renderer lives under `renderer/` and is exercised by the
dedicated `Design Renderer Integration` GitHub Actions workflow on Ubuntu
24.04. The workflow installs only the Chromium browser for Playwright 1.62.1,
verifies the locked browser identity, renders synthetic square, portrait, and
story fixtures, inspects the PNG headers for exact dimensions, and retains the
three samples for seven days as test artifacts. GitHub Actions is an integration
environment, not a production component.

The future production path remains web application to queued render job to an
isolated Linux renderer worker to application persistence. Both CI and that
worker invoke the same `npm run test:design-renderer:integration` contract and
the same application-owned template implementation. Ordinary Laravel boot,
PHP tests, Filament, and Vite do not launch Chromium. A real render attempted on
an unsupported or missing runtime returns the bounded
`renderer_runtime_unavailable` failure and does not create canonical media.

The initial renderer uses only platform-installed Arial/Helvetica and
Georgia/Times families: Inter and Geist preferences map to the sans-serif
family; Playfair Display, Merriweather, and Source Serif preferences map to the
serif family. No commercial font files or remote font requests are used. This
mapping is recorded with renderer evidence and can be replaced only by a
reviewed, locally bundled, license-compatible registry.
