<?php

declare(strict_types=1);

namespace Nvl\Forms\Services;

use Closure;
use Exception;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Nvl\Forms\Contracts\FormRateLimiter;
use Nvl\Forms\Contracts\FormSpamDetector;
use Nvl\Forms\Enums\FormAnalyticEventType;
use Nvl\Forms\Enums\Resolvement;
use Nvl\Forms\Events\FormEntryChanged;
use Nvl\Forms\Exceptions\FormException;
use Nvl\Forms\Exceptions\FormSubmissionRejectionException;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Forms\Models\FormSubmissionReceipt;
use Nvl\Forms\Ownership\FormsOwnership;
use Nvl\Forms\Rules\BoundedSubmissionPayload;
use Nvl\Ownership\Services\PublicOwnerSiteRunner;
use Nvl\Support\Events\ConnectionCommitCallbacks;
use Nvl\Support\Events\DomainEventDispatcher;
use Nvl\Support\Ownership\Contracts\OwnershipBoundary;
use Nvl\Support\Ownership\Contracts\OwnershipContext;
use Nvl\Support\Ownership\Exceptions\OwnershipViolation;
use Nvl\Support\Ownership\Services\OwnerSiteContext;
use Nvl\Translatable\Services\ContentLocale;
use Throwable;

/**
 * Native Forms workflow family.
 *
 * @phpstan-type SubmissionFields array{subject?:?string,firstName?:?string,lastName?:?string,email?:?string,phone?:?string,address?:?string,body?:?string,submissionData?:array<string,mixed>|null}
 * @phpstan-type EntryFields array{formId:string,submittedFrom:string,subject?:?string,firstName?:?string,lastName?:?string,email?:?string,phone?:?string,address?:?string,body?:?string,submissionData?:array<string,mixed>|null}
 * @phpstan-type Protection array{origin:string,originHost:string,requestHost:string,ipAddress:string,userAgent:?string,sessionId:?string,session:?Session,csrfToken:?string,publicToken:?string,idempotency_key:?string,publicSite:string,actor:?Authenticatable,locale:string}
 * @phpstan-type Guard array{submittedFrom:string,ipAddress:string,userAgent:?string,sessionId:?string,handlerPayload:array<string,mixed>}
 *
 * @api
 */
final class FormSubmissions
{
    public function __construct(private readonly CustomFormRegistry $customRegistry, private readonly EntryCallbackRegistry $entryCallbacks, private readonly CustomSubmissionReceiptService $customReceipts, private PublicFormTokenService $tokenService, private readonly FormEntryLocator $entryLocator, private readonly Container $container, private readonly FormSpamDetector $spamDetection, private readonly FormSpamRejectionRecorder $spamRejectionRecorder, private readonly FormRegistrationFingerprint $registrationFingerprint, private readonly OwnershipBoundary $boundary, private DomainEventDispatcher $domainEvents, private readonly FormOriginAccessService $originAccess, private readonly FormRateLimiter $rateLimitService) {}

    /**
     * Execute the public submission flow.
     *
     * @param  Form|string  $formIdentifier  Form model, UUID, or handle
     * @param  SubmissionFields  $fields
     * @param  array<string,mixed>  $protection
     *
     * @throws Throwable
     */
    public function submit(Form|string $formIdentifier, array $fields, array $protection): FormEntry|FormSubmissionReceipt
    {
        $rules = self::fieldRules();
        $allowed = array_filter(array_keys($rules), static fn (string $field): bool => ! str_contains($field, '.'));
        if (array_diff(array_keys($fields), $allowed) !== []) {
            throw ValidationException::withMessages(['fields' => 'Unknown or protected submission fields are not accepted.']);
        }
        foreach ($fields as $value) {
            if (is_string($value) && (! mb_check_encoding($value, 'UTF-8') || str_contains($value, "\0"))) {
                throw ValidationException::withMessages(['fields' => 'Submission text must be valid UTF-8 without NUL bytes.']);
            }
        }
        Validator::make($fields, $rules)->validate();
        $data = $fields;
        $context = $this->protection($protection);
        if (FormsOwnership::declared()) {
            OwnerSiteContext::require();
            if ($this->entryCallbacks->hasNonOwnershipCallbacks()) {
                throw new FormException('Owned public submission requires registerOwnership callbacks with immutable owner facts.');
            }
        }
        $form = app(FormReader::class)->get($formIdentifier);
        if (! $form->isPubliclyAvailableNow()) {
            throw new FormSubmissionRejectionException(message: (string) trans('nvl-forms::forms/messages.api.form_unavailable'), statusCode: 403);
        }
        $this->validateProtection($form, $context);
        if (($form->resolvement ?? Resolvement::ENTRIES) === Resolvement::CUSTOM) {
            return $this->executeCustomSubmission($form, $data, $context);
        }

        return $this->executeEntrySubmission($form, $data, $context);
    }

