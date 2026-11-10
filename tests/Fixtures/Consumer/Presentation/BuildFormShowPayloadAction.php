<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Presentation;

use Illuminate\Contracts\Auth\Authenticatable;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Services\FormReader;
use Nvl\Forms\Tests\Fixtures\Consumer\Data\FormPayload;
use Nvl\Forms\Tests\Fixtures\Consumer\Services\FormShowStateResolver;

/**
 * Orchestrates canonical form retrieval with show-state projection.
 *
 * The action composition preserves one display-loading and optional
 * view-recording path before transport-safe state is derived.
 *
 * @internal
 */
final class BuildFormShowPayloadAction
{
    /**
     * @param  ShowFormAction  $show  Form show/read action
     * @param  FormShowStateResolver  $stateResolver  Derived state resolver
     */
    public function __construct(private readonly FormReader $show, private readonly FormShowStateResolver $stateResolver) {}

    /**
     * Build show-page payload with DTO form data and derived display states.
     *
     * @return array{
     *     form: array<string, mixed>,
     *     states: array<string, mixed>
     * }
     */
    public function execute(Form|string $form, bool $recordView = false, ?string $origin = null, ?string $ipAddress = null, ?string $userAgent = null, ?string $sessionId = null, ?Authenticatable $actor = null): array
    {
        $formModel = $this->show->details($form, $recordView, $origin, $ipAddress, $userAgent, $sessionId, $actor);
        $states = $this->stateResolver->resolve($formModel);

        return ['form' => FormPayload::fromModel($formModel)->toArray(), 'states' => $states->toArray()];
    }
}
