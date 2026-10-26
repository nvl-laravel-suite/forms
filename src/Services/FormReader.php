<?php

declare(strict_types=1);

namespace Nvl\Forms\Services;

use DateTimeInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Nvl\Forms\Enums\FormAnalyticEventType;
use Nvl\Forms\Exceptions\FormException;
use Nvl\Forms\Models\Form;
use Nvl\Forms\Models\FormEntry;
use Nvl\Support\Ownership\Contracts\OwnershipBoundary;
use Stringable;

/**
 * Native Forms workflow family.
 *
 * @api
 */
final class FormReader
{
    public function __construct(private readonly OwnershipBoundary $boundary) {}

    /**
     * Execute the form retrieval for rendering.
     *
     * Accepts a pre-loaded Form instance to avoid redundant queries
     * when the model was already resolved by middleware.
     *
     * @param  Form|string  $formIdentifier  Form model, ID, or handle
     * @return Form Form model with loaded relationships
     *
     * @throws ModelNotFoundException If the form cannot be resolved
     */
    public function get(Form|string $formIdentifier): Form
    {
        $identifier = $formIdentifier instanceof Form ? $formIdentifier->identifier() : $formIdentifier;
        $query = $this->boundary->query(FormModelRegistry::query(), 'forms.forms')->withResolvedTranslations()->where(function ($identity) use ($identifier): void {
            $identity->where('handle', $identifier);
            if (Str::isUuid($identifier)) {
                $identity->orWhere('id', $identifier);
            }
        });
        $form = $query->firstOrFail();
        $form->loadMissing(['allowedOrigins', 'translations']);
        $this->boundary->assertRecord($form, 'forms.forms');

        return $form;
    }

    /**
     * Resolve the form for display and optionally record a view event.
     *
     * @param  Form|string  $form  Form instance or identifier
     * @param  bool  $recordView  Whether the current request should be counted as a view
     * @param  string|null  $origin  Origin header or referrer value
     * @param  string|null  $ipAddress  Visitor IP address
     * @param  string|null  $userAgent  Visitor user agent
     * @param  string|null  $sessionId  Session identifier when available
     * @param  Authenticatable|null  $actor  Actor initiating the view
     * @return Form Form with the required relationships eagerly loaded
     */
    public function details(Form|string $form, bool $recordView = true, ?string $origin = null, ?string $ipAddress = null, ?string $userAgent = null, ?string $sessionId = null, ?Authenticatable $actor = null): Form
    {
        FormManagement::authorize($actor);
        $formId = $form instanceof Form ? $form->identifier() : $form;
        $formModel = $this->boundary->query(FormModelRegistry::query(), 'forms.forms')->findOrFail($formId);
        if ($recordView) {
            $formModel = app(FormAnalytics::class)->view($formModel, $origin, $ipAddress, $userAgent, $sessionId);
        }

        return $formModel->load(['entries' => static function (HasMany $query): void {
            $query->latest()->select('id', 'form_id', 'subject', 'email', 'first_name', 'last_name', 'is_spam', 'created_at')->limit(10);
        }, 'allowedOrigins', 'translations'])->loadMissing(['analytics' => static function (HasMany $query): void {
            $query->whereDate('created_at', '>=', now()->subDays(30)->toDateString())->orderBy('created_at', 'desc')->limit(100);
        }]);
    }

    /**
     * Execute the form listing.
     *
     * @param  bool  $paginate  Whether to paginate results
     * @param  int|null  $perPage  Items per page when paginating
     * @param  array<string,mixed>  $filters
     * @return LengthAwarePaginator<int, Form>|Collection<int, Form>
     *
     * @throws FormException When per_page parameter is invalid
     */
    public function paginate(bool $paginate = true, ?int $perPage = null, array $filters = [], ?Authenticatable $actor = null): LengthAwarePaginator|Collection
    {
        FormManagement::authorize($actor);
        $query = FormModelRegistry::query()->select($this->listFormsGetSelectColumns())->with($this->listFormsGetEagerLoadRelations());
        foreach (array_intersect_key($filters, array_flip(['status', 'type', 'resolvement', 'handle'])) as $column => $value) {
            $query->where($column, $value);
        }
        if (array_key_exists('search', $filters)) {
            $this->filterSearch($query, $filters['search']);
        }
        if (array_key_exists('last_used', $filters)) {
            $this->filterLastUsedAt($query, $filters['last_used']);
        }
        if (array_key_exists('availability', $filters)) {
            $operator = $filters['availability_operator'] ?? 'equals';
            if (! is_string($operator)) {
                throw new FormException('Unsupported availability operator.');
            }
            $this->filterAvailability($query, $filters['availability'], $operator);
        }
        $query->orderBy('handle')->orderBy('id');
        $perPage = $perPage !== null && $perPage > 0 ? $perPage : 20;
        if ($paginate) {
            return $query->paginate($perPage);
        }

        return $query->get();
    }

