<?php

namespace App\Enums;

use App\Filament\Clusters\Website\Pages\EditAbout;
use App\Filament\Clusters\Website\Pages\EditHome;
use App\Models\WebsiteAboutContent;
use App\Models\WebsiteHomeContent;

enum WebsiteDraftDestination: string
{
    case HomeHero = 'home.hero';
    case HomeWelcome = 'home.welcome';
    case AboutStory = 'about.story';
    case AboutVision = 'about.vision';
    case AboutMission = 'about.mission';

    public function label(): string
    {
        return match ($this) {
            self::HomeHero => 'Home hero',
            self::HomeWelcome => 'Home welcome',
            self::AboutStory => 'About church story',
            self::AboutVision => 'About vision',
            self::AboutMission => 'About mission',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::HomeHero => 'Use the content title and body as the homepage heading and supporting text.',
            self::HomeWelcome => 'Use the content title and body as the homepage welcome.',
            self::AboutStory => 'Use the content body as the Church story.',
            self::AboutVision => 'Use the content body as the Church vision.',
            self::AboutMission => 'Use the content body as the Church mission.',
        };
    }

    /** @return list<ContentType> */
    public function compatibleTypes(): array
    {
        return match ($this) {
            self::HomeHero => [ContentType::WEBSITE_COPY, ContentType::ANNOUNCEMENT, ContentType::CAMPAIGN_COPY],
            self::HomeWelcome => [ContentType::GENERAL, ContentType::ANNOUNCEMENT, ContentType::CAMPAIGN_COPY, ContentType::WEBSITE_COPY, ContentType::DEVOTIONAL, ContentType::SERMON_SUMMARY],
            self::AboutStory => [ContentType::GENERAL, ContentType::WEBSITE_COPY, ContentType::SERMON_SUMMARY],
            self::AboutVision, self::AboutMission => [ContentType::WEBSITE_COPY],
        };
    }

    public function supportsMedia(): bool
    {
        return $this === self::HomeHero;
    }

    public function modelClass(): string
    {
        return match ($this) {
            self::HomeHero, self::HomeWelcome => WebsiteHomeContent::class,
            self::AboutStory, self::AboutVision, self::AboutMission => WebsiteAboutContent::class,
        };
    }

    public function editUrl(): string
    {
        return match ($this) {
            self::HomeHero, self::HomeWelcome => EditHome::getUrl(),
            self::AboutStory, self::AboutVision, self::AboutMission => EditAbout::getUrl(),
        };
    }
}