    /**
     * Execute a submission through a custom handler.
     *
     * @param  Form  $form  Target form
     * @param  SubmissionFields  $data  Validated submission payload
     * @param  Protection  $context  Request-derived submission context
     *
     * @throws Throwable
     */
    private function executeCustomSubmission(Form $form, array $data, array $context): FormSubmissionReceipt
    {
        $handler = $this->customRegistry->resolve($form);
        if ($handler === null) {
            throw new FormSubmissionRejectionException(message: (string) trans('nvl-forms::forms/messages.api.error'), statusCode: 400);
        }
        $entryData = $this->prepare($form, $data, $context);
        $this->validateHost($form, $entryData['submittedFrom']);
        $handlerPayload = $this->buildHandlerPayload($entryData, $data);
        $replay = $this->customReceipts->findReplay($form, $handlerPayload, $context['idempotency_key']);
        if ($replay instanceof FormSubmissionReceipt) {
            return $replay;
        }
        $guard = $this->guardCustom($form, $data, $context);
        $claim = $this->customReceipts->claim($form, $guard['handlerPayload'], $guard['sessionId'], $context['idempotency_key']);
        if ($claim->isReplay === true) {
            return $claim->receipt;
        }
        try {
            $result = $handler->handle($form, $guard['handlerPayload'], $context);
        } catch (Throwable $throwable) {
            $this->customReceipts->fail($claim);
            $this->attemptBestEffortBookkeeping(function () use ($form, $guard, $throwable): void {
                app(FormAnalytics::class)->record(form: $form, eventType: FormAnalyticEventType::ERROR, origin: $guard['submittedFrom'], ipAddress: $guard['ipAddress'], userAgent: $guard['userAgent'], sessionId: $guard['sessionId'], metadata: ['exception' => get_class($throwable)]);
            });
            throw $throwable;
        }
        $entryId = (string) ($result['entry_id'] ?? '');
        $this->customReceipts->complete($claim, $entryId);
        $warning = $this->finalizeCustomSubmission($form, $guard);

        return $claim->receipt->refresh()->withBookkeepingWarning($warning);
    }

    /**
     * Execute a standard entry-based submission.
     *
     * @param  Form  $form  Target form
     * @param  SubmissionFields  $data  Validated submission payload
     * @param  Protection  $context  Request-derived submission context
     *
     * @throws Throwable
     */
    private function executeEntrySubmission(Form $form, array $data, array $context): FormEntry
    {
        $entryData = $this->prepare($form, $data, $context);
        $entry = $this->createEntry($entryData, $context['ipAddress'], $context['userAgent'], $context['sessionId'], $context['actor'], $context['idempotency_key'], $this->tokenService->issuedAt($context['publicToken'], $form, $context['publicSite']));
        if (! $entry->isIdempotentReplay()) {
            $commits = $this->container->make(ConnectionCommitCallbacks::class);
            if (FormsOwnership::declared()) {
                $site = OwnerSiteContext::require();
                $generations = $this->container->make(OwnershipContext::class)->snapshot()->generations;
                $callbackContext = ['owner' => $site->owner, 'site' => $site->site, 'canonicalOrigin' => $site->canonicalOrigin, 'origin' => $context['origin'], 'locale' => $context['locale'], 'correlationId' => $context['idempotency_key']];
                $formId = $form->identifier();
                $entryId = $entry->identifier();
                $commits->afterCommit($form->getConnection(), function () use ($site, $generations, $callbackContext, $formId, $entryId): void {
                    $this->container->make(PublicOwnerSiteRunner::class)->run($site->canonicalOrigin, function () use ($site, $generations, $callbackContext, $formId, $entryId): void {
                        $snapshot = $this->container->make(OwnershipContext::class)->snapshot();
                        if ($snapshot->publicSite != $site || $snapshot->generations !== $generations) {
                            throw new OwnershipViolation('Committed Forms callbacks require their original verified site and generation.');
                        }
                        $form = app(FormReader::class)->get($formId);
                        $entry = $this->entryLocator->forForm($form, $entryId);
                        $this->entryCallbacks->dispatchOwnership($form, $entry, $callbackContext);
                    });
                });
            } else {
                $commits->afterCommit($form->getConnection(), function () use ($form, $entry, $context): void {
                    $this->entryCallbacks->dispatch($form, $entry, $context);
                });
            }
        }

        return $entry;
    }

