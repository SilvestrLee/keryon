# Anthropic FaithFlow Processor Review

**Milestone:** K-TRUST-005
**Reviewed:** 29 August 2026
**Provider status:** `under_review`
**Production FaithFlow customer processing:** blocked

This is an engineering/product evidence record, not legal advice or contract
acceptance. It covers only direct Anthropic API, text-only FaithFlow analysis
and generation using `claude-sonnet-5`.

## Keryon technical facts

FaithFlow sends Church-supplied source text plus Keryon system instructions for
canonical analysis. Generation sends the bounded canonical-analysis JSON.
Keryon does not intentionally add user, membership, email, phone, Care,
MediaAsset bytes, credentials, or storage paths. Provider/model are Keryon
configuration, not tenant input. Outputs persist with provider/model/token
provenance and require human review; there is no automatic publication or
provider fallback.

Current local configuration has no Anthropic key, provider status is
`under_review`, and the current model is `claude-sonnet-5`.

## Primary provider evidence

| Evidence | Provider fact | Keryon treatment |
| --- | --- | --- |
| [Commercial Terms](https://www.anthropic.com/legal/commercial-terms), effective 17 June 2025 | Direct API terms contract with Anthropic Ireland, Limited for EEA/Switzerland/UK customers and Anthropic, PBC elsewhere; customer retains Inputs/owns Outputs; Anthropic may not train on Customer Content; DPA incorporated. | Keryon's contracting entity depends on its actual account/customer residence. Acceptance/execution is not evidenced in the repository and remains an operations/legal gate. |
| [Data Processing Addendum](https://www.anthropic.com/legal/data-processing-addendum), effective 24 February 2025 | Customer is controller and Anthropic processor for Customer Personal Data; processing is bounded to services/instructions; subprocessors permitted under stated notice/objection terms; security and audit provisions are recorded. | Availability is known; Keryon acceptance/execution status is unverified. Counsel must review controller/processor role, cross-border transfer terms, and pastoral/religious data implications. |
| [Commercial API retention](https://privacy.claude.com/en/articles/7996866-how-long-do-you-store-my-organization-s-data), dated 1 July 2026 | Standard API inputs/outputs are deleted within 30 days, except longer controlled-service retention, agreed alternatives such as ZDR, Usage Policy enforcement, or law. Flagged inputs/outputs may be retained up to two years and safety scores up to seven years; feedback can be retained five years. | Do not describe retention merely as “30 days.” Keryon must verify the actual workspace/account posture and must not submit feedback containing customer content. |
| [Zero data retention scope](https://privacy.anthropic.com/en/articles/8956058-i-have-a-zero-data-retention-agreement-with-anthropic-what-products-does-it-apply-to) | ZDR is an approved enterprise arrangement, distinct from default retention; safety classifier results remain; Files API, explicit caching/batch instructions, and third-party search can alter the posture. | Keryon has no evidence of a ZDR agreement. FaithFlow approval must not claim ZDR. Files, tools, external search/connectors, and media remain outside scope. |
| [Commercial training statement](https://privacy.claude.com/en/articles/7996885-how-do-you-use-personal-data-in-model-training), dated 16 March 2026 | Commercial chats/API content is not used for model training unless the customer explicitly opts into the Development Partner Program or submits feedback/otherwise opts in. | Keryon must not join an improvement/training programme or submit customer-content feedback without separate Product Office review. Safety/abuse processing remains distinct from model training. |
| [Server locations](https://privacy.claude.com/en/articles/7996890-where-are-your-servers-located-do-you-host-your-models-on-eu-servers), dated 15 June 2026 | By default traffic may be routed in selected US, European, Asian, and Australian countries; data is stored in the US; internal safety/support/incident processing may occur where Anthropic or affiliates operate. | Region is not “local” or “Nigeria.” Actual routing controls/account instructions must be verified; transfer and jurisdiction consequences require counsel review. |
| [Trust Center and subprocessor registry](https://trust.anthropic.com/subprocessors), reviewed 29 August 2026 | Anthropic uses subprocessors and provides a canonical Trust Center list/update feed. Public Trust Center material identifies major cloud infrastructure subprocessors and certifications for the API, including SOC 2 Type 2, ISO 27001, ISO 42001, and CSA STAR. | Reference the living registry rather than copying it into config. Operations must subscribe/review material changes and request restricted evidence where needed. |
| [Claude models overview](https://platform.claude.com/docs/en/models/overview), reviewed 29 August 2026 | `claude-sonnet-5` is the current Claude API ID for Claude Sonnet 5. | Model identity is verified. Approval remains limited to this explicit pinned model ID; no family-wide or provider-wide inheritance. |
| [Model IDs and versioning](https://platform.claude.com/docs/en/about-claude/models/model-ids-and-versions), reviewed 29 August 2026 | `claude-sonnet-5` is a canonical pinned version ID, not an evergreen alias. Each ID has its own lifecycle. | Model changes require Trust/config review; deployment cannot substitute an arbitrary model. |

## Keryon processing decision

Purpose under review: generate ministry communications content from
Church-supplied FaithFlow source material.

Potentially permitted after all gates:

- PUBLIC text;
- INTERNAL text only within the explicit FaithFlow workflow;
- bounded SENSITIVE ministry source and canonical-analysis text.

Prohibited or not approved:

- HIGHLY_RESTRICTED and all Care data;
- PERSONAL data except a separately minimized/reviewed workflow;
- images, MediaAssets, files, audio, video, embeddings, tools, web search,
  external connectors, Design/AI Design, motion, training/fine-tuning, and
  automatic publishing;
- customer-controlled provider, model, endpoint, or API key;
- provider fallback.

Human review remains required. Users must eventually receive factual disclosure
that Church-supplied content is sent to an approved external AI processor, that
Care must not be submitted, and that generated drafts require review. Final
Privacy/Terms wording is not part of this record.

## Required production evidence

Production remains blocked until Product Office/operations records all of:

1. actual contracting entity for Keryon's account;
2. authorized acceptance of applicable Commercial Terms and incorporated DPA;
3. counsel review of Nigeria/cross-border and religious/pastoral-data issues;
4. verified Anthropic workspace/account retention and routing configuration;
5. confirmation that Keryon is not enrolled in optional training/improvement;
6. approved direct Messages API feature set with no Files API, tools, web search,
   connectors, or media;
7. deployment evidence that the registry is `approved` or `restricted` only
   after those facts are recorded;
8. customer/user disclosure requirements accepted for implementation.

Credentials do not satisfy any item. Development/test may use synthetic data
and explicit test-only approval facts.

## Re-review policy

Review at least annually and earlier on material terms/DPA/subprocessor/
retention changes, model or provider changes, a new data class or AI capability,
an incident, or a material account-configuration change. Future AI capabilities
must independently declare provider, purpose, data class, media/content type,
rights, retention/training, disclosure, and model constraints.