    /**
     * Get columns to select.
     *
     * @return array<string>
     */
    private function listFormsGetSelectColumns(): array
    {
        return ['id', 'handle', 'revision', 'status', 'resolvement', 'type', 'restrict_public_access', 'allow_multiple_registrations', 'date_restricted', 'available_from', 'available_until', 'submissions_count', 'first_used_at', 'last_used_at', 'created_at', 'updated_at'];
    }

    /**
     * Get relationships to eager load.
     *
     * @return array<string>
     */
    private function listFormsGetEagerLoadRelations(): array
    {
        return ['translations'];
    }

    /**
     * Execute the validation schema generation.
     *
     * @param  string  $formIdentifier  Form ID or handle
     * @return array<string,mixed> Public validation schema
     */
    public function schema(string $formIdentifier): array
    {
        $form = $this->get($formIdentifier);
        $rules = FormSubmissions::fieldRules();
        $fields = [];
        $normalizedRules = [];
        foreach ($rules as $field => $fieldRules) {
            $normalizedRules[$field] = array_values(array_map($this->getFormValidationSchemaNormalizeRule(...), $fieldRules));
            if (! str_contains($field, '.')) {
                $fields[] = ['key' => $field, 'required' => in_array('required', $fieldRules, true), 'rules' => $normalizedRules[$field]];
            }
        }

        return ['formId' => $form->id, 'name' => $form->displayName(), 'fields' => $fields, 'validationRules' => $normalizedRules, 'messages' => [], 'attributes' => []];
    }

    /**
     * Convert one Laravel validation rule to a stable client representation.
     */
    private function getFormValidationSchemaNormalizeRule(mixed $rule): string
    {
        if (is_string($rule)) {
            return $rule;
        }
        if ($rule instanceof Stringable) {
            return (string) $rule;
        }
        if ($rule instanceof ValidationRule || is_object($rule)) {
            return 'custom:'.class_basename($rule);
        }

        return 'unsupported:'.get_debug_type($rule);
    }

    /**
     * Fetch a form alongside analytics data and a small sample of recent entries.
     *
     * @param  Form|string  $form  Form instance or identifier
     * @param  int  $analyticsDays  Rolling window for analytics aggregation
     * @return array{form: Form, analytics: array<string, mixed>, recent_entries: array<int, array<string, mixed>>}
     */
    public function analytics(Form|string $form, int $analyticsDays = 30, ?Authenticatable $actor = null): array
    {
        FormManagement::authorize($actor);
        $formModel = $this->details($form, false, actor: $actor);
        $summary = $this->summary($formModel, $analyticsDays, $actor);
        /** @var array<int, array<string, mixed>> $recent */
        $recent = $formModel->entries->take(5)->map(static fn (FormEntry $entry): array => $entry->toArray())->values()->all();

        return ['form' => $formModel, 'analytics' => $summary, 'recent_entries' => $recent];
    }

    /**
     * Build an analytics summary for the provided form.
     *
     * @param  Form|string  $form  Form instance or identifier
     * @param  int  $days  Rolling window in days to include in counts
     * @return array{total_views:int,total_submissions:int,spam_blocked:int,conversion_rate:float,top_origins:array<string,int>}
     */
    public function summary(Form|string $form, int $days = 30, ?Authenticatable $actor = null): array
    {
        FormManagement::authorize($actor);
        $formId = $form instanceof Form ? $form->identifier() : $form;
        $formModel = $this->boundary->query(FormModelRegistry::query(), 'forms.forms')->findOrFail($formId);
        $startDate = now()->subDays($days);
        $totalViews = $formModel->analytics()->where('event_type', FormAnalyticEventType::VIEW)->where('created_at', '>=', $startDate)->count();
        $totalSubmissions = $formModel->analytics()->where('event_type', FormAnalyticEventType::SUBMISSION)->where('created_at', '>=', $startDate)->count();
        $spamBlocked = $formModel->analytics()->where('event_type', FormAnalyticEventType::SPAM_BLOCKED)->where('created_at', '>=', $startDate)->count();
        $conversionRate = $totalViews > 0 ? round($totalSubmissions / $totalViews * 100, 2) : 0.0;
        $topOrigins = $this->getFormAnalyticsSummaryBuildTopOrigins($formModel, $days, 10);

        return ['total_views' => $totalViews, 'total_submissions' => $totalSubmissions, 'spam_blocked' => $spamBlocked, 'conversion_rate' => $conversionRate, 'top_origins' => $topOrigins];
    }