    /**
     * Persist Forms-owned submission bookkeeping without overriding downstream success.
     *
     * Once a custom handler has successfully completed its downstream work, failures in
     * Forms counters should be reported for follow-up but must not
     * surface back to the submitter as if the primary submission had failed.
     *
     * @param  Form  $form  Submitted form
     * @param  Guard  $guard
     */
    private function finalizeCustomSubmission(Form $form, array $guard): bool
    {
        return ! $this->attemptBestEffortBookkeeping(function () use ($form, $guard): void {
            app(FormAnalytics::class)->submission($form, $guard['submittedFrom'], $guard['ipAddress'], $guard['userAgent'], $guard['sessionId']);
        });
    }

    /**
     * Report bookkeeping failures without breaking a successful custom submission result.
     *
     * @param  Closure():void  $callback
     */
    private function attemptBestEffortBookkeeping(Closure $callback): bool
    {
        try {
            $callback();

            return true;
        } catch (Throwable $throwable) {
            report($throwable);

            return false;
        }
    }

    /**
     * Execute the form entry creation with comprehensive security checks.
     *
     * Performs host access validation, rate limit enforcement, honeypot detection,
     * spam scoring, entry persistence, and host-form activity capture. The rate-limit
     * guard consumes the submission attempt once; persistence does not record it again.
     *
     * @param  EntryFields  $data  Validated form entry data
     * @param  string  $ipAddress  Request IP address
     * @param  string|null  $userAgent  Request user agent
     * @param  string|null  $sessionId  Request session identifier
     * @param  Authenticatable|null  $actor  Authenticated actor, if any
     * @return FormEntry The created form entry instance
     *
     * @throws Exception When form is not found or entry refresh fails
     * @throws Throwable When security checks fail or rate limit is exceeded
     */
    private function createEntry(array $data, string $ipAddress, ?string $userAgent, ?string $sessionId, ?Authenticatable $actor = null, ?string $idempotencyKey = null, ?float $trustedFormLoadTime = null): FormEntry
    {
        $payloadDigest = $this->payloadDigest($data);
        $connection = FormModelRegistry::newInstance()->getConnection();
        try {
            /** @var array{entry?: FormEntry, form: Form, is_spam: bool, rejection?: string, rejection_exception?: FormSubmissionRejectionException, duplicate?: bool} $result */
            $result = $connection->transaction(function () use ($data, $ipAddress, $userAgent, $sessionId, $idempotencyKey, $trustedFormLoadTime, $payloadDigest, $actor) {
                $formId = $data['formId'];
                if ($formId === '') {
                    throw new Exception((string) trans('nvl-forms::forms/shared.messages.error.not_found', ['item' => (string) trans('nvl-forms::forms/general.entities.singular')]));
                }
                $form = $this->boundary->query(FormModelRegistry::query(), 'forms.forms')->with('allowedOrigins')->whereKey($formId)->firstOrFail();
                if ($idempotencyKey !== null) {
                    $existing = $this->boundary->query(FormEntry::query(), 'forms.entries')->where('form_id', $form->getKey())->where('idempotency_key', $idempotencyKey)->first();
                    if ($existing instanceof FormEntry) {
                        $this->ensureIdempotentPayloadMatches($existing, $payloadDigest);

                        return ['entry' => $existing->loadMissing('form'), 'form' => $form, 'is_spam' => $existing->is_spam, 'duplicate' => true];
                    }
                }
                $submittedFrom = $data['submittedFrom'];
                $email = ($data['email'] ?? null);
                $registrationFingerprint = $this->registrationFingerprint->resolve($form, $email, $sessionId);
                $this->validateHost($form, $submittedFrom);
                try {
                    $this->checkRateLimit($form, $ipAddress, $submittedFrom, $userAgent, $sessionId);
                } catch (FormSubmissionRejectionException $exception) {
                    return ['form' => $form, 'is_spam' => false, 'rejection_exception' => $exception];
                }
                if ($registrationFingerprint !== null && $this->boundary->query(FormEntry::query(), 'forms.entries')->where('form_id', $form->getKey())->where('registration_fingerprint', $registrationFingerprint)->exists()) {
                    return ['form' => $form, 'is_spam' => false, 'rejection_exception' => $this->duplicateRegistration()];
                }
                $submissionData = $data['submissionData'] ?? [];
                if ($this->spamDetection->checkHoneypot($form, $submissionData)) {
                    $this->spamRejectionRecorder->record(form: $form, reason: 'honeypot', score: 100, flags: ['honeypot' => true], channel: 'entries', origin: $submittedFrom, ipAddress: $ipAddress, userAgent: $userAgent, sessionId: $sessionId);

                    return ['form' => $form, 'is_spam' => true, 'rejection' => 'bot_detected'];
                }
                $spamDetection = $this->detectSpam($form, $data, $ipAddress, $userAgent, $trustedFormLoadTime);
                $result = $this->persistEntry($form, $data, $spamDetection, $ipAddress, $userAgent, $sessionId, $idempotencyKey, $payloadDigest, $registrationFingerprint);
                $entry = $result['entry'];
                $form = $result['form'];
                $fresh = $entry->fresh(['form:'.FormsOwnership::formRelationSelect()]);
                if ($fresh === null) {
                    throw new Exception((string) trans('nvl-forms::forms/shared.messages.error.refresh_failed', ['item' => (string) trans('nvl-forms::entries/general.entities.singular')]));
                }
                $this->domainEvents->dispatch(FormEntryChanged::for(form: $form, entry: $entry, operation: 'created', actor: $actor, context: ['is_spam' => (bool) $spamDetection['is_spam']]), $entry->getConnection());

                return ['entry' => $fresh, 'form' => $form, 'is_spam' => (bool) $spamDetection['is_spam']];
            });
        } catch (UniqueConstraintViolationException $exception) {
            if ($idempotencyKey !== null) {
                $existing = $this->boundary->query(FormEntry::query(), 'forms.entries')->with('form')->where('form_id', $data['formId'])->where('idempotency_key', $idempotencyKey)->first();
                if ($existing instanceof FormEntry) {
                    $this->ensureIdempotentPayloadMatches($existing, $payloadDigest);

                    return $existing->markAsIdempotentReplay();
                }
            }
            $form = $this->boundary->query(FormModelRegistry::query(), 'forms.forms')->whereKey($data['formId'])->firstOrFail();
            $email = ($data['email'] ?? null);
            $registrationFingerprint = $this->registrationFingerprint->resolve($form, $email, $sessionId);
            if ($registrationFingerprint !== null && $this->boundary->query(FormEntry::query(), 'forms.entries')->where('form_id', $form->getKey())->where('registration_fingerprint', $registrationFingerprint)->exists()) {
                throw $this->duplicateRegistration();
            }
            throw $exception;
        }
        if (isset($result['rejection_exception'])) {
            throw $result['rejection_exception'];
        }
        if (($result['rejection'] ?? null) === 'bot_detected') {
            throw new FormSubmissionRejectionException((string) trans('nvl-forms::forms/shared.messages.error.bot_detected'), 422);
        }
        $entry = $result['entry'] ?? null;
        if (! $entry instanceof FormEntry) {
            throw new Exception((string) trans('nvl-forms::forms/shared.messages.error.refresh_failed', ['item' => (string) trans('nvl-forms::entries/general.entities.singular')]));
        }
        if (($result['duplicate'] ?? false) === true) {
            return $entry->markAsIdempotentReplay();
        }

        return $entry;
    }

