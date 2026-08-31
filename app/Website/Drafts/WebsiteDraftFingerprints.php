<?php

namespace App\Website\Drafts;

use App\Enums\WebsiteDraftDestination;
use App\Models\ContentItem;
use Illuminate\Database\Eloquent\Model;

final class WebsiteDraftFingerprints
{
    public function source(ContentItem $content, array $mapped): string
    {
        return $this->hash([
            'content_item_id' => $content->id,
            'approved_at' => $content->approved_at?->toISOString(),
            'content_type' => $content->content_type->value,
            'mapped' => $mapped,
        ]);
    }

    public function destination(Model $record, WebsiteDraftDestination $destination, WebsiteDraftMapper $mapper): string
    {
        return $this->hash(collect($mapper->fields($destination))
            ->mapWithKeys(fn (string $field): array => [$field => $record->getAttribute($field)])
            ->all());
    }

    public function empty(WebsiteDraftDestination $destination, WebsiteDraftMapper $mapper, Model $record): bool
    {
        return collect($mapper->fields($destination))->every(fn (string $field): bool => blank($record->getAttribute($field)));
    }

    private function hash(array $value): string
    {
        return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }
}
