<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data;

use Nvl\Forms\Enums\CorsPolicy;

/**
 * Validated CORS behavior for a form or allowed-origin override.
 *
 * @api
 */
final class FormCorsSettings
{
    /**
     * @param  CorsPolicy  $policy  Named policy baseline
     * @param  bool  $allowCredentials  Whether credentialed cross-origin requests are allowed
     * @param  bool  $allowWildcards  Whether wildcard header/origin behavior is allowed
     * @param  int  $maxAge  Browser preflight cache duration in seconds
     * @param  list<string>  $allowedMethods  Allowed cross-origin methods
     * @param  list<string>  $allowedHeaders  Allowed cross-origin request headers
     */
    public function __construct(
        public readonly CorsPolicy $policy = CorsPolicy::Moderate,
        public readonly bool $allowCredentials = true,
        public readonly bool $allowWildcards = false,
        public readonly int $maxAge = 600,
        /** @var list<string> */
        public readonly array $allowedMethods = ['GET', 'POST', 'OPTIONS'],
        /** @var list<string> */
        public readonly array $allowedHeaders = ['Content-Type', 'X-CSRF-TOKEN', 'X-XSRF-TOKEN', 'X-Form-Origin', 'X-Forms-Public-Token', 'Idempotency-Key']
    ) {}

    /**
     * Read the application's persisted CORS shape without loading a DTO library.
     *
     * @param  array<string, mixed>  $settings
     */
    public static function fromStored(array $settings): self
    {
        return new self(
            policy: ($settings['policy'] ?? null) instanceof CorsPolicy ? $settings['policy'] : CorsPolicy::from($settings['policy'] ?? CorsPolicy::Moderate->value),
            allowCredentials: (bool) ($settings['allowCredentials'] ?? true),
            allowWildcards: (bool) ($settings['allowWildcards'] ?? false),
            maxAge: (int) ($settings['maxAge'] ?? 600),
            allowedMethods: $settings['allowedMethods'] ?? ['GET', 'POST', 'OPTIONS'],
            allowedHeaders: $settings['allowedHeaders'] ?? ['Content-Type', 'X-CSRF-TOKEN', 'X-XSRF-TOKEN', 'X-Form-Origin', 'X-Forms-Public-Token', 'Idempotency-Key'],
        );
    }

    /** @return array<string, mixed> Consumer-facing response fields. */
    public function toArray(): array
    {
        return [
            'policy' => $this->policy->value,
            'allowCredentials' => $this->allowCredentials,
            'allowWildcards' => $this->allowWildcards,
            'maxAge' => $this->maxAge,
            'allowedMethods' => $this->allowedMethods,
            'allowedHeaders' => $this->allowedHeaders,
        ];
    }
}
