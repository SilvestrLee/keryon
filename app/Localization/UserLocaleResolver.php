<?php

namespace App\Localization;

use App\Models\User;
use Illuminate\Validation\ValidationException;

final class UserLocaleResolver
{
    /** @return array<string, array{label: string, self_label: string}> */
    public function supported(): array
    {
        return config('keryon.locales', []);
    }

    public function resolve(?User $user): string
    {
        foreach ([$user?->locale, session('locale'), config('app.locale')] as $locale) {
            if (is_string($locale) && array_key_exists($locale, $this->supported())) {
                return $locale;
            }
        }

        return (string) config('app.fallback_locale', 'en');
    }

    public function select(User $user, string $locale): void
    {
        if (! array_key_exists($locale, $this->supported())) {
            throw ValidationException::withMessages(['locale' => 'The selected language is not supported.']);
        }

        $user->forceFill(['locale' => $locale])->save();
        session(['locale' => $locale]);
        app()->setLocale($locale);
    }
}
