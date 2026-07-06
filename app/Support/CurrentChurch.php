<?php

namespace App\Support;

class CurrentChurch
{
    public static function name(string $fallback = 'your church'): string
    {
        return auth()->user()?->church?->name ?: $fallback;
    }

    public static function possessiveName(string $fallback = 'your church'): string
    {
        $name = self::name($fallback);

        return str_ends_with($name, 's') ? "{$name}'" : "{$name}'s";
    }
}
