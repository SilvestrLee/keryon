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

- `06-Engineering/Scope_Challenge_Protocol.md` — defines when Claude must challenge scope drift, security risks, tenant isolation issues, and blueprint conflicts.
- `06-Engineering/Media_Storage_Configuration_Review.md` — documents production media storage requirements, S3-compatible storage expectations, and tenant media isolation rules.
- `06-Engineering/Media_Path_Strategy.md` — defines tenant-prefixed media path rules for future media storage and upload implementation.
- `06-Engineering/Filament_Product_Experience_Review.md` — defines which Filament areas may remain CRUD and which must become curated product experiences.
