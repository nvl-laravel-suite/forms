<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data;

/**
 * Display, styling and behavioral options for public form rendering.
 *
 * Stored as JSON in the `options` column of the forms table.
 * All properties are optional — absent means "use platform default".
 */
class FormOptions
{
    /**
     * @param  bool  $showHeader  Whether to show the form title and intro text
     * @param  bool  $showLogo  Whether to show the platform logo/branding
     * @param  string|null  $submitButtonLabel  Custom submit button text (overrides translation)
     * @param  string|null  $successRedirectUrl  Redirect URL after successful submission (instead of thank-you page)
     * @param  string|null  $theme  Visual theme: light, dark, brand
     * @param  string|null  $maxWidth  Max card width: sm, md, lg, xl, 2xl
     * @param  string|null  $backgroundColor  Custom background color (hex or Tailwind token)
     * @param  string|null  $accentColor  Custom accent color for buttons and links (hex or Tailwind token)
     * @param  bool  $showPoweredBy  Whether to show "Powered by" footer badge
     * @param  bool  $compactLayout  Use reduced spacing and padding
     */
    public function __construct(public readonly bool $showHeader = true, public readonly bool $showLogo = false, public readonly ?string $submitButtonLabel = null, public readonly ?string $successRedirectUrl = null, public readonly ?string $theme = null, public readonly ?string $maxWidth = null, public readonly ?string $backgroundColor = null, public readonly ?string $accentColor = null, public readonly bool $showPoweredBy = true, public readonly bool $compactLayout = false) {}

    /** @param array<string, mixed> $options Persisted public rendering options. */
    public static function fromStored(array $options): self
    {
        return new self(
            showHeader: (bool) ($options['showHeader'] ?? true),
            showLogo: (bool) ($options['showLogo'] ?? false),
            submitButtonLabel: $options['submitButtonLabel'] ?? null,
            successRedirectUrl: $options['successRedirectUrl'] ?? null,
            theme: $options['theme'] ?? null,
            maxWidth: $options['maxWidth'] ?? null,
            backgroundColor: $options['backgroundColor'] ?? null,
            accentColor: $options['accentColor'] ?? null,
            showPoweredBy: (bool) ($options['showPoweredBy'] ?? true),
            compactLayout: (bool) ($options['compactLayout'] ?? false),
        );
    }

    /** @return array<string, mixed> Consumer-facing response fields. */
    public function toArray(): array
    {
        return [
            'showHeader' => $this->showHeader,
            'showLogo' => $this->showLogo,
            'submitButtonLabel' => $this->submitButtonLabel,
            'successRedirectUrl' => $this->successRedirectUrl,
            'theme' => $this->theme,
            'maxWidth' => $this->maxWidth,
            'backgroundColor' => $this->backgroundColor,
            'accentColor' => $this->accentColor,
            'showPoweredBy' => $this->showPoweredBy,
            'compactLayout' => $this->compactLayout,
        ];
    }
}