    /**
     * @return array<string,int>
     */
    private function getFormAnalyticsSummaryBuildTopOrigins(Form $form, int $days, int $limit): array
    {
        $startDate = now()->subDays($days);
        /** @var Collection<int,string> $origins */
        $origins = $form->analytics()->where('created_at', '>=', $startDate)->whereNotNull('origin')->pluck('origin');
        $counts = [];
        foreach ($origins as $origin) {
            $counts[(string) $origin] = ($counts[(string) $origin] ?? 0) + 1;
        }
        arsort($counts);

        return array_slice($counts, 0, $limit, true);
    }

    /**
     * Execute the form search.
     *
     * @param  array<string, mixed>  $filters  Search filters and parameters
     */
    /**
     * @param array<string,mixed> $filters
     * @return array{forms: \Illuminate\Database\Eloquent\Collection<int,Form>,total:int} */
    public function search(array $filters, ?Authenticatable $actor = null): array
    {
        FormManagement::authorize($actor);
        $normalizedFilters = [];
        foreach ($filters as $key => $value) {
            $normalizedFilters[Str::snake($key)] = $value;
        }
        $query = FormModelRegistry::query()->select($this->searchFormsGetSelectColumns($normalizedFilters))->withResolvedTranslations();
        // Apply has_submissions filter
        if (isset($normalizedFilters['has_submissions']) && (bool) $normalizedFilters['has_submissions'] === true) {
            $query->where('submissions_count', '>', 0);
        }
        if (isset($normalizedFilters['status']) && is_string($normalizedFilters['status']) && $normalizedFilters['status'] !== '') {
            $query->where('status', $normalizedFilters['status']);
        }
        // Apply recently_used filter
        if (isset($normalizedFilters['recently_used']) && (bool) $normalizedFilters['recently_used'] === true) {
            $query->whereNotNull('last_used_at')->where('last_used_at', '>=', now()->subDays(30));
        }
        // Apply date filters
        if (isset($normalizedFilters['created_after']) && is_string($normalizedFilters['created_after'])) {
            $query->whereDate('created_at', '>=', $normalizedFilters['created_after']);
        }
        if (isset($normalizedFilters['created_before']) && is_string($normalizedFilters['created_before'])) {
            $query->whereDate('created_at', '<=', $normalizedFilters['created_before']);
        }
        // Apply relationships
        if (isset($normalizedFilters['with']) && is_array($normalizedFilters['with']) && count($normalizedFilters['with']) > 0) {
            /** @var array<int, string> $with */
            $with = array_values(array_filter($normalizedFilters['with'], fn ($v) => is_string($v)));
            $query->with($this->searchFormsGetSafeRelations($with));
        }
        $limit = 25;
        if (isset($normalizedFilters['limit']) && is_numeric($normalizedFilters['limit'])) {
            $limit = (int) $normalizedFilters['limit'];
        }
        $total = (clone $query)->count();
        $forms = $query->orderBy('submissions_count', 'desc')->orderBy('handle')->limit($limit)->get();

        return ['forms' => $forms, 'total' => $total];
    }

    /**
     * Get columns to select based on filters.
     *
     * @param  array  $filters  Request filters
     * @return array Array of column names
     */
    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, string>
     */
    private function searchFormsGetSelectColumns(array $filters): array
    {
        $base = ['id', 'handle', 'revision', 'status', 'restrict_public_access', 'submissions_count', 'last_used_at', 'created_at', 'updated_at'];
        // Add additional columns if relations are requested
        if (isset($filters['with']) && is_array($filters['with'])) {
            $with = $filters['with'];
            // fields relation removed
            if (in_array('entries', $with, true)) {
                // Entries relation doesn't need additional columns
            }
        }

        return $base;
    }

