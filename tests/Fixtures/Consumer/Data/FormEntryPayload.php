<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Data;

use Carbon\Carbon;
use Nvl\Forms\Models\FormEntry;

/**
 * Read-only transfer object for form entry display and listing.
 *
 * For entry creation mutations, use MutateFormEntryPayload instead.
 *
 * @api
 */
class FormEntryPayload
{
    /**
     * Create the form entry data transfer object.
     *
     * @param  string  $id  Entry identifier
     * @param  string  $formId  Parent form identifier
     * @param  string|null  $subject  Entry subject
     * @param  string|null  $email  Contact email
     * @param  string|null  $firstName  Contact first name
     * @param  string|null  $lastName  Contact last name
     * @param  string|null  $phone  Contact phone
     * @param  string|null  $address  Contact address
     * @param  string|null  $body  Entry body
     * @param  array<string, mixed>|null  $submissionData  Raw submission payload
     * @param  string  $submittedFrom  Submission origin
     * @param  string|null  $ipAddress  Request IP address
     * @param  string|null  $userAgent  Request user agent
     * @param  string|null  $sessionId  Session identifier
     * @param  bool  $isSpam  Spam flag
     * @param  int|null  $spamScore  Spam score from zero to one hundred
     * @param  array<string, mixed>|null  $securityFlags  Security flags
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
         * @var string|null
         */
        public readonly ?string $subject,
        /**
         * @var string|null
         */
        public readonly ?string $email,
        /**
         * @var string|null
         */
        public readonly ?string $firstName,
        /**
         * @var string|null
         */
        public readonly ?string $lastName,
        /**
         * @var string|null
         */
        public readonly ?string $phone,
        /**
         * @var string|null
         */
        public readonly ?string $address,
        /**
         * @var string|null
         */
        public readonly ?string $body,
        /**
         * @var array<string, mixed>|null
         */
        public readonly ?array $submissionData,
        /**
         * @var string
         */
        public readonly string $submittedFrom,
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
         * @var bool
         */
        public readonly bool $isSpam = false,
        /**
         * @var int|null
         */
        public readonly ?int $spamScore = null,
        /**
         * @var array<string, mixed>|null
         */
        public readonly ?array $securityFlags = null,
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
     * Create FormEntryPayload from FormEntry model.
     *
     * @param  FormEntry  $formEntry  The form entry model instance
     * @return self The form entry data instance
     *
     * @internal
     */
    public static function fromModel(FormEntry $formEntry): self
    {
        return new self(id: $formEntry->id, formId: $formEntry->form_id, subject: $formEntry->subject, email: $formEntry->email, firstName: $formEntry->first_name, lastName: $formEntry->last_name, phone: $formEntry->phone, address: $formEntry->address, body: $formEntry->body, submissionData: $formEntry->submission_data, submittedFrom: $formEntry->submitted_from, ipAddress: $formEntry->ip_address, userAgent: $formEntry->user_agent, sessionId: $formEntry->session_id, isSpam: $formEntry->is_spam ?? false, spamScore: $formEntry->spam_score, securityFlags: $formEntry->security_flags, form: $formEntry->relationLoaded('form') && $formEntry->form !== null ? FormPayload::fromModel($formEntry->form) : null, createdAt: $formEntry->created_at, updatedAt: $formEntry->updated_at, formLoaded: $formEntry->relationLoaded('form'));
    }

    /** @return array<string, mixed> Consumer-facing response fields. */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'formId' => $this->formId,
            'subject' => $this->subject,
            'email' => $this->email,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'phone' => $this->phone,
            'address' => $this->address,
            'body' => $this->body,
            'submissionData' => $this->submissionData,
            'submittedFrom' => $this->submittedFrom,
            'ipAddress' => $this->ipAddress,
            'userAgent' => $this->userAgent,
            'sessionId' => $this->sessionId,
            'isSpam' => $this->isSpam,
            'spamScore' => $this->spamScore,
            'securityFlags' => $this->securityFlags,
            ...($this->formLoaded ? ['form' => $this->form?->toArray()] : []),
            'createdAt' => $this->createdAt?->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt?->format(DATE_ATOM),
        ];
    }
}
