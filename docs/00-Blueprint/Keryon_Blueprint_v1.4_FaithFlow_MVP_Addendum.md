# Keryon Blueprint v1.4

## FaithFlow MVP Addendum

Document Type: Blueprint Addendum
Parent Document: Keryon Master Blueprint v1.3
Previous Addendum: Keryon Blueprint v1.3.2 — Marketplace Distribution & Self-Hosted Edition Addendum
Status: Approved — narrow MVP scope
Purpose: Move FaithFlow from future roadmap (Master Blueprint v1.3 §35 "Version 2 — Intelligent Communications") into a narrowly-defined Keryon MVP capability
Scope: FaithFlow MVP boundary only. All other AI capability named elsewhere in the Blueprint family (Keryon AI, sermon transcription, social publishing automation, etc.) remains future roadmap and is explicitly not reopened by this addendum.
Blueprint Impact: Supersedes the specific v1.3 passages that classified FaithFlow/AI content generation as future-only, to the extent necessary for the narrow FaithFlow MVP scope defined below. Does not change product positioning, tenancy rules, or any other v1.3 exclusion.

---

# 1. Addendum Purpose

Keryon Master Blueprint v1.3 and its addenda (v1.3.1, v1.3.2) classified FaithFlow as a future-roadmap capability — see Master Blueprint §35 ("Version 2 — Intelligent Communications"), the "Future Integrations... these remain outside Blueprint v1.3" list, and the general AI exclusion list, all of which CLAUDE.md and `docs/06-Engineering/Scope_Challenge_Protocol.md` mirrored as a blanket Scope Challenge trigger.

Product Office has determined FaithFlow strengthens Keryon's communications-product proposition, gives Communications Hub a genuine intelligence capability, and provides a legitimate variable-cost dimension for commercial tiering — not merely a marketing feature.

This addendum supersedes those specific v1.3 passages **only** to the extent necessary to bring the narrowly-defined FaithFlow MVP described below into current implementation scope. It does not reopen "AI" as a general category — see §12 for what explicitly remains excluded.

---

# 2. Explicit Supersession Statement

FaithFlow is moved from the future roadmap into the Keryon MVP beginning with Blueprint v1.4.

All earlier v1.3 exclusions that classify FaithFlow/AI content generation as future-only are superseded **to the extent necessary** for the narrowly defined FaithFlow MVP scope in this addendum.

Other AI capabilities — Keryon AI as a platform-wide assistant, sermon/video/audio transcription, autonomous publishing, Care Center or congregation-data AI use, AI chatbots, AI pastoral counselling, AI member scoring, AI-generated financial/giving content, unrestricted image/video generation, and external-agent orchestration — remain future scope unless explicitly included in a later addendum.

---

# 3. Product Position

FaithFlow is **not** a fifth top-level Keryon pillar for the MVP.

Existing pillars remain exactly four:

1. Congregation
2. Care Center
3. Communications Hub
4. Campaigns

FaithFlow lives inside Communications Hub, specifically integrated with Content Studio:

```txt
Keryon
└── Communications Hub
    └── Content Studio
        └── FaithFlow
```

Conceptually: **FaithFlow is Keryon's AI-powered ministry-content intelligence capability**, not a standalone product.

---

# 4. Approved MVP Workflow

```txt
Sermon / ministry message
        ↓
FaithFlow analysis
        ↓
Structured ministry insights
        ↓
Generate derivative content
        ↓
Human review / edit
        ↓
Approve
        ↓
Save into Keryon's content workflow
```

This is the MVP boundary. Nothing beyond this workflow shape is approved by this addendum.

---

# 5. Input (MVP)

Deliberate, text-based ministry input only:

```txt
Sermon notes
Sermon manuscript
Teaching notes
Ministry message
Campaign message/source material
```

**Explicitly not approved by this addendum:** audio transcription, video transcription, live sermon capture, microphone recording, YouTube sermon ingestion. These materially increase scope and operating cost and require separate approval.