    /**
     * Get safe relations to load.
     *
     * @param  array  $relations  Requested relations
     * @return array Array of safe relation constraints
     */
    /**
     * @param  array<int, string>  $relations
     * @return array<int, string>
     */
    private function searchFormsGetSafeRelations(array $relations): array
    {
        $allowed = ['entries' => 'entries:id,form_id,subject,email,created_at'];

        return array_values(array_intersect_key($allowed, array_flip($relations)));
    }

    /**
     * Execute the form select options retrieval.
     *
     * @param  array<string, mixed>  $filters  Filter parameters for form selection
     * @return Collection<int, array{id:string,label:string,sublabel:?string}>
     */
    public function options(array $filters): Collection
    {
        $normalizedFilters = collect($filters)->mapWithKeys(static function ($value, string $key) {
            return [Str::snake($key) => $value];
        })->toArray();
        $query = FormModelRegistry::query()->withResolvedTranslations()->select(['id', 'handle', 'status', 'restrict_public_access', 'submissions_count']);
        // Apply search query
        $searchValue = $normalizedFilters['q'] ?? null;
        $search = is_string($searchValue) ? trim($searchValue) : '';
        if ($search !== '') {
            $lower = mb_strtolower($search);
            $query->where(function (Builder $q) use ($lower) {
                $q->whereRaw('LOWER(handle) LIKE ?', ['%'.$lower.'%'])->orWhereHas('translations', function (Builder $translations) use ($lower): void {
                    $translations->whereRaw('LOWER(name) LIKE ?', ['%'.$lower.'%']);
                });
            });
        }
        // Apply active_only filter
        if (isset($normalizedFilters['active_only']) && (bool) $normalizedFilters['active_only'] === true) {
            $query->where('status', 'active');
        }
        if (isset($normalizedFilters['status']) && is_string($normalizedFilters['status']) && $normalizedFilters['status'] !== '') {
            $query->where('status', $normalizedFilters['status']);
        }
        // Apply public_only filter
        if (isset($normalizedFilters['public_only']) && (bool) $normalizedFilters['public_only'] === true) {
            $query->where('restrict_public_access', false);
        }
        // Apply with_submissions filter
        if (isset($normalizedFilters['with_submissions']) && (bool) $normalizedFilters['with_submissions'] === true) {
            $query->where('submissions_count', '>', 0);
        }
        $options = [];
        $query->orderBy('handle')->limit(50)->get()->each(function (Form $form) use (&$options): void {
            $options[] = ['id' => $form->id, 'label' => $form->displayName(), 'sublabel' => $this->getFormSelectOptionsSublabelFromHandle($form->handle)];
        });

        return collect($options);
    }

    /**
     * Resolve the optional select-option sublabel from a form handle.
     */
    private function getFormSelectOptionsSublabelFromHandle(?string $handle): ?string
    {
        return $handle !== null && $handle !== '' ? $handle : null;
    }

    /**
     * Execute the form suggestions retrieval.
     *
     * @param  string  $query  Search query term
     * @param  int  $limit  Maximum number of suggestions to return
     * @return Collection<int, Form>
     */
    public function suggestions(string $query, int $limit = 10): Collection
    {
        $lower = mb_strtolower($query);

        return FormModelRegistry::query()->withResolvedTranslations()->select(['id', 'handle', 'submissions_count', 'last_used_at'])->where(function (Builder $q) use ($lower) {
            $q->whereRaw('LOWER(handle) LIKE ?', ['%'.$lower.'%'])->orWhereHas('translations', function (Builder $translations) use ($lower): void {
                $translations->whereRaw('LOWER(name) LIKE ?', ['%'.$lower.'%']);
            });
        })->orderBy('submissions_count', 'desc')->orderBy('handle')->limit($limit)->get();
    }

    /**
     * Filter by search term across multiple fields.
     *
     * @param  Builder<*>  $query  Query builder instance
     * @return Builder<*> Modified query builder
     */
    private function filterSearch(Builder $query, mixed $value): Builder
    {
        if (! is_string($value)) {
            return $query;
        }

        $searchTerm = '%'.mb_strtolower($value).'%';

        return $query->where(function (Builder $q) use ($searchTerm): void {
            $q->whereRaw('LOWER(handle) LIKE ?', [$searchTerm])
                ->orWhereHas('translations', function (Builder $translations) use ($searchTerm): void {
                    $translations->whereRaw('LOWER(name) LIKE ?', [$searchTerm]);
                });
        });
    }

