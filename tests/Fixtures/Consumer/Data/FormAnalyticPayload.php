<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data;

use Carbon\Carbon;
use Illuminate\Validation\Rule;
use Nvl\Forms\Definitions\Tables\FormsTables;
use Nvl\Forms\Enums\FormAnalyticEventType;
use Nvl\Forms\Models\FormAnalytic;
use Nvl\Forms\Tests\Fixtures\Consumer\TranslatedValidation;

/**
 * Transfer object encapsulating individual form analytic events.
 */
class FormAnalyticPayload
{
    use TranslatedValidation;

    /**
     * Create the form analytic data transfer object.
     *
     * @param  string  $id  Analytic identifier
     * @param  string  $formId  Parent form identifier
     * @param  FormAnalyticEventType  $eventType  Analytic event type
     * @param  string|null  $origin  Origin host
     * @param  string|null  $ipAddress  Visitor IP address
     * @param  string|null  $userAgent  Visitor user agent
     * @param  string|null  $sessionId  Session identifier
     * @param  array<string, mixed>|null  $metadata  Analytics metadata
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
         * @var FormAnalyticEventType
         */
        public readonly FormAnalyticEventType $eventType,
        /**
         * @var string|null
         */
        public readonly ?string $origin,
        /**
         * @var string|null
         */
        public readonly ?string $ipAddress,
        /**
         * @var string|null
         */
        public readonly ?string $userAgent,
        /**
         * @var string|null
         */
        public readonly ?string $sessionId,
        /**
         * @var array<string, mixed>|null
         */
        public readonly ?array $metadata,
        /**
         * @var FormPayload|null
         */
        public readonly ?FormPayload $form,
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
     * Create FormAnalytic from FormAnalytic model.
     *
     * @param  FormAnalytic  $formAnalytic  The form analytic model instance
     * @return self The form analytic data instance
     */
    public static function fromModel(FormAnalytic $formAnalytic): self
    {
        return new self(id: $formAnalytic->id, formId: $formAnalytic->form_id, eventType: $formAnalytic->event_type, origin: $formAnalytic->origin, ipAddress: $formAnalytic->ip_address, userAgent: $formAnalytic->user_agent, sessionId: $formAnalytic->session_id, metadata: $formAnalytic->metadata, form: $formAnalytic->relationLoaded('form') && $formAnalytic->form !== null ? FormPayload::fromModel($formAnalytic->form) : null, createdAt: $formAnalytic->created_at, updatedAt: $formAnalytic->updated_at, formLoaded: $formAnalytic->relationLoaded('form'));
    }

    /**
     * Get validation rules for form analytic data.
     *
     * @return array<string, array<int, mixed>> Validation rules
     */
    public static function rules(): array
    {
        return ['formId' => ['required', 'uuid', 'exists:'.FormsTables::get(FormsTables::Forms).',id'], 'eventType' => ['required', Rule::enum(FormAnalyticEventType::class)], 'origin' => ['nullable', 'string', 'max:255'], 'ipAddress' => ['nullable', 'ip'], 'userAgent' => ['nullable', 'string', 'max:500'], 'sessionId' => ['nullable', 'string', 'max:100'], 'metadata' => ['nullable', 'array'], 'metadata.*' => ['nullable']];
    }

    /**
     * Get the default wrapper key for the data.
     *
     * @return string The wrapper key
     */
    public function defaultWrap(): string
    {
        return 'form_analytic';
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
            'eventType' => $this->eventType->value,
            'origin' => $this->origin,
            'ipAddress' => $this->ipAddress,
            'userAgent' => $this->userAgent,
            'sessionId' => $this->sessionId,
            'metadata' => $this->metadata,
            ...($this->formLoaded ? ['form' => $this->form?->toArray()] : []),
            'createdAt' => $this->createdAt?->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt?->format(DATE_ATOM),
        ];
    }
}
