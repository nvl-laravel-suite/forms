<?php

declare(strict_types=1);

namespace Nvl\Forms\Tests\Fixtures\Consumer\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;
use Nvl\Forms\Enums\FormResponseCode;
use Nvl\Forms\Exceptions\FormException;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Services\FormLifecycle;
use Nvl\Forms\Services\FormReader;
use Nvl\Forms\Tests\Fixtures\Consumer\Data\FormPayload;
use Nvl\Forms\Tests\Fixtures\Consumer\Data\FormSearchFilter;
use Nvl\Forms\Tests\Fixtures\Consumer\Data\FormSelectOption;
use Nvl\Forms\Tests\Fixtures\Consumer\Data\FormSuggestion;
use Nvl\Forms\Tests\Fixtures\Consumer\Data\FormSuggestions;
use Nvl\Forms\Tests\Fixtures\Consumer\Data\Mutations\DuplicateFormData;
use Nvl\Forms\Tests\Fixtures\Consumer\Data\Mutations\MutateFormPayload;
use Nvl\Forms\Tests\Fixtures\Consumer\Data\PaginatedCollection;
use Nvl\Forms\Tests\Fixtures\Consumer\Presentation\BuildFormShowPayloadAction;
use Symfony\Component\HttpFoundation\Response;

/**
 * API controller for forms search and autocomplete functionality.
 */
final class FormsApiController extends Controller
{
    /**
     * Display a paginated listing of forms.
     *
     * @param  Request  $request  Incoming listing request
     * @param  ListFormsAction  $action  Forms listing action
     * @return JsonResponse Paginated form rows
     */
    public function index(Request $request, FormReader $action): JsonResponse
    {
        Gate::authorize('viewAny', Form::class);
        $perPage = $request->integer('per_page', 20) ?: 20;
        $forms = $action->paginate(true, $perPage, array_intersect_key($request->query(), array_flip(['status', 'type', 'resolvement', 'handle'])), $request->user());
        if (! $forms instanceof LengthAwarePaginator) {
            throw new FormException('Forms index expects a paginator payload.');
        }

        return response()->json(['data' => ['forms' => PaginatedCollection::fromPaginator($forms)->toArray()]], 200);
    }

