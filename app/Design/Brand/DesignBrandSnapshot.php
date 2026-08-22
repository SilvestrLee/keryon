<?php

namespace App\Design\Brand;

use App\Models\ChurchBrandProfile;

class DesignBrandSnapshot
{
    /** @return array<string, string|null> */
    public function from(?ChurchBrandProfile $profile): array
    {
        $background = $profile?->primary_color ?? '#1E5631';
        $accent = $profile?->accent_color ?? $profile?->secondary_color ?? '#D4AF37';

        if ($this->contrast($background, $accent) < 3.0) {
            $accent = $this->contrast($background, '#FFFFFF') >= 3.0 ? '#FFFFFF' : '#111827';
        }

        return [
            'background' => $background,
            'primary_text' => $this->readableText($background),
            'emphasis' => $accent,
            'accent' => $accent,
            'cta_background' => $accent,
            'cta_text' => $this->readableText($accent),
            'heading_font' => $profile?->heading_font?->value ?? 'inter',
            'body_font' => $profile?->body_font?->value ?? 'inter',
        ];
    }

    private function readableText(string $background): string
    {
        return $this->contrast($background, '#FFFFFF') >= 4.5 ? '#FFFFFF' : '#111827';
    }

    private function contrast(string $first, string $second): float
    {
        $lighter = max($this->luminance($first), $this->luminance($second));
        $darker = min($this->luminance($first), $this->luminance($second));

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private function luminance(string $color): float
    {
        $channels = array_map(
            fn (string $channel): float => hexdec($channel) / 255,
            str_split(ltrim($color, '#'), 2),
        );

        $channels = array_map(
            fn (float $channel): float => $channel <= 0.04045
                ? $channel / 12.92
                : (($channel + 0.055) / 1.055) ** 2.4,
            $channels,
        );

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }
}
