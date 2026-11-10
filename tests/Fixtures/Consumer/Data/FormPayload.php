<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data;

use Carbon\Carbon;
use Nvl\Forms\Enums\FormStatus;
use Nvl\Forms\Enums\FormType;
use Nvl\Forms\Enums\Resolvement;
use Nvl\Forms\Models\Form;

/**
 * Read-only transfer object for form display and listing.
 *
 * For create/update mutations, use MutateFormPayload instead.
 *
 * @api
 */
class FormPayload
{
    /**
     * Create the form configuration data transfer object.
     *
     * @param  string  $id  Form identifier
     * @param  string  $name  Form name
     * @param  string|null  $description  Form description
     * @param  string|null  $handle  Form handle
     * @param  array<string, mixed>|null  $translations  Custom translation data
     * @param  FormStatus  $status  Form status
     * @param  Resolvement  $resolvement  Submission resolvement strategy
     * @param  FormType  $type  Form type
     * @param  int  $revision  Optimistic concurrency revision
     * @param  bool  $restrictPublicAccess  Public access restriction flag
     * @param  bool  $allowMultipleRegistrations  Multiple registration flag
     * @param  bool  $dateRestricted  Date restriction flag
     * @param  Carbon|null  $availableFrom  Availability start
     * @param  Carbon|null  $availableUntil  Availability end
     * @param  int  $submissionsCount  Total submissions count
     * @param  int  $viewsCount  Total views count
     * @param  int  $spamCount  Total spam count
     * @param  Carbon|null  $lastUsedAt  Last used timestamp
     * @param  Carbon|null  $firstUsedAt  First used timestamp
     * @param  bool  $enableHoneypot  Honeypot flag
     * @param  bool  $enableRateLimiting  Rate limiting flag
     * @param  int|null  $rateLimitPerHour  Rate limit per hour
     * @param  bool  $requireCsrf  CSRF requirement flag
     * @param  FormCorsSettings|null  $corsSettings  CORS settings
     * @param  array<string, mixed>|null  $options  Display, styling and behavioral options
     * @param  array<int, string>|null  $allowedOrigins  Allowed origin hosts/patterns for embedding
     * @param  Carbon|null  $createdAt  Creation timestamp
     * @param  Carbon|null  $updatedAt  Update timestamp
     */
    public function __construct(
        /**
         * @var string $id
         */
        public readonly string $id,
        /**
         * @var string
         */
        public readonly string $name,
        /**
         * @var string|null
         */
        public readonly ?string $description,
        /**
         * @var string|null
         */
        public readonly ?string $handle,
        /**
         * @var array<string, mixed>|null
         */
        public readonly ?array $translations,
        /**
         * @var FormStatus
         */
        public readonly FormStatus $status = FormStatus::DRAFT,
        /**
         * @var Resolvement
         */
        public readonly Resolvement $resolvement = Resolvement::ENTRIES,
        /**
         * @var FormType
         */
        public readonly FormType $type = FormType::LANDING_PAGE,
        public readonly int $revision = 1,
        /**
         * @var bool
         */
        public readonly bool $restrictPublicAccess = false,
        /**
         * @var bool
         */
        public readonly bool $allowMultipleRegistrations = true,
        /**
         * @var bool
         */
        public readonly bool $dateRestricted = false,
        /**
         * @var Carbon|null
         */
        public readonly ?Carbon $availableFrom = null,
        /**
         * @var Carbon|null
         */
        public readonly ?Carbon $availableUntil = null,
        /**
         * @var int
         */
        public readonly int $submissionsCount = 0,
        /**
         * @var int
         */
        public readonly int $viewsCount = 0,
        /**
         * @var int
         */
        public readonly int $spamCount = 0,
        /**
         * @var Carbon|null
         */
        public readonly ?Carbon $lastUsedAt = null,
        /**
         * @var Carbon|null
         */
        public readonly ?Carbon $firstUsedAt = null,
        /**
         * @var bool
         */
        public readonly bool $enableHoneypot = true,
        /**
         * @var bool
         */
        public readonly bool $enableRateLimiting = true,
        /**
         * @var int|null
         */
        public readonly ?int $rateLimitPerHour = 10,
        /**
         * @var bool
         */
        public readonly bool $requireCsrf = true,
        /** @var FormCorsSettings|null */
        public readonly ?FormCorsSettings $corsSettings = null,
        /**
         * @var array<string, mixed>|null
         */
        public readonly ?array $options = null,
        /**
         * @var array<int, string>|null
         */
        public readonly ?array $allowedOrigins = null,
        /**
         * @var Carbon|null
         */
        public readonly ?Carbon $createdAt = null,
        /**
         * @var Carbon|null
         */
        public readonly ?Carbon $updatedAt = null
    ) {}

