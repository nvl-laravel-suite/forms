<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data;

use Carbon\Carbon;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Validation\Rule;
use Nvl\Forms\Definitions\Tables\FormsTables;
use Nvl\Forms\Enums\CorsPolicy;
use Nvl\Forms\Models\AllowedOrigin;
use Nvl\Forms\Rules\AllowedOriginExpressionRule;
use Nvl\Forms\Tests\Fixtures\Consumer\TranslatedValidation;

/**
 * Transfer object for allowed origin records scoped to forms.
 */
class AllowedOriginPayload
{
    use TranslatedValidation;

    /**
     * Create the allowed origin data transfer object.
     *
     * @param  string  $id  Allowed origin identifier
     * @param  string  $formId  Parent form identifier
     * @param  string  $origin  Allowed origin host
     * @param  bool  $isActive  Active flag
     * @param  string|null  $description  Optional description
     * @param  FormCorsSettings|null  $corsSettings  CORS configuration overrides
     * @param  int  $usageCount  Total usage count
     * @param  Carbon|null  $lastUsedAt  Timestamp of last usage
     * @param  FormPayload|null  $form  Related form data
     * @param  Carbon|null  $createdAt  Creation timestamp
     * @param  Carbon|null  $updatedAt  Update timestamp
     * @param  bool  $formLoaded  Whether the source explicitly loaded its form relation
     */
    public function __construct(
        /**
         * @var string $id
         */
        public readonly string $id,
        /**
         * @var string
         */
        public readonly string $formId,
        /**
         * @var string
         */
        public readonly string $origin,
        /**
         * @var bool
         */
        public readonly bool $isActive = true,
        /**
         * @var string|null
         */
        public readonly ?string $description = null,
        /** @var FormCorsSettings|null */
        public readonly ?FormCorsSettings $corsSettings = null,
        /**
         * @var int
         */
        public readonly int $usageCount = 0,
        /**
         * @var Carbon|null
         */
        public readonly ?Carbon $lastUsedAt = null,
        /**
         * @var FormPayload|null
         */
        public readonly ?FormPayload $form = null,
        /**
         * @var Carbon|null
         */
        public readonly ?Carbon $createdAt = null,
        /**
         * @var Carbon|null
         */
        public readonly ?Carbon $updatedAt = null,
        private readonly bool $formLoaded = true,
    ) {}

    /**
     * Create AllowedOrigin from AllowedOrigin model.
     *
     * @param  AllowedOrigin  $allowedOrigin  The allowed origin model instance
     * @return self The allowed origin data instance
     */
    public static function fromModel(AllowedOrigin $allowedOrigin): self
    {
        return new self(id: $allowedOrigin->id, formId: $allowedOrigin->form_id, origin: $allowedOrigin->origin, isActive: $allowedOrigin->is_active ?? true, description: $allowedOrigin->description, corsSettings: is_array($allowedOrigin->cors_settings) ? FormCorsSettings::fromStored($allowedOrigin->cors_settings) : null, usageCount: $allowedOrigin->usage_count ?? 0, lastUsedAt: $allowedOrigin->last_used_at, form: $allowedOrigin->relationLoaded('form') && $allowedOrigin->form !== null ? FormPayload::fromModel($allowedOrigin->form) : null, createdAt: $allowedOrigin->created_at, updatedAt: $allowedOrigin->updated_at, formLoaded: $allowedOrigin->relationLoaded('form'));
    }

    /**
     * Build validation rules from explicit resource and form context.
     *
     * @param  string|null  $allowedOriginId  Existing resource identifier for updates
     * @param  string|null  $formId  Parent form identifier for scoped uniqueness
     * @return array<string, array<int, mixed>> Validation rules
     */
    public static function rulesFor(?string $allowedOriginId, ?string $formId): array
    {
        $uniqueRule = Rule::unique(FormsTables::get(FormsTables::AllowedOrigins), 'origin')->ignore($allowedOriginId);
        if (is_string($formId) && $formId !== '') {
            $uniqueRule = $uniqueRule->where(static fn (QueryBuilder $query) => $query->where('form_id', $formId));
        }

        return ['formId' => ['sometimes', 'uuid', 'exists:'.FormsTables::get(FormsTables::Forms).',id'], 'origin' => ['required', 'string', 'max:255', new AllowedOriginExpressionRule, $uniqueRule], 'isActive' => ['boolean'], 'description' => ['nullable', 'string', 'max:500'], 'corsSettings' => ['nullable', 'array:policy,allowCredentials,allowWildcards,maxAge,allowedMethods,allowedHeaders'], 'corsSettings.policy' => [Rule::enum(CorsPolicy::class)], 'corsSettings.allowCredentials' => ['boolean'], 'corsSettings.allowWildcards' => ['boolean'], 'corsSettings.maxAge' => ['integer', 'min:0', 'max:86400'], 'corsSettings.allowedMethods' => ['array', 'min:1'], 'corsSettings.allowedMethods.*' => ['string', 'distinct', Rule::in(['GET', 'POST', 'OPTIONS'])], 'corsSettings.allowedHeaders' => ['array', 'min:1'], 'corsSettings.allowedHeaders.*' => ['string', 'distinct', 'regex:/^(?:\*|[A-Za-z0-9!#$%&\\\'*+.^_`|~-]+)$/'], 'usageCount' => ['sometimes', 'integer', 'min:0'], 'lastUsedAt' => ['nullable', 'date']];
    }

    /**
     * Get the default wrapper key for the data.
     *
     * @return string The wrapper key
     */
    public function defaultWrap(): string
    {
        return 'allowed_origin';
    }

    /**
     * Custom validation messages sourced from module translations.
     *
     * @return array<string, mixed>
     */
    public static function messages(): array
    {
        return self::translatedMessages('nvl-forms::forms');
    }

    /**
     * Attribute name mappings sourced from module translations.
     *
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return self::translatedAttributes('nvl-forms::forms');
    }

    /** @return array<string, mixed> Consumer-facing response fields. */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'formId' => $this->formId,
            'origin' => $this->origin,
            'isActive' => $this->isActive,
            'description' => $this->description,
            'corsSettings' => $this->corsSettings?->toArray(),
            'usageCount' => $this->usageCount,
            'lastUsedAt' => $this->lastUsedAt?->format(DATE_ATOM),
            ...($this->formLoaded ? ['form' => $this->form?->toArray()] : []),
            'createdAt' => $this->createdAt?->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt?->format(DATE_ATOM),
        ];
    }
}
