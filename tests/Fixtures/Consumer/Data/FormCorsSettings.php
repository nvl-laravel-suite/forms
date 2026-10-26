<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data;

use Nvl\Forms\Enums\CorsPolicy;
use Nvl\Forms\Tests\Fixtures\Consumer\DataTransform;
use Spatie\LaravelData\Attributes\MapInputName;
use Spatie\LaravelData\Attributes\MapOutputName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\CamelCaseMapper;

/**
 * Validated CORS behavior for a form or allowed-origin override.
 *
 * @api
 */
#[MapInputName(CamelCaseMapper::class)]
#[MapOutputName(CamelCaseMapper::class)]
final class FormCorsSettings extends Data
{
    use DataTransform;

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
}