    /**
     * Store a newly created form.
     *
     * @param  Request  $request  Incoming create request
     * @param  CreateFormAction  $action  Form creation action
     * @return JsonResponse Created form payload
     */
    public function store(Request $request, FormLifecycle $action): JsonResponse
    {
        Gate::authorize('create', Form::class);
        $form = $action->create(MutateFormPayload::validateForCreate($this->normalizePayloadArray($request->all())), $request->user());

        return response()->json(['data' => FormPayload::fromModel($form)->toArray(), 'code' => FormResponseCode::Created->value], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function queryParameters(Request $request): array
    {
        $parameters = [];
        foreach ($request->query() as $key => $value) {
            if (is_string($key)) {
                $parameters[$key] = $value;
            }
        }

        return $parameters;
    }

    /**
     * Display the specified form with derived show-page state.
     *
     * @param  Request  $request  Incoming show request
     * @param  Form  $form  Route-bound form
     * @param  BuildFormShowPayloadAction  $action  Show payload action
     * @return JsonResponse Form detail payload
     */
    public function show(Request $request, Form $form, BuildFormShowPayloadAction $action): JsonResponse
    {
        Gate::authorize('view', $form);
        $origin = $request->header('Origin') ?? $request->header('Referer');

        return response()->json(['data' => $action->execute($form, false, $origin, $request->ip() ?? '0.0.0.0', $request->userAgent(), $request->hasSession() ? $request->session()->getId() : null, $request->user())], 200);
    }

    /**
     * Update the specified form.
     *
     * @param  Request  $request  Incoming update request
     * @param  Form  $form  Route-bound form
     * @param  UpdateFormAction  $action  Form update action
     * @return JsonResponse Updated form payload
     */
    public function update(Request $request, Form $form, FormLifecycle $action): JsonResponse
    {
        Gate::authorize('update', $form);
        $form = $action->update($form, MutateFormPayload::validateForUpdate($this->normalizePayloadArray($request->all()), $form->id), $request->user());

        return response()->json(['data' => FormPayload::fromModel($form)->toArray(), 'code' => FormResponseCode::Updated->value], 200);
    }

    /**
     * Remove the specified form.
     *
     * @param  Request  $request  Incoming delete request
     * @param  Form  $form  Route-bound form
     * @param  DeleteFormAction  $action  Form deletion action
     * @return JsonResponse Delete result payload
     */
    public function destroy(Request $request, Form $form, FormLifecycle $action): JsonResponse
    {
        Gate::authorize('delete', $form);

        return response()->json(['data' => ['deleted' => $action->delete($form, $request->user())], 'code' => FormResponseCode::Deleted->value], 200);
    }

    /**
     * Duplicate the specified form.
     *
     * @param  Request  $request  Incoming duplicate request
     * @param  Form  $form  Route-bound form
     * @param  DuplicateFormAction  $action  Form duplication action
     * @return JsonResponse Duplicated form payload
     */
    public function duplicate(Request $request, Form $form, FormLifecycle $action): JsonResponse
    {
        Gate::authorize('duplicate', $form);
        $data = $request->validate(DuplicateFormData::rules());
        $name = $data['name'] ?? null;
        $duplicatedForm = $action->duplicate($form, is_string($name) && $name !== '' ? $name : null, $request->user());

        return response()->json(['data' => FormPayload::fromModel($duplicatedForm)->toArray(), 'code' => FormResponseCode::Duplicated->value], 201);
    }

    /**
     * Get form suggestions for autocomplete/select.
     *
     * @param  Request  $request  Incoming suggestion parameters
     * @param  GetFormSuggestionsAction  $action  Action to get form suggestions
     * @return JsonResponse Suggestions response
     */
    public function suggestions(Request $request, FormReader $action): JsonResponse
    {
        Gate::authorize('viewAny', Form::class);
        $data = $request->validate(FormSuggestions::rules(), FormSuggestions::messages(), FormSuggestions::attributes());
        $limit = (int) ($data['limit'] ?? 10);
        $forms = $action->suggestions($data['q'], $limit);

        return response()->json(['data' => $forms->map(static fn (Form $form): array => FormSuggestion::fromModel($form)->toArray())->all()], 200);
    }

    /**
     * Search forms with filters.
     *
     * @param  Request  $request  Incoming search parameters
     * @param  SearchFormsAction  $action  Action to search forms
     * @return JsonResponse Search results response
     */
    public function search(Request $request, FormReader $action): JsonResponse
    {
        Gate::authorize('viewAny', Form::class);
        $payload = $request->validate(FormSearchFilter::rules(), FormSearchFilter::messages(), FormSearchFilter::attributes());
        $result = $action->search($payload, $request->user());

        return response()->json(['data' => ['items' => $result['forms'], 'total' => $result['total']]], 200);
    }

    /**
     * Get select options for forms.
     *
     * @param  Request  $request  Incoming select parameters
     * @param  GetFormSelectOptionsAction  $action  Action to get form select options
     * @return JsonResponse Select options response
     */
    public function select(Request $request, FormReader $action): JsonResponse
    {
        Gate::authorize('viewAny', Form::class);
        $options = $action->options($request->validate(FormSelectOption::rules(), FormSelectOption::messages(), FormSelectOption::attributes()));

        return response()->json(['data' => $options], 200);
    }

    /**
     * Normalize request payloads to string-keyed arrays.
     *
     * @param  array<array-key, mixed>  $payload  Raw DTO payload
     * @return array<string, mixed> Normalized payload
     */
    private function normalizePayloadArray(array $payload): array
    {
        $normalized = [];
        foreach ($payload as $key => $value) {
            if (is_string($key)) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }
}