    /**
     * Support semantic presets for last_used_at coming from FE select filter.
     * Accepts values: today | this_week | this_month | never | ''
     */
    /**
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    private function filterLastUsedAt(Builder $query, mixed $value): Builder
    {
        if (! is_string($value)) {
            throw new FormException('The last-used filter must be a string.');
        }

        $val = trim(mb_strtolower($value));

        return match ($val) {
            'today' => $query->whereDate('last_used_at', now()->toDateString()),
            'this_week' => $query->whereDate('last_used_at', '>=', now()->startOfWeek()->toDateString()),
            'this_month' => $query->whereDate('last_used_at', '>=', now()->startOfMonth()->toDateString()),
            'never' => $query->whereNull('last_used_at'),
            default => throw new FormException('Unknown last-used filter value.'),
        };
    }

    /**
     * Filter by availability window using a single key `availability`.
     * Supports operators: between, before, after, equals
     * - between: forms overlapping with [from, to]
     * - before:  forms with available_until <= date OR unrestricted
     * - after:   forms with available_from >= date OR unrestricted
     * - equals:  forms available at the given date/time OR unrestricted
     *
     * @param  Builder<*>  $query
     * @return Builder<*>
     */
    private function filterAvailability(
        Builder $query,
        mixed $val,
        string $operator,
    ): Builder {
        $from = null;
        $to = null;
        if (is_array($val)) {
            $fromValue = $val[0] ?? null;
            $toValue = $val[1] ?? null;
            $from = is_string($fromValue) || $fromValue instanceof DateTimeInterface ? $fromValue : null;
            $to = is_string($toValue) || $toValue instanceof DateTimeInterface ? $toValue : null;
        } elseif (is_string($val) || $val instanceof DateTimeInterface) {
            if ($val instanceof DateTimeInterface) {
                $from = $val;
                $to = null;
            } else {
                $parts = explode(',', $val, 2);
                $from = $parts[0];
                $to = $parts[1] ?? null;
            }
        } else {
            throw new FormException('The availability filter must be a date or date range.');
        }

        return match ($operator) {
            'between' => $query->where(function (Builder $q) use ($from, $to): void {
                $q->whereRaw('date_restricted = ?', [false])
                    ->orWhere(function (Builder $w) use ($from, $to): void {
                        if ($from !== null && $from !== '') {
                            $w->where(function (Builder $ww) use ($from): void {
                                $ww->whereNull('available_until')
                                    ->orWhereRaw('available_until >= ?', [$from]);
                            });
                        }
                        if ($to !== null && $to !== '') {
                            $w->where(function (Builder $ww) use ($to): void {
                                $ww->whereNull('available_from')
                                    ->orWhereRaw('available_from <= ?', [$to]);
                            });
                        }
                    });
            }),
            'before' => $query->where(function (Builder $q) use ($from): void {
                $q->whereRaw('date_restricted = ?', [false])
                    ->orWhere(function (Builder $w) use ($from): void {
                        if ($from !== null && $from !== '') {
                            $w->whereNotNull('available_until')->whereRaw('available_until <= ?', [$from]);
                        }
                    });
            }),
            'after' => $query->where(function (Builder $q) use ($from): void {
                $q->whereRaw('date_restricted = ?', [false])
                    ->orWhere(function (Builder $w) use ($from): void {
                        if ($from !== null && $from !== '') {
                            $w->whereNotNull('available_from')->whereRaw('available_from >= ?', [$from]);
                        }
                    });
            }),
            'equals' => $query->where(function (Builder $q) use ($from): void {
                $q->whereRaw('date_restricted = ?', [false])
                    ->orWhere(function (Builder $w) use ($from): void {
                        if ($from !== null && $from !== '') {
                            $w->where(function (Builder $ww) use ($from): void {
                                $ww->whereNull('available_from')->orWhereRaw('available_from <= ?', [$from]);
                            })->where(function (Builder $ww) use ($from): void {
                                $ww->whereNull('available_until')->orWhereRaw('available_until >= ?', [$from]);
                            });
                        }
                    });
            }),
            default => throw new FormException('Unsupported availability operator.'),
        };
    }
}
