<?php

namespace App\Communications\OrganizationInbox\Import;

use App\Enums\ContentOrigin;
use App\Enums\ContentType;
use App\Models\ContentItem;
use App\Models\OrganizationCommunicationMaterial;

/**
 * K-ORG-COMMS-001E §11-§13 — the explicit ContentItem-creation seam for
 * Organization import, mirroring `CreateContentItemFromFaithFlow`'s own
 * precedent exactly. Authorization is deliberately not performed here —
 * the caller's (`OrganizationCommunicationImportService`'s) responsibility,
 * which has already checked `Capability::ContentManage` via the import
 * plan before ever calling this.
 *
 * Hard rule (§13): the resulting ContentItem always starts in Content
 * Studio's normal initial state (`ContentStatus::DRAFT`, via ContentItem's
 * own `creating()` default) — Organization approval never becomes Church
 * approval.
 */
class CreateContentItemFromOrganizationImport
{
    public function handle(OrganizationCommunicationMaterial $material, ContentType $contentType): ContentItem
    {
        $contentItem = new ContentItem([
            'title' => $this->deriveTitle($material),
            'content_type' => $contentType,
            'body' => (string) $material->body,
        ]);
        // Not mass-assignable (system-controlled) — set explicitly before
        // save() so ContentItem's own `origin ??= ContentOrigin::HUMAN`
        // creating-hook sees it already populated and leaves it alone.
        // `status` is left untouched: the same hook's
        // `status ??= ContentStatus::DRAFT` default is exactly the
        // initial state this import wants (§13).
        $contentItem->origin = ContentOrigin::ORGANIZATION_IMPORT;
        $contentItem->save();

        return $contentItem;
    }

    private function deriveTitle(OrganizationCommunicationMaterial $material): string
    {
        if (filled($material->title)) {
            return $material->title;
        }

        return $material->type->label();
    }
}
