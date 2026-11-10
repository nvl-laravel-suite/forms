<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data;

use Carbon\Carbon;
use Nvl\Forms\Definitions\Tables\FormsTables;
use Nvl\Forms\Models\FormRateLimit;
use Nvl\Forms\Tests\Fixtures\Consumer\TranslatedValidation;

/**
 * Transfer object representing form rate limit state per IP.
 */
class FormRateLimitPayload
{
    use TranslatedValidation;

    /**
     * Create the form rate limit data transfer object.
     *
     * @param  string  $id  Rate limit identifier
     * @param  string  $formId  Parent form identifier
     * @param  string  $ipAddress  Request IP address
     * @param  Carbon  $windowStart  Rate limit window start
     * @param  Carbon  $lastSubmissionAt  Last submission timestamp
     * @param  int  $submissionCount  Submission count in window
     * @param  bool  $isBlocked  Blocked flag
     * @param  Carbon|null  $blockedUntil  Blocked until timestamp
     * @param  int  $violationCount  Violation count
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
        public readonly string $ipAddress,
        /**
         * @var Carbon
         */
        public readonly Carbon $windowStart,
        /**
         * @var Carbon
         */
        public readonly Carbon $lastSubmissionAt,
        /**
         * @var int
         */
        public readonly int $submissionCount = 0,
        /**
         * @var bool
         */
        public readonly bool $isBlocked = false,
        /**
         * @var Carbon|null
         */
        public readonly ?Carbon $blockedUntil = null,
        /**
         * @var int
         */
        public readonly int $violationCount = 0,
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
     * Create FormRateLimit from FormRateLimit model.
     *
     * @param  FormRateLimit  $formRateLimit  The form rate limit model instance
     * @return self The form rate limit data instance
     */
    public static function fromModel(FormRateLimit $formRateLimit): self
    {
        return new self(id: $formRateLimit->id, formId: $formRateLimit->form_id, ipAddress: $formRateLimit->ip_address, windowStart: $formRateLimit->window_start, lastSubmissionAt: $formRateLimit->last_submission_at, submissionCount: $formRateLimit->submission_count ?? 0, isBlocked: $formRateLimit->is_blocked ?? false, blockedUntil: $formRateLimit->blocked_until, violationCount: $formRateLimit->violation_count ?? 0, form: $formRateLimit->relationLoaded('form') && $formRateLimit->form !== null ? FormPayload::fromModel($formRateLimit->form) : null, createdAt: $formRateLimit->created_at, updatedAt: $formRateLimit->updated_at, formLoaded: $formRateLimit->relationLoaded('form'));
    }

    /**
     * Get validation rules for form rate limit data.
     *
     * @return array<string, array<int, mixed>> Validation rules
     */
    public static function rules(): array
    {
        return ['formId' => ['required', 'uuid', 'exists:'.FormsTables::get(FormsTables::Forms).',id'], 'ipAddress' => ['required', 'ip'], 'submissionCount' => ['sometimes', 'integer', 'min:0'], 'windowStart' => ['required', 'date'], 'lastSubmissionAt' => ['required', 'date'], 'isBlocked' => ['boolean'], 'blockedUntil' => ['nullable', 'date', 'after_or_equal:windowStart'], 'violationCount' => ['sometimes', 'integer', 'min:0']];
    }

    /**
     * Get the default wrapper key for the data.
     *
     * @return string The wrapper key
     */
    public function defaultWrap(): string
    {
        return 'form_rate_limit';
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
            'ipAddress' => $this->ipAddress,
            'windowStart' => $this->windowStart->format(DATE_ATOM),
            'lastSubmissionAt' => $this->lastSubmissionAt->format(DATE_ATOM),
            'submissionCount' => $this->submissionCount,
            'isBlocked' => $this->isBlocked,
            'blockedUntil' => $this->blockedUntil?->format(DATE_ATOM),
            'violationCount' => $this->violationCount,
            ...($this->formLoaded ? ['form' => $this->form?->toArray()] : []),
            'createdAt' => $this->createdAt?->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt?->format(DATE_ATOM),
        ];
    }
}
