<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;

/**
 * Builds a language-tabbed input group for one `*_translations` JSON column
 * (App\Models\Concerns\HasTranslations) — one tab per language documented
 * in CLAUDE.md's "Ko'p tillilik" section (uz/en/ru/ko/fr/zh), each holding
 * a single field bound to `{column}.{code}` (Filament/Livewire resolves
 * this dot path against the array-cast attribute automatically). Only
 * `uz` is required, matching HasTranslations::translate()'s fallback
 * chain (requested locale → uz → first available).
 */
class TranslatableTabs
{
    private const LANGUAGES = [
        'uz' => "O'zbekcha",
        'en' => 'Inglizcha',
        'ru' => 'Ruscha',
        'ko' => 'Koreyscha',
        'fr' => 'Fransuzcha',
        'zh' => 'Xitoycha',
    ];

    public static function input(string $column, string $label): Tabs
    {
        return self::build($column, $label, fn (string $field, string $tabLabel) => TextInput::make($field)
            ->label($tabLabel)
            ->required($field === "{$column}.uz")
            ->maxLength(255)
            ->dehydrateStateUsing(self::emptyToNull(...)));
    }

    public static function textarea(string $column, string $label): Tabs
    {
        return self::build($column, $label, fn (string $field, string $tabLabel) => Textarea::make($field)
            ->label($tabLabel)
            ->rows(3)
            ->dehydrateStateUsing(self::emptyToNull(...)));
    }

    /**
     * A left-blank language tab must save as null, not "" — otherwise
     * HasTranslations::translate()'s `$translations[$locale] ?? $fallback`
     * never falls back (an empty string satisfies the array key, so `??`
     * doesn't trigger), and a customer in that locale would see a blank
     * name/description instead of the intended uz fallback.
     */
    private static function emptyToNull(?string $state): ?string
    {
        return filled($state) ? $state : null;
    }

    private static function build(string $column, string $label, \Closure $makeField): Tabs
    {
        return Tabs::make($label)
            ->contained(false)
            ->tabs(array_map(
                fn (string $code, string $name) => Tab::make($name)
                    ->schema([$makeField("{$column}.{$code}", "{$label} ({$name})")]),
                array_keys(self::LANGUAGES),
                self::LANGUAGES,
            ));
    }
}
