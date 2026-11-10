<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer;

use Illuminate\Support\Str;

/** Localized field labels and messages shared by consumer-owned validation rules. */
trait TranslatedValidation
{
    /**
     * Load validation attribute names from module translations and augment with camelCase aliases.
     *
     * @param  string  $namespace  Translation namespace, such as 'articles::articles'
     * @return array<string, string>
     */
    public static function translatedAttributes(string $namespace): array
    {
        $key = str_contains($namespace, '/validation') || str_ends_with($namespace, '::validation') ? $namespace.'.attributes' : $namespace.'/validation.attributes';
        $attributes = trans($key);
        if (! is_array($attributes)) {
            return [];
        }
        $camelAliased = [];
        foreach ($attributes as $key => $label) {
            if (! is_string($key) || ! is_string($label)) {
                continue;
            }
            $camel = Str::camel($key);
            $camelAliased[$key] = $label;
            $camelAliased[$camel] = $label;
        }

        return $camelAliased;
    }

    /**
     * Load custom validation messages from module translations.
     *
     * @param  string  $namespace  Translation namespace, such as 'articles::articles'
     * @return array<string, mixed>
     */
    public static function translatedMessages(string $namespace): array
    {
        $key = str_contains($namespace, '/validation') || str_ends_with($namespace, '::validation') ? $namespace.'.custom' : $namespace.'/validation.custom';
        $custom = trans($key);
        if (! is_array($custom)) {
            return [];
        }

        /** @var array<string, mixed> $custom */
        return $custom;
    }
}