    /**
     * Create FormPayload from Form model.
     *
     * @param  Form  $form  The form model instance
     * @return self The form data instance
     *
     * @internal
     */
    public static function fromModel(Form $form): self
    {
        $allowedOrigins = null;
        if ($form->relationLoaded('allowedOrigins')) {
            $allowedOrigins = $form->allowedOrigins->where('is_active', true)->pluck('origin')->values()->all();
        }

        return new self(id: $form->id, name: $form->displayName(), description: $form->displayDescription(), handle: $form->handle, translations: $form->translationPayloads(), status: $form->status ?? FormStatus::DRAFT, resolvement: $form->resolvement ?? Resolvement::ENTRIES, type: $form->type ?? FormType::LANDING_PAGE, revision: $form->revision, restrictPublicAccess: $form->restrict_public_access ?? false, allowMultipleRegistrations: $form->allowsMultipleRegistrations(), dateRestricted: $form->date_restricted ?? false, availableFrom: $form->available_from, availableUntil: $form->available_until, submissionsCount: $form->submissions_count ?? 0, viewsCount: $form->views_count ?? 0, spamCount: $form->spam_count ?? 0, lastUsedAt: $form->last_used_at, firstUsedAt: $form->first_used_at, enableHoneypot: $form->enable_honeypot ?? true, enableRateLimiting: $form->enable_rate_limiting ?? true, rateLimitPerHour: $form->rate_limit_per_hour ?? 10, requireCsrf: $form->require_csrf ?? true, corsSettings: is_array($form->cors_settings) ? FormCorsSettings::fromStored($form->cors_settings) : null, options: $form->options, allowedOrigins: $allowedOrigins === null ? null : array_values(array_filter($allowedOrigins, static fn (mixed $origin): bool => is_string($origin))), createdAt: $form->created_at, updatedAt: $form->updated_at);
    }

    /** @return array<string, mixed> Consumer-facing response fields. */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'handle' => $this->handle,
            'translations' => $this->translations,
            'status' => $this->status->value,
            'resolvement' => $this->resolvement->value,
            'type' => $this->type->value,
            'revision' => $this->revision,
            'restrictPublicAccess' => $this->restrictPublicAccess,
            'allowMultipleRegistrations' => $this->allowMultipleRegistrations,
            'dateRestricted' => $this->dateRestricted,
            'availableFrom' => $this->availableFrom?->format(DATE_ATOM),
            'availableUntil' => $this->availableUntil?->format(DATE_ATOM),
            'submissionsCount' => $this->submissionsCount,
            'viewsCount' => $this->viewsCount,
            'spamCount' => $this->spamCount,
            'lastUsedAt' => $this->lastUsedAt?->format(DATE_ATOM),
            'firstUsedAt' => $this->firstUsedAt?->format(DATE_ATOM),
            'enableHoneypot' => $this->enableHoneypot,
            'enableRateLimiting' => $this->enableRateLimiting,
            'rateLimitPerHour' => $this->rateLimitPerHour,
            'requireCsrf' => $this->requireCsrf,
            'corsSettings' => $this->corsSettings?->toArray(),
            'options' => $this->options,
            'allowedOrigins' => $this->allowedOrigins,
            'createdAt' => $this->createdAt?->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt?->format(DATE_ATOM),
        ];
    }
}
