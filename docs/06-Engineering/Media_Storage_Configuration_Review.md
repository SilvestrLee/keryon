# Keryon Media Storage Configuration Review

## Document Type

Engineering Hardening Review

## Parent Documents

- Keryon Master Blueprint v1.3
- Keryon Blueprint v1.3.1 Engineering Hardening Addendum
- Scope Challenge Protocol

## Status

Approved Review Document

## Purpose

This document records Keryon's production media storage requirements before the Media Library and Communications Hub upload workflows are implemented.

Keryon must not depend on local server disk for persistent production media storage.

Production media must use S3-compatible object storage with tenant-aware isolation.

---

## Current Configuration Summary

- Default filesystem disk: `local` (via `FILESYSTEM_DISK`, defaulting to `local`)
- Available disks: `local`, `public`, `s3` (all defined in `config/filesystems.php`)
- S3 disk present: yes, as configuration only — the disk array exists and reads from `AWS_*` env vars
- S3 adapter package present: no — `league/flysystem-aws-s3-v3` and `aws/aws-sdk-php` are not installed (not in `composer.json`, not in `vendor/`)
- Spatie Media Library present: no
- Production-ready as-is: no

`.env.example` includes some but not all recommended S3 variables: `FILESYSTEM_DISK`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION`, `AWS_BUCKET`, `AWS_USE_PATH_STYLE_ENDPOINT` are present; `AWS_URL` and `AWS_ENDPOINT` are missing. No secrets are recorded in this document.

---

## Approved Production Storage Principle

Production media storage must use S3-compatible object storage.

Approved providers may include:

```txt
AWS S3
DigitalOcean Spaces
Cloudflare R2
Wasabi
Other S3-compatible providers approved later
```

Local storage is allowed for development only.

---

## Local Development Exception

Local development may use:

```txt
storage/app/public
public disk
php artisan storage:link
```

This is acceptable only for local development.

Production must not rely on local server disk for persistent media.

---

## Required Environment Variables

Future production deployment should support variables such as:

```txt
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=
AWS_BUCKET=
AWS_URL=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=
```

Provider-specific notes:

```txt
AWS S3 may not require AWS_ENDPOINT.
DigitalOcean Spaces, Cloudflare R2, and Wasabi usually require endpoint configuration.
Cloudflare R2 may require path-style or endpoint-specific handling depending on SDK configuration.
```

Do not store real credentials in documentation.

---

## Tenant Media Isolation Requirement

All tenant media must be stored using tenant-aware prefixes.

Required future path pattern:

```txt
tenants/{church_id}/media/{media_id}/
```

Example:

```txt
tenants/42/media/938/banner.jpg
```

This ensures that media is logically separated by church.

---

## Storage Quota Requirement

Before upload completion, Keryon must be able to check:

```txt
current used storage
incoming file size
church plan limit
file type restrictions
maximum file size limits
```

Uploads should be blocked before a church exceeds its storage allowance.

This is not implemented in this sprint.

---

## Security Requirements

Future media implementation must ensure:

```txt
media belongs to one church
media paths are tenant-prefixed
media access is tenant-scoped
upload validation exists
file type restrictions exist
maximum file size rules exist
public/private visibility is deliberate
temporary URLs are used where appropriate
secrets are not committed
```

---

## Future Implementation Notes

Before implementing the Media Library, Engineering must confirm:

```txt
S3 adapter package approval
Spatie Media Library installation approval, if not already installed
tenant path generator strategy
quota tracking strategy
media ownership model
upload validation rules
public/private disk strategy
backup and retention expectations
```

---

## Explicit Non-Goals

This review does not implement:

```txt
Media Library
file uploads
tenant path generator
quota enforcement
S3 deployment
Spatie Media Library installation
media database tables
Communications Hub
marketplace packaging
```

---

## Product Office Decision

T-03 is complete when:

```txt
current storage configuration is reviewed
production risks are documented
S3-compatible storage requirement is recorded
tenant media isolation requirement is recorded
no media implementation has started
```
