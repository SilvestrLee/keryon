<?php

namespace App\Enums;

enum ChurchOnboardingStep: string
{
    case WELCOME = 'welcome';
    case IDENTITY = 'identity';
    case BRAND = 'brand';
    case SERVICE_TIMES = 'service_times';
    case DIGITAL_PRESENCE = 'digital_presence';
    case COMPLETE = 'complete';

    public function label(): string
    {
        return match ($this) {
            self::WELCOME => 'Welcome',
            self::IDENTITY => 'Church essentials',
            self::BRAND => 'Brand',
            self::SERVICE_TIMES => 'Service times',
            self::DIGITAL_PRESENCE => 'Digital presence',
            self::COMPLETE => 'Finish',
        };
    }

    public function next(): self
    {
        return match ($this) {
            self::WELCOME => self::IDENTITY,
            self::IDENTITY => self::BRAND,
            self::BRAND => self::SERVICE_TIMES,
            self::SERVICE_TIMES => self::DIGITAL_PRESENCE,
            self::DIGITAL_PRESENCE, self::COMPLETE => self::COMPLETE,
        };
    }
}
