# Keryon Media Path Strategy

## Document Type

Engineering Hardening Strategy

## Parent Documents

- Keryon Master Blueprint v1.3
- Keryon Blueprint v1.3.1 Engineering Hardening Addendum
- Media Storage Configuration Review
- Scope Challenge Protocol

## Status

Approved Strategy Document

## Purpose

This document defines how Keryon must structure tenant-owned media paths when Media Library functionality is implemented later.

Keryon is a multi-tenant church communications platform.

Every tenant-owned media file must be clearly associated with one church and stored under a tenant-aware prefix.

---

## Approved Path Pattern

All tenant-owned media must use the following base path:

```txt
tenants/{church_id}/media/{media_id}/
```

Example:

```txt
tenants/42/media/938/banner.jpg
```

This pattern is mandatory for production media storage.

---

## Why Tenant-Prefixed Paths Are Required

Tenant-prefixed media paths provide:

```txt
clear logical separation by church
safer S3/object storage organization
simpler tenant-level backup and export possibilities
simpler storage usage reporting
reduced risk of path collisions
clearer incident response if media access issues occur
```

Tenant-prefixed paths do not replace authorization.

They support tenant isolation but must work alongside model scopes, policies, and access controls.

---

## Approved Future Path Examples

Original file:

```txt
tenants/{church_id}/media/{media_id}/{filename}
```

Conversions:

```txt
tenants/{church_id}/media/{media_id}/conversions/{filename}
```

Responsive images:

```txt
tenants/{church_id}/media/{media_id}/responsive-images/{filename}
```

Temporary processing paths, if needed, must also be tenant-aware or cleaned immediately after processing.

---

## Disallowed Path Patterns

Do not store tenant media in ambiguous shared paths such as:

```txt
media/{media_id}/
uploads/{filename}
public/uploads/{filename}
churches/{church_id}/{filename}
campaigns/{campaign_id}/{filename}
website/{filename}
tenant-media/{filename}
```

Reason:

These patterns either hide tenant ownership, create collision risk, or make future storage reporting harder.

---

## Tenant Ownership Resolution

Before storing media, Keryon must resolve a church owner.

A media item may resolve `church_id` from:

```txt
the authenticated user's church_id
the owning model's church_id
an explicitly approved tenant context
```

If no church owner can be resolved, the upload must fail closed.

Approved principle:

```txt
No church_id, no tenant media upload.
```

---

## Future Spatie Media Library Strategy

When Spatie Media Library is approved and installed later, Keryon should use a tenant-aware path strategy.

Likely future implementation:

```txt
custom Spatie Media Library path generator
```

Possible future class name:

```txt
App\Support\Media\TenantMediaPathGenerator
```

Expected future behavior:

```txt
base path: tenants/{church_id}/media/{media_id}/
conversion path: tenants/{church_id}/media/{media_id}/conversions/
responsive images path: tenants/{church_id}/media/{media_id}/responsive-images/
```

This class must not be created until Media Library implementation is approved.

---

## Platform-Owned Media Exception

Some future media may be platform-owned rather than tenant-owned.

Examples may include:

```txt
built-in templates
demo assets
system placeholders
marketplace preview assets
```

Platform-owned media must not be mixed with tenant media.

A future approved path may be:

```txt
platform/media/{media_id}/
```

This exception requires Product Office approval before implementation.

Default assumption:

```txt
media is tenant-owned unless explicitly approved as platform-owned.
```

---

## Public vs Private Media

Tenant path isolation is not the same as access control.

Future media implementation must deliberately classify collections as public or private.

Examples:

```txt
public website images may be public
published campaign images may be public
draft-only media may require authenticated access
future care-related attachments should be private
```

Do not assume all media is public.

Do not assume all media is private.

Visibility must be decided per collection.

---

## Storage Quota Alignment

The media path strategy must support future storage quota enforcement.

Future quota calculations may need to group usage by:

```txt
church_id
media collection
file type
file size
created_at
```

The tenant path pattern supports quota enforcement by grouping files under:

```txt
tenants/{church_id}/
```

Quota enforcement is not implemented in this strategy document.

---

## Backup and Export Considerations

Tenant-prefixed paths support future operational needs such as:

```txt
tenant-level backups
tenant-level export
tenant offboarding
storage usage auditing
incident review
provider migration
```

These capabilities are not implemented now, but the path strategy should not block them.

---

## Local Development

Local development may use the public disk or local storage.

However, local media paths should still mimic the tenant-aware structure where practical.

Development exception:

```txt
local storage is allowed locally
tenant-aware paths still preferred
production must use S3-compatible object storage
```

---

## Implementation Guardrails

Future implementation must not:

```txt
store tenant media outside tenant-prefixed paths
allow uploads without church ownership
commit credentials
make all files public by default without review
bypass tenant scoping
use local disk for persistent production media
mix platform media and tenant media
install packages without approval
```

---

## Non-Goals

This strategy does not implement:

```txt
Spatie Media Library
S3 adapter package
Media model
media migrations
upload forms
path generator class
storage quota table
file validation rules
public/private media policy
Communications Hub
Website Content uploads
Campaign uploads
marketplace packaging
```

---

## Future Implementation Checklist

Before Media Library implementation begins, Engineering must receive approval for:

```txt
Spatie Media Library installation
S3 adapter package installation
Media model strategy
tenant path generator implementation
media ownership rules
collection naming rules
file validation rules
public/private collection strategy
quota tracking strategy
.env.example storage variable updates
production storage provider decision
```

---

## Product Office Decision

T-04 is complete when:

```txt
tenant media path rules are documented
future Spatie path strategy is documented
platform-owned media exception is documented
public/private media warning is documented
quota alignment is documented
no media implementation has started
```
