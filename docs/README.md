# Keryon Documentation

This folder contains product, engineering, and governance documentation for Keryon.

## Start Here

- `00-Blueprint/Blueprint_Index.md`
- `00-Blueprint/Keryon_Master_Blueprint_v1.3.md`
- `00-Blueprint/Keryon_Blueprint_v1.3.1_Engineering_Hardening_Addendum.md`
- `00-Blueprint/Keryon_Blueprint_v1.3.2_Marketplace_Distribution_Addendum.md`

## Governance

The blueprint family is the source of truth for the product.

Engineering should not introduce new modules, statuses, payment functionality, page-builder behavior, or marketplace packaging features unless Product Office explicitly approves them.

## Engineering Governance

- `06-Engineering/Scope_Challenge_Protocol.md` — defines when Claude must challenge scope drift, security risks, tenant isolation issues, and blueprint conflicts, and requires inspecting existing data before mutating controlled values (enums, statuses, roles).
- `06-Engineering/Media_Storage_Configuration_Review.md` — documents production media storage requirements, S3-compatible storage expectations, and tenant media isolation rules.
- `06-Engineering/Media_Path_Strategy.md` — defines tenant-prefixed media path rules for future media storage and upload implementation.
- `06-Engineering/Filament_Product_Experience_Review.md` — defines which Filament areas may remain CRUD and which must become curated product experiences.
- `06-Engineering/Public_Repo_Safety_Audit.md` — records the post-push repository safety audit and Claude artifact tracking correction.
- `06-Engineering/Product_Language_Standard.md` — defines Keryon's product voice, church-name personalization rules, and natural copy standards.
- `06-Engineering/UX_Feedback_and_System_State_Standard.md` — defines how Keryon communicates workflow state, progress, attention, success, and error language; complements the Product Language Standard.
- `06-Engineering/Website_Content_Contract.md` — defines the reusable content-contract format every editable website component must have before it is built.
- `06-Engineering/Responsive_and_Accessibility_Standard.md` — defines the minimum responsive-viewport and accessibility verification expected on user-facing interfaces.
- `06-Engineering/Logging_Standard.md` — defines what may, must not, and must never be written to application logs, given Keryon's pastoral data.
- `06-Engineering/Deployment_Guardrails.md` — defines the minimum inspection required before choosing deployment commands, pending a full deployment runbook.
