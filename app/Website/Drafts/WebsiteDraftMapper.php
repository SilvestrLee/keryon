<?php

namespace App\Website\Drafts;

use App\Enums\WebsiteDraftDestination;
use App\Models\ContentItem;
use App\Models\MediaAsset;
use Illuminate\Support\Str;

final class WebsiteDraftMapper
{
    /** @return array<string, mixed> */
    public function map(ContentItem $content, WebsiteDraftDestination $destination, ?MediaAsset $media = null): array
    {
        $body = $this->plainText($content->body);

        return match ($destination) {
            WebsiteDraftDestination::HomeHero => array_filter([
                'hero_heading' => trim($content->title),
                'hero_subheading' => $body,
                'hero_image_id' => $media?->id,
            ], fn (mixed $value): bool => $value !== null),
            WebsiteDraftDestination::HomeWelcome => [
                'welcome_heading' => trim($content->title),
                'welcome_body' => $body,
            ],
            WebsiteDraftDestination::AboutStory => ['church_story' => $body],
            WebsiteDraftDestination::AboutVision => ['vision' => $body],
            WebsiteDraftDestination::AboutMission => ['mission' => $body],
        };
    }

    private function plainText(string $markdown): string
    {
        $html = Str::markdown($markdown);
        $html = preg_replace('/<\/(p|div|h[1-6]|li|blockquote)>/i', "\n", $html) ?? $html;

        return trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
    }

    /** @return list<string> */
    public function fields(WebsiteDraftDestination $destination): array
    {
        return match ($destination) {
            WebsiteDraftDestination::HomeHero => ['hero_heading', 'hero_subheading', 'hero_image_id'],
            WebsiteDraftDestination::HomeWelcome => ['welcome_heading', 'welcome_body'],
            WebsiteDraftDestination::AboutStory => ['church_story'],
            WebsiteDraftDestination::AboutVision => ['vision'],
            WebsiteDraftDestination::AboutMission => ['mission'],
        };
    }
}