---

# 6. Approved MVP Output Catalogue

The initial FaithFlow content set for product planning:

- **Sermon Summary** — a concise representation of the source message
- **Key Themes** — important themes FaithFlow identifies in the message
- **Key Quotes** — useful excerpts/quote candidates derived from the supplied material
- **Devotional Content** — short devotional material derived from the message
- **Prayer Points** — prayer prompts based on the source message
- **Social Captions** — church-appropriate social copy derived from the message
- **WhatsApp / Status Copy** — short communication suitable for church messaging/status use
- **Discussion Questions** — questions suitable for reflection, small-group conversation, or follow-up

**Output scope discipline:** no additional AI output type may be added merely because a model can generate it. Any addition beyond this catalogue requires a clear church-communication use case and separate Product Office approval. Named future candidates (not approved here): email/newsletter drafts, campaign variants, website copy suggestions, announcement drafts, youth-specific versions, audience adaptations.

**Quote graphics** (a visual output historically contemplated for FaithFlow) are explicitly **not** included in this MVP catalogue — classified as an **MVP candidate requiring separate technical/design assessment** of whether the existing Media Library/design capability supports them without materially expanding scope. Not silently approved by this addendum.

---

# 7. Human Review Is Required

FaithFlow is an assistance system. Generated ministry content must never automatically become published church communication.

```txt
Generate → Review → Edit if necessary → Approve → Use
```

Explicitly not approved: autonomous theological approval, automatic publishing, automatic doctrine decisions, automatic campaign sending.

---

# 8. No Automatic Social Publishing

The existing MVP exclusion on automatic social-media publishing (Master Blueprint §"Social Publishing Drift") remains in force and is not altered by this addendum.

```txt
Generate content            ✓
Automatically publish externally   ✗
```

FaithFlow may generate social copy. It may not publish it.

---

# 9. Integration Relationships

**Content Studio:**

```txt
Content Studio
    ↓
Source content
    ↓
FaithFlow generation
    ↓
Generated content assets
    ↓
Review
    ↓
Save / organise
    ↓
Content Calendar / Campaign workflow where relevant
```

