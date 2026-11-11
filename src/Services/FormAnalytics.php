<?php

declare(strict_types=1);

namespace Nvl\Forms\Services;

use Nvl\Forms\Contracts\FormRateLimiter;
use Nvl\Forms\Enums\FormAnalyticEventType;
use Nvl\Forms\Exceptions\FormException;
use Nvl\Forms\Models\AllowedOrigin;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormAnalytic;
use Nvl\Forms\Support\FormIdentifier;
use Nvl\Support\Ownership\Contracts\OwnershipBoundary;
use Throwable;

/**
 * Native Forms workflow family.
 *
 * @api
 */
final class FormAnalytics
{
    public function __construct(private readonly OwnershipBoundary $boundary, private readonly FormCounterWriter $counters, private readonly FormOriginAccessService $originAccess) {}

    /**
     * Create a forms analytics event.
     *
     * @param  FormAnalyticEventType|string  $eventType  Analytic event type
     * @param  array<string, mixed>|null  $metadata  Optional event metadata
     */
    public function record(Form|string $form, FormAnalyticEventType|string $eventType, ?string $origin = null, ?string $ipAddress = null, ?string $userAgent = null, ?string $sessionId = null, ?array $metadata = null): FormAnalytic
    {
        $canonical = $this->boundary->query(FormModelRegistry::query(), 'forms.forms')->findOrFail(FormIdentifier::of($form, Form::class));
        $event = $eventType instanceof FormAnalyticEventType ? $eventType : FormAnalyticEventType::from((string) $eventType);

        return FormModelRegistry::newInstance()->getConnection()->transaction(fn (): FormAnalytic => FormAnalytic::create(['form_id' => $canonical->getKey(), 'event_type' => $event, 'origin' => $origin, 'ip_address' => $ipAddress, 'user_agent' => $userAgent, 'session_id' => $sessionId, 'metadata' => $metadata]));
    }

    /**
     * Persist counters and analytics for a form submission.
     *
     * @param  Form|string  $form  Form instance or identifier
     * @param  string|null  $origin  Origin header for the submission
     * @param  string|null  $ipAddress  Originating IP address
     * @param  string|null  $userAgent  Request user agent
     * @param  string|null  $sessionId  Session identifier when available
     * @return Form Updated form instance
     *
     * @throws Throwable
     */
    public function submission(Form|string $form, ?string $origin, ?string $ipAddress, ?string $userAgent, ?string $sessionId): Form
    {
        return FormModelRegistry::newInstance()->getConnection()->transaction(function () use ($form, $origin, $ipAddress, $userAgent, $sessionId) {
            $formModel = $form instanceof Form ? $form : FormModelRegistry::query()->findOrFail(FormIdentifier::of($form, Form::class));
            $nowFormatted = $formModel->fromDateTime(now());
            if (! is_string($nowFormatted)) {
                throw new FormException('Forms bookkeeping requires a native timestamp.');
            }
            $extra = ['last_used_at' => $nowFormatted];
            if ($formModel->first_used_at === null) {
                $extra['first_used_at'] = $nowFormatted;
            }
            // Single UPDATE: increment + extra columns in one query
            $this->counters->increment($formModel, 'submissions_count', $extra);
            $this->record(form: $formModel, eventType: FormAnalyticEventType::SUBMISSION, origin: $origin, ipAddress: $ipAddress, userAgent: $userAgent, sessionId: $sessionId);

            return $formModel;
        });
    }

    /**
     * Record the view for the given form.
     *
     * @param  Form|string  $form  Form instance or identifier
     * @param  string|null  $origin  Origin header or referrer value
     * @param  string|null  $ipAddress  Visitor IP address
     * @param  string|null  $userAgent  Visitor user agent
     * @param  string|null  $sessionId  Session identifier when available
     * @return Form Updated form instance
     *
     * @throws Throwable
     */
    public function view(Form|string $form, ?string $origin, ?string $ipAddress, ?string $userAgent, ?string $sessionId): Form
    {
        return FormModelRegistry::newInstance()->getConnection()->transaction(function () use ($form, $origin, $ipAddress, $userAgent, $sessionId) {
            $formModel = $form instanceof Form ? $form : FormModelRegistry::query()->findOrFail(FormIdentifier::of($form, Form::class));
            $this->counters->increment($formModel, 'views_count');
            $this->record(form: $formModel, eventType: FormAnalyticEventType::VIEW, origin: $origin, ipAddress: $ipAddress, userAgent: $userAgent, sessionId: $sessionId);
            if ($origin !== null && $origin !== '' && $formModel->restrict_public_access) {
                $matchedOrigin = $this->originAccess->resolveMatchingOrigin($formModel, $origin);
                if ($matchedOrigin !== null) {
                    $this->originUsage($matchedOrigin);
                }
            }
            $fresh = $formModel->fresh();
            if (! $fresh instanceof Form) {
                throw new \RuntimeException('The form could not be refreshed after recording its view.');
            }

            return $fresh;
        });
    }

    /**
     * Record a submission attempt for the given form and IP.
     *
     * @param  Form|string  $form  The form or its ID
     * @param  string  $ipAddress  Submitter IP address
     * @param  string|null  $origin  Request Origin header value
     * @param  string|null  $userAgent  Request user agent
     * @param  string|null  $sessionId  Session identifier
     */
    public function rateLimit(Form|string $form, string $ipAddress, ?string $origin = null, ?string $userAgent = null, ?string $sessionId = null): void
    {
        $formModel = $this->boundary->query(FormModelRegistry::query(), 'forms.forms')->findOrFail(FormIdentifier::of($form, Form::class));
        app(FormRateLimiter::class)->recordSubmissionAttempt($formModel, $ipAddress, $origin, $userAgent, $sessionId);
    }

    /**
     * Increment usage count and update the last-used timestamp atomically.
     *
     * @param  AllowedOrigin  $allowedOrigin  The origin record to update
     */
    public function originUsage(AllowedOrigin $allowedOrigin): void
    {
        $this->boundary->assertRecord($allowedOrigin, 'forms.origins');
        $allowedOrigin->increment('usage_count', 1, ['last_used_at' => now()]);
    }
}