    /** @param EntryFields $data */
    private function payloadDigest(array $data): string
    {
        return hash('sha256', (string) json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function ensureIdempotentPayloadMatches(FormEntry $entry, string $payloadDigest): void
    {
        if ($entry->payload_digest === $payloadDigest) {
            return;
        }
        throw new FormSubmissionRejectionException(message: (string) trans('nvl-forms::forms/messages.error.idempotency_conflict'), statusCode: 409);
    }

    /**
     * Reject a second registration without disclosing the identity source.
     */
    private function duplicateRegistration(): FormSubmissionRejectionException
    {
        return new FormSubmissionRejectionException(message: (string) trans('nvl-forms::forms/messages.error.registration_already_exists'), statusCode: 409);
    }

    /**
     * Create the entry and update counters/analytics accordingly.
     *
     * @param  Form|string  $form  Form model or identifier
     * @param  EntryFields  $data  Prepared entry data
     * @param  array{is_spam:bool,is_flagged?:bool,score:int,flags:array<string,mixed>}  $spamDetection  Spam detection data
     * @param  string  $ipAddress  Request IP address
     * @param  string|null  $userAgent  Request user agent
     * @param  string|null  $sessionId  Request session identifier
     * @return array{entry: FormEntry, form: Form} Persisted entry and refreshed form
     *
     * @throws Throwable When the transaction fails
     */
    private function persistEntry(Form|string $form, array $data, array $spamDetection, string $ipAddress, ?string $userAgent, ?string $sessionId, ?string $idempotencyKey = null, ?string $payloadDigest = null, ?string $registrationFingerprint = null): array
    {
        return FormModelRegistry::newInstance()->getConnection()->transaction(function () use ($form, $data, $spamDetection, $ipAddress, $userAgent, $sessionId, $idempotencyKey, $payloadDigest, $registrationFingerprint) {
            $submittedFrom = $data['submittedFrom'];
            $formId = $form instanceof Form ? $form->identifier() : $form;
            $form = $this->boundary->query(FormModelRegistry::query(), 'forms.forms')->findOrFail($formId);
            $entryPayload = $this->entryAttributes($data);
            $entryPayload['ip_address'] = $ipAddress;
            $entryPayload['user_agent'] = $userAgent;
            $entryPayload['session_id'] = $sessionId;
            $entryPayload['is_spam'] = $spamDetection['is_spam'];
            $entryPayload['spam_score'] = $spamDetection['score'];
            $entryPayload['security_flags'] = $spamDetection['flags'];
            $entryPayload['idempotency_key'] = $idempotencyKey;
            $entryPayload['payload_digest'] = $payloadDigest;
            $entryPayload['registration_fingerprint'] = $registrationFingerprint;
            $entry = new FormEntry;
            $entry->fill($entryPayload);
            $entry->save();
            // Flag suspicious-but-not-blocked submissions for admin review
            $isFlagged = (bool) ($spamDetection['is_flagged'] ?? false);
            if ($isFlagged && ! $spamDetection['is_spam']) {
                $entry->setSecurityFlag('spam_flagged', true);
                $entry->setSecurityFlag('spam_flagged_at', now()->toISOString());
                $entry->setSecurityFlag('spam_flagged_score', $spamDetection['score']);
                $entry->save();
            }
            if ($spamDetection['is_spam']) {
                $form = $this->spamRejectionRecorder->record(form: $form, reason: 'spam_score', score: $spamDetection['score'], flags: $spamDetection['flags'], channel: 'entries', origin: $submittedFrom, ipAddress: $ipAddress, userAgent: $userAgent, sessionId: $sessionId);
            } else {
                // Legitimate submission: update counters and analytics
                $form = app(FormAnalytics::class)->submission($form, $submittedFrom, $ipAddress, $userAgent, $sessionId);
            }
            // Ensure the entry has the minimal parent loaded for further logging
            $entry->loadMissing('form:'.FormsOwnership::formRelationSelect());

            return ['entry' => $entry, 'form' => $form];
        });
    }

    /**
     * Execute the submission data preparation.
     *
     * @param  Form  $form  Form model
     * @param  SubmissionFields  $data  Validated submission data
     * @param  Protection  $context  Trusted submission context
     * @return EntryFields Prepared form entry data
     */
    private function prepare(Form $form, array $data, array $context): array
    {
        $validated = $data;
        $submittedFrom = $context['originHost'];

        return ['formId' => $form->id, 'subject' => $validated['subject'] ?? null, 'firstName' => $validated['firstName'] ?? null, 'lastName' => $validated['lastName'] ?? null, 'email' => $validated['email'] ?? null, 'phone' => $validated['phone'] ?? null, 'address' => $validated['address'] ?? null, 'body' => $validated['body'] ?? null, 'submissionData' => $validated['submissionData'] ?? [], 'submittedFrom' => $submittedFrom];
    }

    /**
     * Execute all security checks required before invoking a custom handler.
     *
     * @param  Form  $form  Target form
     * @param  SubmissionFields  $submissionData  Validated submission DTO
     * @param  Protection  $context  Request-derived submission context
     * @return Guard
     *
     * @throws FormSubmissionRejectionException When the submission is blocked by host/rate/spam rules
     */
    private function guardCustom(Form $form, array $submissionData, array $context): array
    {
        $entryData = $this->prepare($form, $submissionData, $context);
        $ipAddress = $context['ipAddress'];
        $userAgent = $context['userAgent'];
        $sessionId = $context['sessionId'];
        $submittedFrom = $entryData['submittedFrom'];
        $handlerPayload = $this->buildHandlerPayload($entryData, $submissionData);
        $this->validateHost($form, $submittedFrom);
        $this->checkRateLimit($form, $ipAddress, $submittedFrom, $userAgent, $sessionId);
        $submissionDataArray = $entryData['submissionData'] ?? [];
        if ($this->spamDetection->checkHoneypot($form, $submissionDataArray)) {
            $this->recordSpamRejection(form: $form, reason: 'honeypot', score: 100, flags: ['honeypot' => true], submittedFrom: $submittedFrom, ipAddress: $ipAddress, userAgent: $userAgent, sessionId: $sessionId);
            throw new FormSubmissionRejectionException(message: (string) trans('nvl-forms::forms/shared.messages.error.bot_detected'), statusCode: 422);
        }
        $spamDetection = $this->detectSpam($form, $entryData, $ipAddress, $userAgent, $this->tokenService->issuedAt($context['publicToken'], $form, $context['publicSite']));
        if ($spamDetection['is_spam']) {
            $this->recordSpamRejection(form: $form, reason: 'spam_score', score: $spamDetection['score'], flags: $spamDetection['flags'], submittedFrom: $submittedFrom, ipAddress: $ipAddress, userAgent: $userAgent, sessionId: $sessionId);
            throw new FormSubmissionRejectionException(message: (string) trans('nvl-forms::forms/shared.messages.error.bot_detected'), statusCode: 422);
        }

        return ['submittedFrom' => $submittedFrom, 'ipAddress' => $ipAddress, 'userAgent' => $userAgent, 'sessionId' => $sessionId, 'handlerPayload' => $handlerPayload];
    }

    /**
     * Build normalized payload for custom handler execution.
     *
     * @param  EntryFields  $entryData  Normalized entry payload
     * @param  SubmissionFields  $submissionData  Original validated DTO
     * @return array<string,mixed>
     */
    private function buildHandlerPayload(array $entryData, array $submissionData): array
    {
        $payload = [];
        foreach ($submissionData as $key => $value) {
            $payload[$key] = $value;
        }
        $subject = ($entryData['subject'] ?? null);
        $firstName = ($entryData['firstName'] ?? null);
        $lastName = ($entryData['lastName'] ?? null);
        $email = ($entryData['email'] ?? null);
        $phone = ($entryData['phone'] ?? null);
        $address = ($entryData['address'] ?? null);
        $body = ($entryData['body'] ?? null);
        $submission = ($entryData['submissionData'] ?? []);
        $submittedFrom = $entryData['submittedFrom'];
        $payload['subject'] = $subject;
        $payload['firstName'] = $firstName;
        $payload['lastName'] = $lastName;
        $payload['email'] = $email;
        $payload['phone'] = $phone;
        $payload['address'] = $address;
        $payload['body'] = $body;
        $payload['submissionData'] = $submission;
        $payload['submittedFrom'] = $submittedFrom;

        return $payload;
    }

    /**
     * Record spam rejection bookkeeping inside a narrow Forms-owned transaction.
     *
     * @param  Form  $form  Submitted form
     * @param  string  $reason  Machine-readable rejection reason
     * @param  float|int  $score  Spam score
     * @param  array<string, mixed>  $flags  Spam flags
     * @param  string|null  $submittedFrom  Submitted origin
     * @param  string  $ipAddress  Submitter IP address
     * @param  string|null  $userAgent  Submitter user agent
     * @param  string|null  $sessionId  Session identifier
     */
    private function recordSpamRejection(Form $form, string $reason, float|int $score, array $flags, ?string $submittedFrom, string $ipAddress, ?string $userAgent, ?string $sessionId): void
    {
        FormModelRegistry::newInstance()->getConnection()->transaction(function () use ($form, $reason, $score, $flags, $submittedFrom, $ipAddress, $userAgent, $sessionId): void {
            $this->spamRejectionRecorder->record(form: $form, reason: $reason, score: $score, flags: $flags, channel: 'custom_resolvement', origin: $submittedFrom, ipAddress: $ipAddress, userAgent: $userAgent, sessionId: $sessionId);
        });
    }

    /**
     * Validate submission protection requirements for a form request.
     *
     * When CSRF is required for the form, the request must provide either:
     * - a valid CSRF token tied to the request session, or
     * - a valid signed public token for this form.
     *
     * @param  Form  $form  Target form
     * @param  Protection  $context  Request-derived submission context
     *
     * @throws FormSubmissionRejectionException When the submission fails CSRF/public-token validation
     */
    private function validateProtection(Form $form, array $context): void
    {
        if (! $form->require_csrf) {
            return;
        }
        if ($this->hasValidCsrfToken($context)) {
            return;
        }
        $publicToken = $context['publicToken'];
        if ($this->tokenService->validate($publicToken, $form, $context['publicSite'])) {
            return;
        }
        throw new FormSubmissionRejectionException(message: (string) trans('nvl-forms::forms/messages.error.csrf_failed'), statusCode: 419);
    }

    /**
     * Resolve and verify the CSRF token against the current session.
     *
     * @param  Protection  $context
     */
    private function hasValidCsrfToken(array $context): bool
    {
        if (! $context['session'] instanceof Session) {
            return false;
        }
        $providedToken = $context['csrfToken'];
        if ($providedToken === null || $providedToken === '') {
            return false;
        }

        // A session that never generated a token returns null despite the contract's string type.
        $sessionToken = (string) $context['session']->token();

        return $sessionToken !== '' && hash_equals($sessionToken, $providedToken);
    }

    /**
     * Ensure the submitted origin is allowed when public access is restricted.
     *
     * @param  Form|string  $form  Form model or identifier
     * @param  string|null  $submittedFrom  Origin host that submitted the form
     *
     * @throws FormSubmissionRejectionException If host is not allowed or missing when required
     */
    private function validateHost(Form|string $form, ?string $submittedFrom): void
    {
        $formModel = $form instanceof Form ? $form : FormModelRegistry::query()->findOrFail($form);
        if (! $formModel->restrict_public_access) {
            return;
        }
        if ($submittedFrom === null || $submittedFrom === '') {
            throw new FormSubmissionRejectionException((string) trans('nvl-forms::forms/messages.error.origin_required'), 403);
        }
        if (! $this->originAccess->isOriginAllowed($formModel, $submittedFrom)) {
            throw new FormSubmissionRejectionException((string) trans('nvl-forms::forms/shared.messages.error.origin_not_allowed', ['origin' => $submittedFrom]), 403);
        }
    }

    /**
     * Enforce rate limiting for a form submission.
     *
     * @param  Form|string  $form  The form or its ID
     * @param  string  $ipAddress  Submitter IP address
     * @param  string|null  $origin  Request Origin header value for analytics
     * @param  string|null  $userAgent  Request user agent for analytics
     * @param  string|null  $sessionId  Session identifier for analytics
     *
     * @throws FormSubmissionRejectionException When rate limit is exceeded
     */
    private function checkRateLimit(Form|string $form, string $ipAddress, ?string $origin = null, ?string $userAgent = null, ?string $sessionId = null): void
    {
        $formModel = $form instanceof Form ? $form : FormModelRegistry::query()->findOrFail($form);
        $attempt = $this->rateLimitService->consumeSubmissionAttempt($formModel, $ipAddress, $origin, $userAgent, $sessionId);
        if (! $attempt->allowed) {
            throw new FormSubmissionRejectionException((string) trans('nvl-forms::forms/shared.messages.error.rate_limit_exceeded'), 429, max(1, $attempt->retryAfterSeconds));
        }
    }

    /**
     * Evaluate a submission payload for spam signals.
     *
     * Extracts text fields (body, subject), email, and submission data from the DTO,
     * delegates scoring to the spam detection service, and returns a structured result
     * with is_spam/is_flagged decisions based on configured thresholds.
     *
     * @param  Form  $form  Resolved form model
     * @param  SubmissionFields  $data  Submission data
     * @param  string  $ipAddress  Request IP address for reputation scoring
     * @param  string|null  $userAgent  Request user agent for bot detection
     * @return array{is_spam: bool, is_flagged: bool, score: int, flags: array<string, mixed>}
     */
    private function detectSpam(Form $form, array $data, string $ipAddress, ?string $userAgent, ?float $trustedFormLoadTime = null): array
    {
        $payload = $this->normalizedPayload($data);
        $analysis = $this->spamDetection instanceof FormSpamDetectionService ? $this->spamDetection->analyzeSubmission(form: $form, data: $payload, ipAddress: $ipAddress, userAgent: $userAgent, trustedFormLoadTime: $trustedFormLoadTime) : ['score' => $this->spamDetection->calculateSpamScore($form, $payload, $ipAddress, $userAgent, null, $trustedFormLoadTime), 'flags' => []];
        $finalScore = (float) $analysis['score'];

        return ['is_spam' => $this->spamDetection->shouldBlockSubmission($finalScore), 'is_flagged' => $this->spamDetection->shouldFlagSubmission($finalScore), 'score' => (int) round($finalScore), 'flags' => $analysis['flags']];
    }

    /**
     * Build the submission payload used by the spam detection service.
     *
     * @param  SubmissionFields  $data  Submission data
     * @return array<string, mixed>
     */
    private function normalizedPayload(array $data): array
    {
        $payload = $data['submissionData'] ?? [];
        foreach (['subject', 'body', 'email'] as $property) {
            $value = $data[$property] ?? null;
            if ($value !== null && $value !== '') {
                $payload[$property] = $value;
            }
        }

        return $payload;
    }

    /** @return array<string, array<int, mixed>> */
    public static function fieldRules(): array
    {
        return [
            'subject' => ['nullable', 'string', 'max:255'],
            'firstName' => ['nullable', 'string', 'max:100'],
            'lastName' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:50000'],
            'submissionData' => ['nullable', 'array', new BoundedSubmissionPayload],
            'submissionData.*' => ['nullable'],
        ];
    }

    /**
     * @param  SubmissionFields  $data
     * @return array<string,mixed>
     */
    private function entryAttributes(array $data): array
    {
        $attributes = [];
        foreach (array_intersect_key($data, array_flip(['formId', 'subject', 'firstName', 'lastName', 'email', 'phone', 'address', 'body', 'submissionData', 'submittedFrom'])) as $key => $value) {
            $attributes[Str::snake($key)] = $value;
        }

        return $attributes;
    }

    /**
     * Validate transport-supplied facts; timing is derived only from a verified signed token.
     *
     * @param  array<string,mixed>  $protection
     * @return Protection
     */
    private function protection(array $protection): array
    {
        $allowed = ['origin', 'ipAddress', 'userAgent', 'sessionId', 'session', 'csrfToken', 'publicToken', 'idempotency_key', 'publicSite', 'actor', 'locale'];
        if (array_diff(array_keys($protection), $allowed) !== []) {
            throw ValidationException::withMessages(['protection' => 'Unknown submission protection metadata.']);
        }
        if (is_string($protection['userAgent'] ?? null)) {
            $protection['userAgent'] = mb_scrub($protection['userAgent'], 'UTF-8');
        }
        $validated = Validator::make($protection, [
            'origin' => ['required', 'string', 'max:2048', 'url:http,https'],
            'ipAddress' => ['required', 'ip'],
            'userAgent' => ['nullable', 'string', 'max:500'],
            'sessionId' => ['nullable', 'string', 'max:100'],
            'csrfToken' => ['nullable', 'string', 'max:2048'],
            'publicToken' => ['nullable', 'string', 'max:8192'],
            'idempotency_key' => ['nullable', 'string', 'min:1', 'max:128'],
            'publicSite' => ['sometimes', 'string', 'min:1', 'max:100'],
            'locale' => ['sometimes', 'string', 'max:32'],
        ])->validate();
        $strings = [];
        foreach ($validated as $key => $value) {
            if (! is_string($key) || ($value !== null && (! is_string($value) || ! mb_check_encoding($value, 'UTF-8')))) {
                throw ValidationException::withMessages(['protection' => 'Expected string metadata.']);
            }
            $strings[$key] = $value;
        }
        $validated = $strings;
        if (! isset($validated['origin'], $validated['ipAddress'])) {
            throw ValidationException::withMessages(['protection' => 'Origin and IP address are required.']);
        }
        $parts = parse_url($validated['origin']);
        if (! is_array($parts) || ! isset($parts['host'], $parts['scheme']) || strlen($parts['host']) > 253 || array_diff(array_keys($parts), ['scheme', 'host', 'port']) !== [] || preg_match('/[\x00-\x20\x7f]/', $validated['origin']) === 1) {
            throw ValidationException::withMessages(['origin' => 'A canonical HTTP origin is required.']);
        }
        $originHost = strtolower($parts['host']).(isset($parts['port']) ? ':'.$parts['port'] : '');
        $origin = strtolower($parts['scheme']).'://'.$originHost;
        $packedAddress = inet_pton($validated['ipAddress']);
        $canonicalAddress = $packedAddress === false ? false : inet_ntop($packedAddress);
        $site = OwnerSiteContext::current();
        if ($site !== null && ($site->canonicalOrigin !== $origin || ($validated['publicSite'] ?? $site->site) !== $site->site)) {
            throw new OwnershipViolation('Submission origin differs from the admitted canonical site.');
        }
        $session = $protection['session'] ?? null;
        $actor = $protection['actor'] ?? null;
        if (($session !== null && ! $session instanceof Session) || ($actor !== null && ! $actor instanceof Authenticatable)) {
            throw ValidationException::withMessages(['protection' => 'Session and actor must be explicit native objects.']);
        }

        return [
            'origin' => $origin, 'originHost' => $originHost, 'requestHost' => strtolower($parts['host']),
            'ipAddress' => $canonicalAddress === false ? $validated['ipAddress'] : $canonicalAddress, 'userAgent' => $validated['userAgent'] ?? null,
            'sessionId' => $session?->getId() ?? ($validated['sessionId'] ?? null), 'session' => $session,
            'csrfToken' => $validated['csrfToken'] ?? null, 'publicToken' => $validated['publicToken'] ?? null,
            'idempotency_key' => $validated['idempotency_key'] ?? null, 'publicSite' => $site !== null ? $site->site : ($validated['publicSite'] ?? 'default'),
            'actor' => $actor, 'locale' => $validated['locale'] ?? app(ContentLocale::class)->get(),
        ];
    }
}