**Campaigns:** Campaigns may consume FaithFlow-generated content (e.g., an Easter campaign's message/source material producing captions, prayer points, devotional copy, website copy suggestions) by reusing the same Content Studio generation capability. A second, Campaign-specific AI generation engine is explicitly not approved.

**Media Library:** generated textual content may be stored/associated with Content Studio records. Generated imagery (quote graphics) is not assumed MVP scope — see §6.

None of this integration is implemented by this addendum — it documents the intended relationship for the architecture milestone (K-FAITHFLOW-001) to design against.

---

# 10. Variable Cost & Pricing Role

FaithFlow introduces real variable operating cost (model/API calls, input size, generated output count, regeneration, model choice, future media generation). This makes FaithFlow usage an approved legitimate commercial-tiering dimension — fundamentally different from charging a church more for having more congregation members or prayer requests, which remains prohibited.

Conceptual tier role (allowances not decided by this addendum):

- **Starter** — FaithFlow included, lower monthly allowance, designed for lighter/occasional content workflows
- **Growth** — FaithFlow included, materially higher allowance, designed around an active weekly church communications rhythm
- **Professional** — FaithFlow included, highest standard allowance, designed for higher-volume communications teams

FaithFlow must not be gated entirely out of Starter. Exact allowances require workflow definition, model/provider assumptions, cost modelling, and entitlement design — none of which this addendum performs.

**Customer-facing usage unit:** not decided by this addendum. Raw tokens/model calls/API cents must never become the primary church-facing unit. Product Office's stated preference to investigate first is "content runs / content packs," since it maps to the workflow rather than technical AI accounting — this remains an investigation candidate, not an approved unit.

**Regeneration:** has real AI cost and must eventually be bounded by the entitlement model (no "1 pack + unlimited regenerate" unbounded-spend design), without becoming punitive UX. Left to the K-FAITHFLOW-001 architecture milestone.

---

# 11. Core Modules Remain Whole

Tier differentiation must not depend primarily on stripping core Keryon modules. All paid tiers retain: Congregation, Care Center, Communications Hub, Campaigns, Website Content, standard Themes, managed website hosting, and FaithFlow access. Differentiation scales through FaithFlow allowance, team capacity, storage, permissions depth, support/onboarding, and future higher-cost capabilities — not module removal.

---

# 12. Explicit MVP Exclusions

Moving FaithFlow into MVP does **not** automatically bring into scope:

```txt
Sermon audio transcription
Sermon video transcription
Live speech-to-text
Autonomous publishing
Autonomous campaign execution
AI chatbot for congregation members
AI pastoral counselling
AI prayer-response generation from private care records
AI member scoring
Automated social publishing
AI-generated financial/giving content
Unrestricted image/video generation
External-agent orchestration
```

These remain excluded unless separately approved through their own addendum.

---

# 13. Data Privacy Boundaries

**Care Center boundary:** FaithFlow must not automatically process private Care Center prayer/care records as generation input. FaithFlow MVP belongs to Communications Hub/Content Studio. Any future AI use of Care Center information requires separate privacy/product approval — an explicit boundary, not an oversight.

**Congregation data boundary:** member profiles or personal congregation data must not be automatically fed into FaithFlow. FaithFlow MVP works from ministry/content source material deliberately supplied for generation. Any future personalization using member data requires separate privacy/architecture review.

---

# 14. Provider & Architecture Neutrality

Moving FaithFlow into MVP does **not** approve any specific AI provider or model (OpenAI, Anthropic, Gemini, or otherwise). Provider/model selection is a separate Architecture/Engineering decision. The product contract should remain provider-agnostic where practical.

Future architecture (K-FAITHFLOW-001, not this addendum) should evaluate: structured, validated generation over freeform output; durable generation records (source reference, generation type, output, status, model/provider identifier, prompt/version identifier, usage/cost metadata, human-approval status, timestamps); and internal AI cost tracking (provider, model, request, usage, cost estimate, status, retries/regenerations) as operational data not necessarily exposed to churches. No migrations, models, or schema are created by this addendum.

---

# 15. Safety & Ministry Quality

MVP design must account for: preserving source meaning, avoiding fabricated scripture references, distinguishing direct source quotes from generated wording, human review, no automatic publishing, and a clear generated-content state. Keryon does not attempt to build a theological truth engine — human church leadership remains responsible for final content.

---

# 16. Product Language

FaithFlow public language must avoid generic AI hype:

```txt
Avoid:  revolutionary AI, AI-powered transformation, magical content, replace your communications team
Prefer: Turn one ministry message into useful communication your team can review, shape, and use throughout the week.
```

FaithFlow assists the team. It does not replace ministry leadership.

---

# 17. Website / Marketing Impact

This addendum does not modify any public page. Features, Solutions, Pricing, or homepage updates representing FaithFlow truthfully are separate, later website work, performed only after this scope is frozen and tracked — not before.

---

# 18. Next Milestone

The next FaithFlow milestone is **K-FAITHFLOW-001 — MVP Product + Architecture Contract**, covering: input model, output schema, generation lifecycle, approval workflow, provider abstraction, cost tracking, usage metering, entitlements, storage, privacy, tests, and Content Studio integration. FaithFlow is not built until that architecture is separately reviewed and approved.

---

# 19. Product Office Decision

Approved as the tracked, versioned scope boundary for FaithFlow's move from future roadmap into Keryon MVP. Supersedes Master Blueprint v1.3 §35 and the AI exclusion lists **only** within the narrow boundary defined above. `CLAUDE.md` and `docs/06-Engineering/Scope_Challenge_Protocol.md` are updated to reference this addendum precisely, without weakening the general Scope Challenge mechanism for AI capability outside this boundary.
