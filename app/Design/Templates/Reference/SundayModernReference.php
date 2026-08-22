<?php

namespace App\Design\Templates\Reference;

use App\Design\Templates\DesignBrandRules;
use App\Design\Templates\DesignImageSlot;
use App\Design\Templates\DesignSlot;
use App\Design\Templates\DesignTemplateDefinition;
use App\Enums\DesignImageFit;
use App\Enums\DesignOutputFormat;
use App\Enums\DesignPurpose;
use App\Enums\DesignSlotType;

final class SundayModernReference
{
    public static function definition(): DesignTemplateDefinition
    {
        return new DesignTemplateDefinition(
            key: 'sunday-modern-reference',
            version: 1,
            name: 'Sunday Modern Reference',
            family: 'sunday-modern',
            familyVersion: 1,
            purposes: [DesignPurpose::SERVICE],
            formats: [
                DesignOutputFormat::SQUARE,
                DesignOutputFormat::PORTRAIT,
                DesignOutputFormat::STORY,
            ],
            slots: [
                new DesignSlot('title', 'Title', DesignSlotType::SHORT_TEXT, required: true, maxCharacters: 72, maxLines: 3),
                new DesignSlot('date', 'Date', DesignSlotType::DATE, required: true),
                new DesignSlot('time', 'Time', DesignSlotType::TIME, required: true),
                new DesignSlot('theme', 'Theme', DesignSlotType::SHORT_TEXT, maxCharacters: 90, maxLines: 2),
                new DesignSlot('scripture', 'Scripture', DesignSlotType::SCRIPTURE, maxCharacters: 60, maxLines: 2),
                new DesignSlot('speaker', 'Speaker', DesignSlotType::SHORT_TEXT, maxCharacters: 60, maxLines: 2),
                new DesignSlot('cta', 'Call to action', DesignSlotType::CALL_TO_ACTION, maxCharacters: 40, maxLines: 1),
            ],
            imageSlots: [
                new DesignImageSlot('background', 'Background image', false, DesignImageFit::COVER, 1080, 1080),
                new DesignImageSlot('speaker', 'Speaker image', false, DesignImageFit::COVER, 900, 1200),
            ],
            brand: new DesignBrandRules(
                logoRequired: false,
                markSupported: true,
                primaryColorSupported: true,
                accentColorSupported: true,
            ),
            variants: ['default', 'minimal'],
        );
    }
}
