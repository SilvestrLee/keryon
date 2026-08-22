<?php

namespace App\Enums;

enum DesignOutputFormat: string
{
    case SQUARE = 'square';
    case PORTRAIT = 'portrait';
    case STORY = 'story';

    public function width(): int
    {
        return 1080;
    }

    public function height(): int
    {
        return match ($this) {
            self::SQUARE => 1080,
            self::PORTRAIT => 1350,
            self::STORY => 1920,
        };
    }
}
