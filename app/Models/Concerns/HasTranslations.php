<?php

namespace App\Models\Concerns;

trait HasTranslations
{
    /**
     * Resolve a translated value from a JSON translations attribute,
     * falling back to $fallback locale, then to the first available value.
     */
    public function translate(string $attribute, string $locale, string $fallback = 'uz'): ?string
    {
        $translations = $this->{$attribute};

        if (! is_array($translations) || $translations === []) {
            return null;
        }

        return $translations[$locale]
            ?? $translations[$fallback]
            ?? reset($translations);
    }
}
