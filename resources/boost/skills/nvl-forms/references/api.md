# Public API: nvl/forms

Generated from the package public contract. Use this reference to confirm fully qualified names, parameter order, defaults, native array shapes and return types. Read [usage](usage.md) for policy and operation semantics; declarations alone never grant authority.

Search for the relevant fully qualified service or contract and read only its matching symbol sections; this is a lookup catalog, not an instruction to load every declaration. `resolved` annotations expand imported aliases and domain type shapes. Framework inheritance and host-authored extensions remain outside this package contract.

## Symbols

- `Nvl\Forms\Contracts\CustomFormHandler`
- `Nvl\Forms\Contracts\EntrySubmissionCallback`
- `Nvl\Forms\Contracts\FormEntryDeletionPolicy`
- `Nvl\Forms\Contracts\FormEntryPrivacyPolicy`
- `Nvl\Forms\Contracts\FormErrorMapper`
- `Nvl\Forms\Contracts\FormRateLimiter`
- `Nvl\Forms\Contracts\FormRenderDataProvider`
- `Nvl\Forms\Contracts\FormSpamDetector`
- `Nvl\Forms\Contracts\OwnershipEntrySubmissionCallback`
- `Nvl\Forms\Database\Factories\AllowedOriginFactory`
- `Nvl\Forms\Database\Factories\FormAnalyticFactory`
- `Nvl\Forms\Database\Factories\FormEntryFactory`
- `Nvl\Forms\Database\Factories\FormFactory`
- `Nvl\Forms\Database\Factories\FormRateLimitFactory`
- `Nvl\Forms\Database\Factories\FormSubmissionReceiptFactory`
- `Nvl\Forms\Database\Factories\FormTranslationFactory`
- `Nvl\Forms\Enums\CorsPolicy`
- `Nvl\Forms\Enums\FormResponseCode`
- `Nvl\Forms\Enums\FormStatus`
- `Nvl\Forms\Enums\FormType`
- `Nvl\Forms\Enums\Resolvement`
- `Nvl\Forms\Events\FormChanged`
- `Nvl\Forms\Events\FormEntryChanged`
- `Nvl\Forms\Exceptions\FormException`
- `Nvl\Forms\Exceptions\FormOwnershipException`
- `Nvl\Forms\Exceptions\FormSubmissionRejectionException`
- `Nvl\Forms\Models\AllowedOrigin`
- `Nvl\Forms\Models\Form`
- `Nvl\Forms\Models\FormAnalytic`
- `Nvl\Forms\Models\FormEntry`
- `Nvl\Forms\Models\FormRateLimit`
- `Nvl\Forms\Models\FormSubmissionReceipt`
- `Nvl\Forms\Models\FormTranslation`
- `Nvl\Forms\Results\FormRateLimitAttemptResult`
- `Nvl\Forms\Services\CustomFormRegistry`
- `Nvl\Forms\Services\EntryCallbackRegistry`
- `Nvl\Forms\Services\FormAnalytics`
- `Nvl\Forms\Services\FormEntries`
- `Nvl\Forms\Services\FormEntryExportService`
- `Nvl\Forms\Services\FormLifecycle`
- `Nvl\Forms\Services\FormModelRegistry`
- `Nvl\Forms\Services\FormOriginAccessService`
- `Nvl\Forms\Services\FormRateLimitService`
- `Nvl\Forms\Services\FormReader`
- `Nvl\Forms\Services\FormRetention`
- `Nvl\Forms\Services\FormSubmissions`
- `Nvl\Forms\Services\PublicFormTokenService`
- `Nvl\Forms\Support\FormErrorMapperRegistry`
- `Nvl\Forms\Support\FormHandlerRegistry`
- `Nvl\Forms\Support\FormRenderDataRegistry`

## Nvl\Forms\Contracts\CustomFormHandler

```text
interface Nvl\Forms\Contracts\CustomFormHandler
public abstract function handle(Nvl\Forms\Models\Form $form, array $data, array $context): array
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param array<string, mixed> $data
@param array<string, mixed> $context
@return array{entry_id?: string, meta?: array<string, mixed>}
```

## Nvl\Forms\Contracts\EntrySubmissionCallback

```text
interface Nvl\Forms\Contracts\EntrySubmissionCallback
public abstract function after(Nvl\Forms\Models\Form $form, Nvl\Forms\Models\FormEntry $entry, array $context): void
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param FormEntry $entry
resolved @param Nvl\Forms\Models\FormEntry $entry
@param array<string, mixed> $context
```

## Nvl\Forms\Contracts\FormEntryDeletionPolicy

```text
interface Nvl\Forms\Contracts\FormEntryDeletionPolicy
public abstract function authorize(Nvl\Forms\Models\FormEntry $entry, ?Illuminate\Contracts\Auth\Authenticatable $actor=null): void
```

## Nvl\Forms\Contracts\FormEntryPrivacyPolicy

```text
interface Nvl\Forms\Contracts\FormEntryPrivacyPolicy
public abstract function authorize(string $operation, Nvl\Forms\Models\FormEntry $entry, ?Illuminate\Contracts\Auth\Authenticatable $actor=null): void
```

## Nvl\Forms\Contracts\FormErrorMapper

```text
interface Nvl\Forms\Contracts\FormErrorMapper
public abstract function map(Nvl\Forms\Models\Form $form, Nvl\Support\Exceptions\BusinessException $exception): ?array
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param BusinessException $exception
resolved @param Nvl\Support\Exceptions\BusinessException $exception
@return (array<string, mixed> | null)
```

## Nvl\Forms\Contracts\FormRateLimiter

```text
interface Nvl\Forms\Contracts\FormRateLimiter
public abstract function blockIpAddress(Nvl\Forms\Models\Form $form, Nvl\Forms\Models\FormRateLimit $rateLimit, ?int $durationMinutes=null, ?string $origin=null, ?string $userAgent=null, ?string $sessionId=null): void
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param FormRateLimit $rateLimit
resolved @param Nvl\Forms\Models\FormRateLimit $rateLimit
@param (int | null) $durationMinutes
@param (string | null) $origin
@param (string | null) $userAgent
@param (string | null) $sessionId
public abstract function cleanupExpiredRecords(): int
@return int
public abstract function consumeSubmissionAttempt(Nvl\Forms\Models\Form $form, string $ipAddress, ?string $origin=null, ?string $userAgent=null, ?string $sessionId=null): Nvl\Forms\Results\FormRateLimitAttemptResult
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param string $ipAddress
@param (string | null) $origin
@param (string | null) $userAgent
@param (string | null) $sessionId
@return FormRateLimitAttemptResult
resolved @return Nvl\Forms\Results\FormRateLimitAttemptResult
public abstract function getRateLimitStatus(Nvl\Forms\Models\Form $form, string $ipAddress): array
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param string $ipAddress
@return array{enabled: bool, remaining: (int | null), reset_at: (CarbonInterface | null), is_blocked: bool, blocked_until: (CarbonInterface | null), retry_after: int, violation_count: int}
resolved @return array{enabled: bool, remaining: (int | null), reset_at: (Carbon\CarbonInterface | null), is_blocked: bool, blocked_until: (Carbon\CarbonInterface | null), retry_after: int, violation_count: int}
public abstract function isRateLimited(Nvl\Forms\Models\Form $form, string $ipAddress): bool
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param string $ipAddress
@return bool
public abstract function recordSubmissionAttempt(Nvl\Forms\Models\Form $form, string $ipAddress, ?string $origin=null, ?string $userAgent=null, ?string $sessionId=null): void
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param string $ipAddress
@param (string | null) $origin
@param (string | null) $userAgent
@param (string | null) $sessionId
public abstract function unblockIpAddress(Nvl\Forms\Models\Form $form, string $ipAddress): void
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param string $ipAddress
public abstract function whitelistIpAddress(Nvl\Forms\Models\Form $form, string $ipAddress): void
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param string $ipAddress
```

## Nvl\Forms\Contracts\FormRenderDataProvider

```text
interface Nvl\Forms\Contracts\FormRenderDataProvider
public abstract function getData(Nvl\Forms\Models\Form $form, array $context): array
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param array<string, mixed> $context
@return array<string, mixed>
public abstract function getTranslations(Nvl\Forms\Models\Form $form): array
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@return array<string, mixed>
```

## Nvl\Forms\Contracts\FormSpamDetector

```text
interface Nvl\Forms\Contracts\FormSpamDetector
public abstract function calculateSpamScore(Nvl\Forms\Models\Form $form, array $data, string $ipAddress, ?string $userAgent=null, ?Nvl\Forms\Models\FormRateLimit $rateLimit=null, ?float $formLoadTime=null): float
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param array<string, mixed> $data
@param string $ipAddress
@param (string | null) $userAgent
@param (FormRateLimit | null) $rateLimit
resolved @param (Nvl\Forms\Models\FormRateLimit | null) $rateLimit
@param (float | null) $formLoadTime
@return float
public abstract function checkHoneypot(Nvl\Forms\Models\Form $form, array $data): bool
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param array<string, mixed> $data
@return bool
public abstract function shouldBlockSubmission(float $spamScore): bool
@param float $spamScore
@return bool
public abstract function shouldFlagSubmission(float $spamScore): bool
@param float $spamScore
@return bool
```

## Nvl\Forms\Contracts\OwnershipEntrySubmissionCallback

```text
interface Nvl\Forms\Contracts\OwnershipEntrySubmissionCallback
public abstract function after(Nvl\Forms\Models\Form $form, Nvl\Forms\Models\FormEntry $entry, array $context): void
@param array<string, mixed> $context
```

## Nvl\Forms\Database\Factories\AllowedOriginFactory

```text
final class Nvl\Forms\Database\Factories\AllowedOriginFactory extends Illuminate\Database\Eloquent\Factories\Factory
@extends Factory<AllowedOrigin>
resolved @extends Illuminate\Database\Eloquent\Factories\Factory<Nvl\Forms\Models\AllowedOrigin>
public function definition(): array
@return array<model-property<AllowedOrigin>, mixed>
resolved @return array<model-property<Nvl\Forms\Models\AllowedOrigin>, mixed>
public function forForm(Nvl\Forms\Models\Form $form): static
```

## Nvl\Forms\Database\Factories\FormAnalyticFactory

```text
final class Nvl\Forms\Database\Factories\FormAnalyticFactory extends Illuminate\Database\Eloquent\Factories\Factory
@extends Factory<FormAnalytic>
resolved @extends Illuminate\Database\Eloquent\Factories\Factory<Nvl\Forms\Models\FormAnalytic>
public function definition(): array
@return array<model-property<FormAnalytic>, mixed>
resolved @return array<model-property<Nvl\Forms\Models\FormAnalytic>, mixed>
public function forForm(Nvl\Forms\Models\Form $parent): static
```

## Nvl\Forms\Database\Factories\FormEntryFactory

```text
final class Nvl\Forms\Database\Factories\FormEntryFactory extends Illuminate\Database\Eloquent\Factories\Factory
@extends Factory<FormEntry>
resolved @extends Illuminate\Database\Eloquent\Factories\Factory<Nvl\Forms\Models\FormEntry>
public function definition(): array
@return array<model-property<FormEntry>, mixed>
resolved @return array<model-property<Nvl\Forms\Models\FormEntry>, mixed>
public function forForm(Nvl\Forms\Models\Form $form): static
```

## Nvl\Forms\Database\Factories\FormFactory

```text
final class Nvl\Forms\Database\Factories\FormFactory extends Illuminate\Database\Eloquent\Factories\Factory
@extends Factory<Form>
resolved @extends Illuminate\Database\Eloquent\Factories\Factory<Nvl\Forms\Models\Form>
public function configure(): static
public function definition(): array
@return array<model-property<Form>, mixed>
resolved @return array<model-property<Nvl\Forms\Models\Form>, mixed>
public function modelName(): string
public function withoutTranslations(): static
```

## Nvl\Forms\Database\Factories\FormRateLimitFactory

```text
final class Nvl\Forms\Database\Factories\FormRateLimitFactory extends Illuminate\Database\Eloquent\Factories\Factory
@extends Factory<FormRateLimit>
resolved @extends Illuminate\Database\Eloquent\Factories\Factory<Nvl\Forms\Models\FormRateLimit>
public function definition(): array
@return array<model-property<FormRateLimit>, mixed>
resolved @return array<model-property<Nvl\Forms\Models\FormRateLimit>, mixed>
public function forForm(Nvl\Forms\Models\Form $parent): static
```

## Nvl\Forms\Database\Factories\FormSubmissionReceiptFactory

```text
final class Nvl\Forms\Database\Factories\FormSubmissionReceiptFactory extends Illuminate\Database\Eloquent\Factories\Factory
@extends Factory<FormSubmissionReceipt>
resolved @extends Illuminate\Database\Eloquent\Factories\Factory<Nvl\Forms\Models\FormSubmissionReceipt>
public function definition(): array
@return array<model-property<FormSubmissionReceipt>, mixed>
resolved @return array<model-property<Nvl\Forms\Models\FormSubmissionReceipt>, mixed>
public function forForm(Nvl\Forms\Models\Form $form): static
```

## Nvl\Forms\Database\Factories\FormTranslationFactory

```text
final class Nvl\Forms\Database\Factories\FormTranslationFactory extends Illuminate\Database\Eloquent\Factories\Factory
@extends Factory<FormTranslation>
resolved @extends Illuminate\Database\Eloquent\Factories\Factory<Nvl\Forms\Models\FormTranslation>
public function definition(): array
@return array<model-property<FormTranslation>, mixed>
resolved @return array<model-property<Nvl\Forms\Models\FormTranslation>, mixed>
public function forForm(Nvl\Forms\Models\Form $parent): static
```

## Nvl\Forms\Enums\CorsPolicy

```text
enum Nvl\Forms\Enums\CorsPolicy implements BackedEnum, UnitEnum: string
public const Nvl\Forms\Enums\CorsPolicy Custom=Nvl\Forms\Enums\CorsPolicy::Custom="custom"
public const Nvl\Forms\Enums\CorsPolicy Moderate=Nvl\Forms\Enums\CorsPolicy::Moderate="moderate"
public const Nvl\Forms\Enums\CorsPolicy Permissive=Nvl\Forms\Enums\CorsPolicy::Permissive="permissive"
public const Nvl\Forms\Enums\CorsPolicy Strict=Nvl\Forms\Enums\CorsPolicy::Strict="strict"
public function description(): string
public function getDefaultSettings(): array
@return array{allowCredentials: bool, allowWildcards: bool, maxAge: int, allowedMethods: list<string>, allowedHeaders: list<string>}
public function getLabel(): string
public static function options(): array
@return list<array{value: string, label: string}>
public function supportsWildcards(): bool
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Forms\Enums\FormResponseCode

```text
enum Nvl\Forms\Enums\FormResponseCode implements BackedEnum, Nvl\Support\Contracts\ResponseCode, UnitEnum: string
public const Nvl\Forms\Enums\FormResponseCode CreateFailed=Nvl\Forms\Enums\FormResponseCode::CreateFailed="create_failed"
public const Nvl\Forms\Enums\FormResponseCode Created=Nvl\Forms\Enums\FormResponseCode::Created="created"
public const Nvl\Forms\Enums\FormResponseCode DeleteFailed=Nvl\Forms\Enums\FormResponseCode::DeleteFailed="delete_failed"
public const Nvl\Forms\Enums\FormResponseCode Deleted=Nvl\Forms\Enums\FormResponseCode::Deleted="deleted"
public const Nvl\Forms\Enums\FormResponseCode Duplicated=Nvl\Forms\Enums\FormResponseCode::Duplicated="duplicated"
public const Nvl\Forms\Enums\FormResponseCode OperationFailed=Nvl\Forms\Enums\FormResponseCode::OperationFailed="operation_failed"
public const Nvl\Forms\Enums\FormResponseCode OwnershipDenied=Nvl\Forms\Enums\FormResponseCode::OwnershipDenied="ownership_denied"
public const Nvl\Forms\Enums\FormResponseCode SubmissionRejected=Nvl\Forms\Enums\FormResponseCode::SubmissionRejected="submission_rejected"
public const Nvl\Forms\Enums\FormResponseCode UpdateFailed=Nvl\Forms\Enums\FormResponseCode::UpdateFailed="update_failed"
public const Nvl\Forms\Enums\FormResponseCode Updated=Nvl\Forms\Enums\FormResponseCode::Updated="updated"
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Forms\Enums\FormStatus

```text
enum Nvl\Forms\Enums\FormStatus implements BackedEnum, UnitEnum: string
public const Nvl\Forms\Enums\FormStatus ACTIVE=Nvl\Forms\Enums\FormStatus::ACTIVE="active"
public const Nvl\Forms\Enums\FormStatus ARCHIVED=Nvl\Forms\Enums\FormStatus::ARCHIVED="archived"
public const Nvl\Forms\Enums\FormStatus DRAFT=Nvl\Forms\Enums\FormStatus::DRAFT="draft"
public const Nvl\Forms\Enums\FormStatus PAUSED=Nvl\Forms\Enums\FormStatus::PAUSED="paused"
public function canAcceptSubmissions(): bool
public function canBeEdited(): bool
public function colorClass(): string
public function description(): string
public function getLabel(): string
public static function options(): array
@return list<array{value: string, label: string}>
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Forms\Enums\FormType

```text
enum Nvl\Forms\Enums\FormType implements BackedEnum, UnitEnum: string
public const Nvl\Forms\Enums\FormType IFRAME=Nvl\Forms\Enums\FormType::IFRAME="iframe"
public const Nvl\Forms\Enums\FormType LANDING_PAGE=Nvl\Forms\Enums\FormType::LANDING_PAGE="landing_page"
public function getLabel(): string
public static function options(): array
@return list<array{value: string, label: string}>
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Forms\Enums\Resolvement

```text
enum Nvl\Forms\Enums\Resolvement implements BackedEnum, UnitEnum: string
public const Nvl\Forms\Enums\Resolvement CUSTOM=Nvl\Forms\Enums\Resolvement::CUSTOM="custom"
public const Nvl\Forms\Enums\Resolvement ENTRIES=Nvl\Forms\Enums\Resolvement::ENTRIES="entries"
public function getLabel(): string
public static function options(): array
@return list<array{value: string, label: string}>
public readonly string $name
public static function cases(): array
public readonly string $value
public static function from(string|int $value): static
public static function tryFrom(string|int $value): ?static
```

## Nvl\Forms\Events\FormChanged

```text
final class Nvl\Forms\Events\FormChanged implements Nvl\Support\Contracts\DomainEvent
public function __construct(string $formId, string $operation, string|int|null $actorId=null, array $context=[], int $schemaVersion=1): mixed
@param array<string, (bool | float | int | string | null)> $context
public static function for(Nvl\Forms\Models\Form $form, string $operation, ?Illuminate\Contracts\Auth\Authenticatable $actor=null, array $context=[]): self
@param array<string, (bool | float | int | string | null)> $context
public function schemaVersion(): int
public readonly string|int|null $actorId
public readonly array $context
@param array<string, (bool | float | int | string | null)> $context
public readonly string $formId
public readonly string $operation
public readonly int $schemaVersion
```

## Nvl\Forms\Events\FormEntryChanged

```text
final class Nvl\Forms\Events\FormEntryChanged implements Nvl\Support\Contracts\DomainEvent
public function __construct(string $formId, string $entryId, string $operation, string|int|null $actorId=null, array $context=[], int $schemaVersion=1): mixed
@param array<string, (bool | float | int | string | null)> $context
public static function for(Nvl\Forms\Models\Form $form, Nvl\Forms\Models\FormEntry|string $entry, string $operation, ?Illuminate\Contracts\Auth\Authenticatable $actor=null, array $context=[]): self
@param array<string, (bool | float | int | string | null)> $context
public function schemaVersion(): int
public readonly string|int|null $actorId
public readonly array $context
@param array<string, (bool | float | int | string | null)> $context
public readonly string $entryId
public readonly string $formId
public readonly string $operation
public readonly int $schemaVersion
```

## Nvl\Forms\Exceptions\FormException

```text
class Nvl\Forms\Exceptions\FormException extends Exception implements Nvl\Support\Contracts\RespondableException
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): ?string
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Forms\Exceptions\FormOwnershipException

```text
final class Nvl\Forms\Exceptions\FormOwnershipException extends Nvl\Forms\Exceptions\FormException
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): ?string
public function responseHeaders(): array
@return array<string, string>
public function suggestedStatus(): int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Forms\Exceptions\FormSubmissionRejectionException

```text
final class Nvl\Forms\Exceptions\FormSubmissionRejectionException extends Nvl\Forms\Exceptions\FormException
public function __construct(string $message, int $statusCode, int $retryAfterSeconds=0): mixed
@param string $message
@param int $statusCode
public function package(): string
public function publicContext(): array
@return array<string, mixed>
public function responseCode(): ?string
public function responseHeaders(): array
@return array<string, string>
public function retryAfterSeconds(): int
public function statusCode(): int
public function suggestedStatus(): int
public function translationKey(): ?string
public function translationParameters(): array
@return array<string, (scalar | null)>
```

## Nvl\Forms\Models\AllowedOrigin

```text
class Nvl\Forms\Models\AllowedOrigin extends Illuminate\Database\Eloquent\Model implements Nvl\Support\Ownership\Contracts\Ownable
public const string OWNERSHIP_RESOURCE="forms.origins"
public function delete(): mixed
@return ($this is Pivot ? (bool | int | null) : (bool | null))
resolved @return ($this is Illuminate\Database\Eloquent\Relations\Pivot ? (bool | int | null) : (bool | null))
public function form(): Illuminate\Database\Eloquent\Relations\BelongsTo
@return BelongsTo<Form, $this>
resolved @return Illuminate\Database\Eloquent\Relations\BelongsTo<Nvl\Forms\Models\Form, $this>
public function fresh(mixed $with=[]): ?static
@param (array<array-key, mixed> | string) $with
public function getConnectionName(): ?string
public function getTable(): string
public function newQueryForRestoration(mixed $ids): Illuminate\Database\Eloquent\Builder
@param (int | string | array<array-key, (int | string)>) $ids
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\AllowedOrigin>
public static function ownershipDefinition(): Nvl\Support\Ownership\ValueObjects\OwnershipDefinition
public function refresh(): static
public function refreshForUpdate(): static
public function save(array $options=[]): bool
@param array<string, mixed> $options
public function scopeActive(Illuminate\Database\Eloquent\Builder $query): void
@param Builder<AllowedOrigin> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\AllowedOrigin> $query
public function scopeRecentlyUsed(Illuminate\Database\Eloquent\Builder $query): void
@param Builder<AllowedOrigin> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\AllowedOrigin> $query
@property (array<string, mixed> | null) $cors_settings
@property Carbon $created_at
resolved @property Carbon\Carbon $created_at
@property (string | null) $description
@property-read Form $form
resolved @property-read Nvl\Forms\Models\Form $form
@property string $form_id
@property string $id
@property bool $is_active
@property (Carbon | null) $last_used_at
resolved @property (Carbon\Carbon | null) $last_used_at
@property string $origin
@property Carbon $updated_at
resolved @property Carbon\Carbon $updated_at
@property int $usage_count
```

## Nvl\Forms\Models\Form

```text
class Nvl\Forms\Models\Form extends Illuminate\Database\Eloquent\Model implements Nvl\Support\Ownership\Contracts\Ownable, Nvl\Translatable\Contracts\TranslatableModel
public const string OWNERSHIP_RESOURCE="forms.forms"
public function allowedOrigins(): Illuminate\Database\Eloquent\Relations\HasMany
@return HasMany<AllowedOrigin, $this>
resolved @return Illuminate\Database\Eloquent\Relations\HasMany<Nvl\Forms\Models\AllowedOrigin, $this>
public function allowsMultipleRegistrations(): bool
@return bool
public function analytics(): Illuminate\Database\Eloquent\Relations\HasMany
@return HasMany<FormAnalytic, $this>
resolved @return Illuminate\Database\Eloquent\Relations\HasMany<Nvl\Forms\Models\FormAnalytic, $this>
public function delete(): mixed
@return ($this is Pivot ? (bool | int | null) : (bool | null))
resolved @return ($this is Illuminate\Database\Eloquent\Relations\Pivot ? (bool | int | null) : (bool | null))
public function displayDescription(?string $locale=null): ?string
public function displayName(?string $locale=null): string
public function entries(): Illuminate\Database\Eloquent\Relations\HasMany
@return HasMany<FormEntry, $this>
resolved @return Illuminate\Database\Eloquent\Relations\HasMany<Nvl\Forms\Models\FormEntry, $this>
public function fresh(mixed $with=[]): ?static
@param (array<array-key, mixed> | string) $with
public function getAllTranslations(): Illuminate\Database\Eloquent\Collection
@return Collection<int, Model>
resolved @return Illuminate\Database\Eloquent\Collection<int, Illuminate\Database\Eloquent\Model>
public function getAvailableLocales(): array
@return list<string>
public function getConnectionName(): ?string
public function getCurrentLocale(): string
public function getRelationValue(mixed $key): mixed
@param string $key
public function getTable(): string
public function getTranslatedAttributes(?string $locale=null): array
@return array<string, mixed>
public function getTranslation(?string $locale=null, bool $withFallback=true): ?Illuminate\Database\Eloquent\Model
public function hasTranslation(?string $locale=null): bool
public function identifier(): string
public function isAvailableNow(): bool
@return bool
public function isPubliclyAvailableNow(): bool
@return bool
public function isTranslatableAttribute(string $key): bool
public function localizedContent(?string $locale=null): array
@return array<string, mixed>
public function newQueryForRestoration(mixed $ids): Illuminate\Database\Eloquent\Builder
@param (int | string | array<array-key, (int | string)>) $ids
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form>
public static function ownershipDefinition(): Nvl\Support\Ownership\ValueObjects\OwnershipDefinition
public function rateLimits(): Illuminate\Database\Eloquent\Relations\HasMany
@return HasMany<FormRateLimit, $this>
resolved @return Illuminate\Database\Eloquent\Relations\HasMany<Nvl\Forms\Models\FormRateLimit, $this>
public function refresh(): static
public function refreshForUpdate(): static
public function resolveTranslation(string $field, ?string $locale=null): Nvl\Translatable\TranslationResolution
public function save(array $options=[]): bool
@param array<string, mixed> $options
public function scopeActive(Illuminate\Database\Eloquent\Builder $query): void
@param Builder<Form> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form> $query
public function scopeOrderByTranslated(Illuminate\Database\Eloquent\Builder $query, string $field, string $direction="asc", ?string $locale=null): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form>
public function scopeRecentlyUsed(Illuminate\Database\Eloquent\Builder $query): void
@param Builder<Form> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form> $query
public function scopeWhereTranslated(Illuminate\Database\Eloquent\Builder $query, string $field, mixed $operator, mixed $value=\Nvl\Translatable\Enums\TranslationComparisonValue::Omitted, ?string $locale=null): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form>
public function scopeWhereTranslationNotNull(Illuminate\Database\Eloquent\Builder $query, string $field, ?string $locale=null): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form>
public function scopeWhereTranslationNull(Illuminate\Database\Eloquent\Builder $query, string $field, ?string $locale=null): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form>
public function scopeWithAllTranslations(Illuminate\Database\Eloquent\Builder $query): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form>
public function scopeWithResolvedTranslations(Illuminate\Database\Eloquent\Builder $query, ?string $locale=null): Illuminate\Database\Eloquent\Builder
@param Builder<static> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form> $query
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form>
public function scopeWithSubmissions(Illuminate\Database\Eloquent\Builder $query): void
@param Builder<Form> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form> $query
public function setLocale(string $locale): self
public function translated(string $field, ?string $locale=null): mixed
public function translation(?string $locale=null): Illuminate\Database\Eloquent\Relations\HasOne
@return HasOne<Model, $this>
resolved @return Illuminate\Database\Eloquent\Relations\HasOne<Illuminate\Database\Eloquent\Model, $this>
public function translationDefinition(): Nvl\Translatable\RelatedTranslationDefinition
public function translationPayloads(): array
@return array<string, array<string, mixed>>
public function translationResourceKey(): int|string
public function translations(): Illuminate\Database\Eloquent\Relations\HasMany
@return HasMany<Model, $this>
resolved @return Illuminate\Database\Eloquent\Relations\HasMany<Illuminate\Database\Eloquent\Model, $this>
@method static Builder<static> orderByTranslated(string $field, string $direction = 'asc', ?string $locale = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form> orderByTranslated(string $field, string $direction = 'asc', ?string $locale = null)
@method static Builder<static> whereTranslated(string $field, mixed $operator, mixed $value = TranslationComparisonValue::Omitted, ?string $locale = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form> whereTranslated(string $field, mixed $operator, mixed $value = Nvl\Translatable\Enums\TranslationComparisonValue::Omitted, ?string $locale = null)
@method static Builder<static> whereTranslationNotNull(string $field, ?string $locale = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form> whereTranslationNotNull(string $field, ?string $locale = null)
@method static Builder<static> whereTranslationNull(string $field, ?string $locale = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form> whereTranslationNull(string $field, ?string $locale = null)
@method static Builder<static> withAllTranslations()
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form> withAllTranslations()
@method static Builder<static> withResolvedTranslations(?string $locale = null)
resolved @method static Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\Form> withResolvedTranslations(?string $locale = null)
@property bool $allow_multiple_registrations
@property-read Collection<int, AllowedOrigin> $allowedOrigins
resolved @property-read Illuminate\Database\Eloquent\Collection<int, Nvl\Forms\Models\AllowedOrigin> $allowedOrigins
@property-read Collection<int, FormAnalytic> $analytics
resolved @property-read Illuminate\Database\Eloquent\Collection<int, Nvl\Forms\Models\FormAnalytic> $analytics
@property (Carbon | null) $available_from
resolved @property (Carbon\Carbon | null) $available_from
@property (Carbon | null) $available_until
resolved @property (Carbon\Carbon | null) $available_until
@property (array<string, mixed> | null) $cors_settings
@property Carbon $created_at
resolved @property Carbon\Carbon $created_at
@property bool $date_restricted
@property (Carbon | null) $deleted_at
resolved @property (Carbon\Carbon | null) $deleted_at
@property bool $enable_honeypot
@property bool $enable_rate_limiting
@property-read Collection<int, FormEntry> $entries
resolved @property-read Illuminate\Database\Eloquent\Collection<int, Nvl\Forms\Models\FormEntry> $entries
@property (Carbon | null) $first_used_at
resolved @property (Carbon\Carbon | null) $first_used_at
@property string $handle
@property string $id
@property (Carbon | null) $last_used_at
resolved @property (Carbon\Carbon | null) $last_used_at
@property (array<string, mixed> | null) $options
@property-read Collection<int, FormRateLimit> $rateLimits
resolved @property-read Illuminate\Database\Eloquent\Collection<int, Nvl\Forms\Models\FormRateLimit> $rateLimits
@property int $rate_limit_per_hour
@property bool $require_csrf
@property Resolvement $resolvement
resolved @property Nvl\Forms\Enums\Resolvement $resolvement
@property bool $restrict_public_access
@property int $revision
@property int $spam_count
@property FormStatus $status
resolved @property Nvl\Forms\Enums\FormStatus $status
@property int $submissions_count
@property-read Collection<int, FormTranslation> $translations
resolved @property-read Illuminate\Database\Eloquent\Collection<int, Nvl\Forms\Models\FormTranslation> $translations
@property FormType $type
resolved @property Nvl\Forms\Enums\FormType $type
@property Carbon $updated_at
resolved @property Carbon\Carbon $updated_at
@property int $views_count
```

## Nvl\Forms\Models\FormAnalytic

```text
class Nvl\Forms\Models\FormAnalytic extends Illuminate\Database\Eloquent\Model implements Nvl\Support\Ownership\Contracts\Ownable
public const string OWNERSHIP_RESOURCE="forms.analytics"
public function delete(): mixed
@return ($this is Pivot ? (bool | int | null) : (bool | null))
resolved @return ($this is Illuminate\Database\Eloquent\Relations\Pivot ? (bool | int | null) : (bool | null))
public function form(): Illuminate\Database\Eloquent\Relations\BelongsTo
@return BelongsTo<Form, $this>
resolved @return Illuminate\Database\Eloquent\Relations\BelongsTo<Nvl\Forms\Models\Form, $this>
public function fresh(mixed $with=[]): ?static
@param (array<array-key, mixed> | string) $with
public function getConnectionName(): ?string
public function getTable(): string
public function newEloquentBuilder(mixed $query): Nvl\Forms\Builders\FormAnalyticBuilder
@param Builder $query
resolved @param Illuminate\Database\Query\Builder $query
@return FormAnalyticBuilder<FormAnalytic>
resolved @return Nvl\Forms\Builders\FormAnalyticBuilder<Nvl\Forms\Models\FormAnalytic>
public function newQueryForRestoration(mixed $ids): Illuminate\Database\Eloquent\Builder
@param (int | string | array<array-key, (int | string)>) $ids
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\FormAnalytic>
public static function ownershipDefinition(): Nvl\Support\Ownership\ValueObjects\OwnershipDefinition
public function refresh(): static
public function refreshForUpdate(): static
public function save(array $options=[]): bool
@param array<string, mixed> $options
@property Carbon $created_at
resolved @property Carbon\Carbon $created_at
@property FormAnalyticEventType $event_type
resolved @property Nvl\Forms\Enums\FormAnalyticEventType $event_type
@property-read Form $form
resolved @property-read Nvl\Forms\Models\Form $form
@property string $form_id
@property string $id
@property (string | null) $ip_address
@property (array<string, mixed> | null) $metadata
@property (string | null) $origin
@property (string | null) $session_id
@property Carbon $updated_at
resolved @property Carbon\Carbon $updated_at
@property (string | null) $user_agent
```

## Nvl\Forms\Models\FormEntry

```text
class Nvl\Forms\Models\FormEntry extends Illuminate\Database\Eloquent\Model implements Nvl\Support\Ownership\Contracts\Ownable
public const string OWNERSHIP_RESOURCE="forms.entries"
public function delete(): mixed
@return ($this is Pivot ? (bool | int | null) : (bool | null))
resolved @return ($this is Illuminate\Database\Eloquent\Relations\Pivot ? (bool | int | null) : (bool | null))
public function form(): Illuminate\Database\Eloquent\Relations\BelongsTo
@return BelongsTo<Form, $this>
resolved @return Illuminate\Database\Eloquent\Relations\BelongsTo<Nvl\Forms\Models\Form, $this>
public function fresh(mixed $with=[]): ?static
@param (array<array-key, mixed> | string) $with
public function getConnectionName(): ?string
public function getFullNameAttribute(): ?string
@return (string | null)
public function getSecurityFlag(string $key, mixed $default=null): mixed
@param string $key
@param (mixed | null) $default
@return mixed
public function getTable(): string
public function hasContactInfo(): bool
@return bool
public function identifier(): string
public function isIdempotentReplay(): bool
public function markAsIdempotentReplay(): self
public function newEloquentBuilder(mixed $query): Nvl\Forms\Builders\FormEntryBuilder
@param Builder $query
resolved @param Illuminate\Database\Query\Builder $query
@return FormEntryBuilder<FormEntry>
resolved @return Nvl\Forms\Builders\FormEntryBuilder<Nvl\Forms\Models\FormEntry>
public function newQueryForRestoration(mixed $ids): Illuminate\Database\Eloquent\Builder
@param (int | string | array<array-key, (int | string)>) $ids
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\FormEntry>
public static function ownershipDefinition(): Nvl\Support\Ownership\ValueObjects\OwnershipDefinition
public function refresh(): static
public function refreshForUpdate(): static
public function save(array $options=[]): bool
@param array<string, mixed> $options
public function setSecurityFlag(string $key, mixed $value): void
@param string $key
@param mixed $value
@property (string | null) $address
@property (Carbon | null) $anonymized_at
resolved @property (Carbon\Carbon | null) $anonymized_at
@property (string | null) $body
@property (Carbon | null) $created_at
resolved @property (Carbon\Carbon | null) $created_at
@property (string | null) $email
@property (string | null) $first_name
@property-read Form $form
resolved @property-read Nvl\Forms\Models\Form $form
@property string $form_id
@property string $id
@property (string | null) $idempotency_key
@property (string | null) $ip_address
@property bool $is_spam
@property (string | null) $last_name
@property (string | null) $payload_digest
@property (string | null) $phone
@property (Carbon | null) $redacted_at
resolved @property (Carbon\Carbon | null) $redacted_at
@property (string | null) $registration_fingerprint
@property (array<string, mixed> | null) $security_flags
@property (string | null) $session_id
@property (int | null) $spam_score
@property (string | null) $subject
@property (array<string, mixed> | null) $submission_data
@property string $submitted_from
@property (Carbon | null) $updated_at
resolved @property (Carbon\Carbon | null) $updated_at
@property (string | null) $user_agent
```

## Nvl\Forms\Models\FormRateLimit

```text
class Nvl\Forms\Models\FormRateLimit extends Illuminate\Database\Eloquent\Model implements Nvl\Support\Ownership\Contracts\Ownable
public const string OWNERSHIP_RESOURCE="forms.rates"
public function delete(): mixed
@return ($this is Pivot ? (bool | int | null) : (bool | null))
resolved @return ($this is Illuminate\Database\Eloquent\Relations\Pivot ? (bool | int | null) : (bool | null))
public function form(): Illuminate\Database\Eloquent\Relations\BelongsTo
@return BelongsTo<Form, $this>
resolved @return Illuminate\Database\Eloquent\Relations\BelongsTo<Nvl\Forms\Models\Form, $this>
public function fresh(mixed $with=[]): ?static
@param (array<array-key, mixed> | string) $with
public function getConnectionName(): ?string
public function getTable(): string
public function newQueryForRestoration(mixed $ids): Illuminate\Database\Eloquent\Builder
@param (int | string | array<array-key, (int | string)>) $ids
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\FormRateLimit>
public static function ownershipDefinition(): Nvl\Support\Ownership\ValueObjects\OwnershipDefinition
public function refresh(): static
public function refreshForUpdate(): static
public function save(array $options=[]): bool
@param array<string, mixed> $options
public function scopeActiveWindow(Illuminate\Database\Eloquent\Builder $query): void
@param Builder<FormRateLimit> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\FormRateLimit> $query
public function scopeBlocked(Illuminate\Database\Eloquent\Builder $query): void
@param Builder<FormRateLimit> $query
resolved @param Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\FormRateLimit> $query
@property (Carbon | null) $blocked_until
resolved @property (Carbon\Carbon | null) $blocked_until
@property Carbon $created_at
resolved @property Carbon\Carbon $created_at
@property-read Form $form
resolved @property-read Nvl\Forms\Models\Form $form
@property string $form_id
@property string $id
@property string $ip_address
@property bool $is_blocked
@property Carbon $last_submission_at
resolved @property Carbon\Carbon $last_submission_at
@property int $submission_count
@property Carbon $updated_at
resolved @property Carbon\Carbon $updated_at
@property int $violation_count
@property Carbon $window_start
resolved @property Carbon\Carbon $window_start
```

## Nvl\Forms\Models\FormSubmissionReceipt

```text
final class Nvl\Forms\Models\FormSubmissionReceipt extends Illuminate\Database\Eloquent\Model implements Nvl\Support\Ownership\Contracts\Ownable
public const string OWNERSHIP_RESOURCE="forms.receipts"
public function delete(): mixed
@return ($this is Pivot ? (bool | int | null) : (bool | null))
resolved @return ($this is Illuminate\Database\Eloquent\Relations\Pivot ? (bool | int | null) : (bool | null))
public function form(): Illuminate\Database\Eloquent\Relations\BelongsTo
@return BelongsTo<Form, $this>
resolved @return Illuminate\Database\Eloquent\Relations\BelongsTo<Nvl\Forms\Models\Form, $this>
public function fresh(mixed $with=[]): ?static
@param (array<array-key, mixed> | string) $with
public function getConnectionName(): ?string
public function getTable(): string
public function hasBookkeepingWarning(): bool
public function newQueryForRestoration(mixed $ids): Illuminate\Database\Eloquent\Builder
@param (int | string | array<array-key, (int | string)>) $ids
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\FormSubmissionReceipt>
public static function ownershipDefinition(): Nvl\Support\Ownership\ValueObjects\OwnershipDefinition
public function refresh(): static
public function refreshForUpdate(): static
public function save(array $options=[]): bool
@param array<string, mixed> $options
@property Carbon $created_at
resolved @property Carbon\Carbon $created_at
@property-read Form $form
resolved @property-read Nvl\Forms\Models\Form $form
@property string $form_id
@property string $id
@property (string | null) $idempotency_key
@property string $payload_digest
@property (string | null) $registration_fingerprint
@property (string | null) $result_id
@property FormSubmissionReceiptState $state
resolved @property Nvl\Forms\Enums\FormSubmissionReceiptState $state
@property Carbon $updated_at
resolved @property Carbon\Carbon $updated_at
```

## Nvl\Forms\Models\FormTranslation

```text
final class Nvl\Forms\Models\FormTranslation extends Illuminate\Database\Eloquent\Model implements Nvl\Support\Ownership\Contracts\Ownable
public const string OWNERSHIP_RESOURCE="forms.translations"
public const string TABLE=\Nvl\Forms\Definitions\Tables\FormsTables::I18n
public function delete(): mixed
@return ($this is Pivot ? (bool | int | null) : (bool | null))
resolved @return ($this is Illuminate\Database\Eloquent\Relations\Pivot ? (bool | int | null) : (bool | null))
public function form(): Illuminate\Database\Eloquent\Relations\BelongsTo
@return BelongsTo<Form, $this>
resolved @return Illuminate\Database\Eloquent\Relations\BelongsTo<Nvl\Forms\Models\Form, $this>
public function fresh(mixed $with=[]): ?static
@param (array<array-key, mixed> | string) $with
public function getConnectionName(): ?string
public function getTable(): string
public function newQueryForRestoration(mixed $ids): Illuminate\Database\Eloquent\Builder
@param (int | string | array<array-key, (int | string)>) $ids
@return Builder<static>
resolved @return Illuminate\Database\Eloquent\Builder<Nvl\Forms\Models\FormTranslation>
public static function ownershipDefinition(): Nvl\Support\Ownership\ValueObjects\OwnershipDefinition
public function refresh(): static
public function refreshForUpdate(): static
public function save(array $options=[]): bool
@param array<string, mixed> $options
@property (array<string, mixed> | null) $content
@property (string | null) $description
@property string $form_id
@property string $id
@property string $locale
@property (string | null) $name
@property (string | null) $submit_button_label
@property (string | null) $success_message
@property (string | null) $success_title
```

## Nvl\Forms\Results\FormRateLimitAttemptResult

```text
final readonly class Nvl\Forms\Results\FormRateLimitAttemptResult
public function __construct(bool $allowed, int $retryAfterSeconds, ?Carbon\CarbonInterface $blockedUntil, int $submissionCount, int $violationCount, ?Nvl\Forms\Models\FormRateLimit $rateLimit): mixed
public readonly bool $allowed
public readonly ?Carbon\CarbonInterface $blockedUntil
public readonly ?Nvl\Forms\Models\FormRateLimit $rateLimit
public readonly int $retryAfterSeconds
public readonly int $submissionCount
public readonly int $violationCount
```

## Nvl\Forms\Services\CustomFormRegistry

```text
final readonly class Nvl\Forms\Services\CustomFormRegistry
public function __construct(Illuminate\Contracts\Container\Container $container, Nvl\Forms\Support\FormHandlerRegistry $registry): mixed
@param Container $container
resolved @param Illuminate\Contracts\Container\Container $container
@param FormHandlerRegistry $registry
resolved @param Nvl\Forms\Support\FormHandlerRegistry $registry
public function resolve(Nvl\Forms\Models\Form $form): ?Nvl\Forms\Contracts\CustomFormHandler
@throws BindingResolutionException
resolved @throws Illuminate\Contracts\Container\BindingResolutionException
```

## Nvl\Forms\Services\EntryCallbackRegistry

```text
final class Nvl\Forms\Services\EntryCallbackRegistry
public function __construct(Illuminate\Contracts\Container\Container $container): mixed
@param Container $container
resolved @param Illuminate\Contracts\Container\Container $container
public function clear(): void
public function dispatchOwnership(Nvl\Forms\Models\Form $form, Nvl\Forms\Models\FormEntry $entry, array $context): void
@param array<string, mixed> $context
public function forget(string $handle): void
@param string $handle
public function hasLegacyCallbacks(): bool
public function register(string $handle, Nvl\Forms\Contracts\EntrySubmissionCallback|callable|string|array $callbacks): void
@param string $handle
@param (EntrySubmissionCallback | callable | string | array<int, (EntrySubmissionCallback | callable | string)>) $callbacks
resolved @param (Nvl\Forms\Contracts\EntrySubmissionCallback | callable | string | array<int, (Nvl\Forms\Contracts\EntrySubmissionCallback | callable | string)>) $callbacks
public function registerOwnership(string $handle, string $callbackClass): void
```

## Nvl\Forms\Services\FormAnalytics

```text
final class Nvl\Forms\Services\FormAnalytics
public function __construct(Nvl\Support\Ownership\Contracts\OwnershipBoundary $boundary, Nvl\Forms\Services\FormCounterWriter $counters, Nvl\Forms\Services\FormOriginAccessService $originAccess): mixed
public function originUsage(Nvl\Forms\Models\AllowedOrigin $allowedOrigin): void
@param AllowedOrigin $allowedOrigin
resolved @param Nvl\Forms\Models\AllowedOrigin $allowedOrigin
public function rateLimit(Nvl\Forms\Models\Form|string $form, string $ipAddress, ?string $origin=null, ?string $userAgent=null, ?string $sessionId=null): void
@param (Form | string) $form
resolved @param (Nvl\Forms\Models\Form | string) $form
@param string $ipAddress
@param (string | null) $origin
@param (string | null) $userAgent
@param (string | null) $sessionId
public function record(Nvl\Forms\Models\Form|string $form, Nvl\Forms\Enums\FormAnalyticEventType|string $eventType, ?string $origin=null, ?string $ipAddress=null, ?string $userAgent=null, ?string $sessionId=null, ?array $metadata=null): Nvl\Forms\Models\FormAnalytic
@param (FormAnalyticEventType | string) $eventType
resolved @param (Nvl\Forms\Enums\FormAnalyticEventType | string) $eventType
@param (array<string, mixed> | null) $metadata
public function submission(Nvl\Forms\Models\Form|string $form, ?string $origin, ?string $ipAddress, ?string $userAgent, ?string $sessionId): Nvl\Forms\Models\Form
@param (Form | string) $form
resolved @param (Nvl\Forms\Models\Form | string) $form
@param (string | null) $origin
@param (string | null) $ipAddress
@param (string | null) $userAgent
@param (string | null) $sessionId
@return Form
resolved @return Nvl\Forms\Models\Form
@throws Throwable
public function view(Nvl\Forms\Models\Form|string $form, ?string $origin, ?string $ipAddress, ?string $userAgent, ?string $sessionId): Nvl\Forms\Models\Form
@param (Form | string) $form
resolved @param (Nvl\Forms\Models\Form | string) $form
@param (string | null) $origin
@param (string | null) $ipAddress
@param (string | null) $userAgent
@param (string | null) $sessionId
@return Form
resolved @return Nvl\Forms\Models\Form
@throws Throwable
```

## Nvl\Forms\Services\FormEntries

```text
final class Nvl\Forms\Services\FormEntries
public function __construct(Nvl\Forms\Contracts\FormEntryDeletionPolicy $deletionPolicy, Nvl\Support\Events\DomainEventDispatcher $domainEvents, Nvl\Forms\Contracts\FormEntryPrivacyPolicy $privacyPolicy, Nvl\Forms\Services\FormEntryExportService $exportService, Nvl\Support\Ownership\Contracts\OwnershipBoundary $ownership): mixed
public function anonymize(Nvl\Forms\Models\FormEntry|string $entry, ?Illuminate\Contracts\Auth\Authenticatable $actor=null): Nvl\Forms\Models\FormEntry
public function delete(Nvl\Forms\Models\FormEntry|string $formEntry, ?Illuminate\Contracts\Auth\Authenticatable $actor=null): bool
@param (FormEntry | string) $formEntry
resolved @param (Nvl\Forms\Models\FormEntry | string) $formEntry
@param (Authenticatable | null) $actor
resolved @param (Illuminate\Contracts\Auth\Authenticatable | null) $actor
@return bool
@throws (Exception | Throwable)
public function export(Nvl\Forms\Models\Form|string $form, array $options=[], ?Illuminate\Contracts\Auth\Authenticatable $actor=null): string
@param (Form | string) $form
resolved @param (Nvl\Forms\Models\Form | string) $form
@param array<string, mixed> $options
@param (Authenticatable | null) $actor
resolved @param (Illuminate\Contracts\Auth\Authenticatable | null) $actor
@return string
@throws Exception
public function exportFilename(Nvl\Forms\Models\Form $form): string
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@return string
public function flag(Nvl\Forms\Models\FormEntry|string $entry, string $key, mixed $value, ?Illuminate\Contracts\Auth\Authenticatable $actor=null): Nvl\Forms\Models\FormEntry
@param (FormEntry | string) $entry
resolved @param (Nvl\Forms\Models\FormEntry | string) $entry
@param string $key
@param mixed $value
@return FormEntry
resolved @return Nvl\Forms\Models\FormEntry
@throws Throwable
public function get(Nvl\Forms\Models\FormEntry|string $formEntry, ?Illuminate\Contracts\Auth\Authenticatable $actor=null): Nvl\Forms\Models\FormEntry
@param (FormEntry | string) $formEntry
resolved @param (Nvl\Forms\Models\FormEntry | string) $formEntry
@return FormEntry
resolved @return Nvl\Forms\Models\FormEntry
public function markLegitimate(Nvl\Forms\Models\FormEntry|string $entry, ?Illuminate\Contracts\Auth\Authenticatable $actor=null): Nvl\Forms\Models\FormEntry
@param (FormEntry | string) $entry
resolved @param (Nvl\Forms\Models\FormEntry | string) $entry
@return FormEntry
resolved @return Nvl\Forms\Models\FormEntry
@throws Throwable
public function markSpam(Nvl\Forms\Models\FormEntry|string $entry, ?string $reason=null, ?Illuminate\Contracts\Auth\Authenticatable $actor=null): Nvl\Forms\Models\FormEntry
@param (FormEntry | string) $entry
resolved @param (Nvl\Forms\Models\FormEntry | string) $entry
@param (string | null) $reason
@return FormEntry
resolved @return Nvl\Forms\Models\FormEntry
@throws Throwable
public function paginate(Nvl\Forms\Models\Form|string|null $form=null, bool $paginate=true, ?int $perPage=null, array $filters=[], ?Illuminate\Contracts\Auth\Authenticatable $actor=null): Illuminate\Contracts\Pagination\LengthAwarePaginator|Illuminate\Database\Eloquent\Collection
@param (Form | string | null) $form
resolved @param (Nvl\Forms\Models\Form | string | null) $form
@param bool $paginate
@param (int | null) $perPage
@param array<string, mixed> $filters
@return (LengthAwarePaginator<int, FormEntry> | Collection<int, FormEntry>)
resolved @return (Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Nvl\Forms\Models\FormEntry> | Illuminate\Database\Eloquent\Collection<int, Nvl\Forms\Models\FormEntry>)
@throws FormException
resolved @throws Nvl\Forms\Exceptions\FormException
public function redact(Nvl\Forms\Models\FormEntry|string $entry, array $fields, ?Illuminate\Contracts\Auth\Authenticatable $actor=null): Nvl\Forms\Models\FormEntry
@param list<string> $fields
public function update(Nvl\Forms\Models\FormEntry|string $entry, array $attributes, ?Illuminate\Contracts\Auth\Authenticatable $actor=null): Nvl\Forms\Models\FormEntry
@param array<string, mixed> $attributes
public function validateOwnership(Nvl\Forms\Models\Form $form, Nvl\Forms\Models\FormEntry $entry): void
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param FormEntry $entry
resolved @param Nvl\Forms\Models\FormEntry $entry
@throws FormOwnershipException
resolved @throws Nvl\Forms\Exceptions\FormOwnershipException
```

## Nvl\Forms\Services\FormEntryExportService

```text
final class Nvl\Forms\Services\FormEntryExportService
public function extractDynamicFields(Illuminate\Database\Eloquent\Collection $entries): array
@param Collection<int, FormEntry> $entries
resolved @param Illuminate\Database\Eloquent\Collection<int, Nvl\Forms\Models\FormEntry> $entries
@return array<int, string>
public function generateCsvContent(Nvl\Forms\Models\Form $form, Illuminate\Database\Eloquent\Collection $entries, array $options=[]): string
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param Collection<int, FormEntry> $entries
resolved @param Illuminate\Database\Eloquent\Collection<int, Nvl\Forms\Models\FormEntry> $entries
@param array<string, mixed> $options
@return string
@throws JsonException
public function generateFilename(Nvl\Forms\Models\Form $form, string|int|null $actorId): string
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param (string | int | null) $actorId
@return string
public function sanitizeForExport(mixed $value): string
@param mixed $value
@return string
@throws JsonException
```

## Nvl\Forms\Services\FormLifecycle

```text
final class Nvl\Forms\Services\FormLifecycle
public function __construct(Nvl\Forms\Services\FormHandleService $handleService, Nvl\Forms\Services\FormAllowedOriginService $originService, Nvl\Forms\Services\FormTranslationPayloadMapper $translationPayloadMapper, Nvl\Translatable\Services\RelatedTranslationStore $translationWriter, Nvl\Support\Events\DomainEventDispatcher $domainEvents): mixed
public function create(array $data, ?Illuminate\Contracts\Auth\Authenticatable $actor=null): Nvl\Forms\Models\Form
@param FormCreate $data
resolved @param array{handle?: ?string, translations: array<string, (array{name?: ?string, description?: ?string, submitButtonLabel?: ?string, successTitle?: ?string, successMessage?: ?string, ...} | null)>, translation_mode?: (Nvl\Translatable\Enums\TranslationSyncMode | string), resolvement: (Nvl\Forms\Enums\Resolvement | string), type: (Nvl\Forms\Enums\FormType | string), status?: (Nvl\Forms\Enums\FormStatus | string), restrictPublicAccess?: bool, allowMultipleRegistrations?: bool, dateRestricted?: bool, availableFrom?: ?string, availableUntil?: ?string, enableHoneypot?: bool, enableRateLimiting?: bool, rateLimitPerHour?: ?int, requireCsrf?: bool, corsSettings?: (array{policy?: (Nvl\Forms\Enums\CorsPolicy | string), allowCredentials?: bool, allowWildcards?: bool, maxAge?: int, allowedMethods?: list<string>, allowedHeaders?: list<string>} | null), options?: (array{showHeader?: bool, showLogo?: bool, submitButtonLabel?: ?string, successRedirectUrl?: ?string, theme?: ?string, maxWidth?: ?string, backgroundColor?: ?string, accentColor?: ?string, showPoweredBy?: bool, compactLayout?: bool, ...} | null), allowedOrigins?: (array<array-key, string> | null)} $data
@param (Authenticatable | null) $actor
resolved @param (Illuminate\Contracts\Auth\Authenticatable | null) $actor
@return Form
resolved @return Nvl\Forms\Models\Form
@throws Exception
@throws Throwable
public function delete(Nvl\Forms\Models\Form|string $form, ?Illuminate\Contracts\Auth\Authenticatable $actor=null): bool
@param (Form | string) $form
resolved @param (Nvl\Forms\Models\Form | string) $form
@param (Authenticatable | null) $actor
resolved @param (Illuminate\Contracts\Auth\Authenticatable | null) $actor
@return bool
@throws (Exception | Throwable)
public function duplicate(Nvl\Forms\Models\Form|string $form, ?string $newName=null, ?Illuminate\Contracts\Auth\Authenticatable $actor=null): Nvl\Forms\Models\Form
@param (Form | string) $form
resolved @param (Nvl\Forms\Models\Form | string) $form
@param (string | null) $newName
@param (Authenticatable | null) $actor
resolved @param (Illuminate\Contracts\Auth\Authenticatable | null) $actor
@return Form
resolved @return Nvl\Forms\Models\Form
@throws Exception
@throws Throwable
public function update(Nvl\Forms\Models\Form|string $form, array $data, ?Illuminate\Contracts\Auth\Authenticatable $actor=null): Nvl\Forms\Models\Form
@param (Form | string) $form
resolved @param (Nvl\Forms\Models\Form | string) $form
@param FormUpdate $data
resolved @param array{expected_revision: int, handle?: ?string, translations?: array<string, (array{name?: ?string, description?: ?string, submitButtonLabel?: ?string, successTitle?: ?string, successMessage?: ?string, ...} | null)>, translation_mode?: (Nvl\Translatable\Enums\TranslationSyncMode | string), resolvement?: (Nvl\Forms\Enums\Resolvement | string), type?: (Nvl\Forms\Enums\FormType | string), status?: (Nvl\Forms\Enums\FormStatus | string), restrictPublicAccess?: bool, allowMultipleRegistrations?: bool, dateRestricted?: bool, availableFrom?: ?string, availableUntil?: ?string, enableHoneypot?: bool, enableRateLimiting?: bool, rateLimitPerHour?: ?int, requireCsrf?: bool, corsSettings?: (array{policy?: (Nvl\Forms\Enums\CorsPolicy | string), allowCredentials?: bool, allowWildcards?: bool, maxAge?: int, allowedMethods?: list<string>, allowedHeaders?: list<string>} | null), options?: (array{showHeader?: bool, showLogo?: bool, submitButtonLabel?: ?string, successRedirectUrl?: ?string, theme?: ?string, maxWidth?: ?string, backgroundColor?: ?string, accentColor?: ?string, showPoweredBy?: bool, compactLayout?: bool, ...} | null), allowedOrigins?: (array<array-key, string> | null)} $data
@param (Authenticatable | null) $actor
resolved @param (Illuminate\Contracts\Auth\Authenticatable | null) $actor
@return Form
resolved @return Nvl\Forms\Models\Form
@throws ValidationException
resolved @throws Illuminate\Validation\ValidationException
@throws FormException
resolved @throws Nvl\Forms\Exceptions\FormException
@throws Exception
@throws Throwable
```

## Nvl\Forms\Services\FormModelRegistry

```text
final class Nvl\Forms\Services\FormModelRegistry
public function __construct(Illuminate\Contracts\Config\Repository $configuration, Nvl\Support\OwnerRegistry $identities): mixed
public function formClass(): string
@return class-string<Form>
resolved @return class-string<Nvl\Forms\Models\Form>
public static function projectionColumns(array $columns): array
@param list<string> $columns
@return list<string>
```

## Nvl\Forms\Services\FormOriginAccessService

```text
final class Nvl\Forms\Services\FormOriginAccessService
public function __construct(Nvl\Forms\Services\OriginMatchingService $originMatcher, Nvl\Support\Ownership\Contracts\OwnershipBoundary $boundary): mixed
@param OriginMatchingService $originMatcher
resolved @param Nvl\Forms\Services\OriginMatchingService $originMatcher
public function isOriginAllowed(Nvl\Forms\Models\Form|string $form, string $origin): bool
public function resolveMatchingOrigin(Nvl\Forms\Models\Form|string $form, string $origin): ?Nvl\Forms\Models\AllowedOrigin
```

## Nvl\Forms\Services\FormRateLimitService

```text
final class Nvl\Forms\Services\FormRateLimitService implements Nvl\Forms\Contracts\FormRateLimiter
public function __construct(Nvl\Forms\Services\FormAnalytics $recordFormAnalytic, Nvl\Forms\Services\FormRateLimitStatisticsService $statistics, Nvl\Support\Ownership\Contracts\OwnershipBoundary $boundary): mixed
@param FormAnalytics $recordFormAnalytic
resolved @param Nvl\Forms\Services\FormAnalytics $recordFormAnalytic
@param FormRateLimitStatisticsService $statistics
resolved @param Nvl\Forms\Services\FormRateLimitStatisticsService $statistics
public function blockIpAddress(Nvl\Forms\Models\Form $form, Nvl\Forms\Models\FormRateLimit $rateLimit, ?int $durationMinutes=null, ?string $origin=null, ?string $userAgent=null, ?string $sessionId=null): void
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param FormRateLimit $rateLimit
resolved @param Nvl\Forms\Models\FormRateLimit $rateLimit
@param (int | null) $durationMinutes
@param (string | null) $origin
@param (string | null) $userAgent
@param (string | null) $sessionId
public function cleanupExpiredRecords(): int
@return int
public function consumeSubmissionAttempt(Nvl\Forms\Models\Form $form, string $ipAddress, ?string $origin=null, ?string $userAgent=null, ?string $sessionId=null): Nvl\Forms\Results\FormRateLimitAttemptResult
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param string $ipAddress
@param (string | null) $origin
@param (string | null) $userAgent
@param (string | null) $sessionId
@return FormRateLimitAttemptResult
resolved @return Nvl\Forms\Results\FormRateLimitAttemptResult
public function getRateLimitStatus(Nvl\Forms\Models\Form $form, string $ipAddress): array
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param string $ipAddress
@return array{enabled: bool, remaining: (int | null), reset_at: (CarbonInterface | null), is_blocked: bool, blocked_until: (CarbonInterface | null), retry_after: int, violation_count: int}
resolved @return array{enabled: bool, remaining: (int | null), reset_at: (Carbon\CarbonInterface | null), is_blocked: bool, blocked_until: (Carbon\CarbonInterface | null), retry_after: int, violation_count: int}
public function isRateLimited(Nvl\Forms\Models\Form $form, string $ipAddress): bool
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param string $ipAddress
@return bool
public function recordSubmissionAttempt(Nvl\Forms\Models\Form $form, string $ipAddress, ?string $origin=null, ?string $userAgent=null, ?string $sessionId=null): void
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param string $ipAddress
@param (string | null) $origin
@param (string | null) $userAgent
@param (string | null) $sessionId
public function unblockIpAddress(Nvl\Forms\Models\Form $form, string $ipAddress): void
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param string $ipAddress
public function whitelistIpAddress(Nvl\Forms\Models\Form $form, string $ipAddress): void
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param string $ipAddress
```

## Nvl\Forms\Services\FormReader

```text
final class Nvl\Forms\Services\FormReader
public function __construct(Nvl\Support\Ownership\Contracts\OwnershipBoundary $boundary): mixed
public function analytics(Nvl\Forms\Models\Form|string $form, int $analyticsDays=30, ?Illuminate\Contracts\Auth\Authenticatable $actor=null): array
@param (Form | string) $form
resolved @param (Nvl\Forms\Models\Form | string) $form
@param int $analyticsDays
@return array{form: Form, analytics: array<string, mixed>, recent_entries: array<int, array<string, mixed>>}
resolved @return array{form: Nvl\Forms\Models\Form, analytics: array<string, mixed>, recent_entries: array<int, array<string, mixed>>}
public function details(Nvl\Forms\Models\Form|string $form, bool $recordView=true, ?string $origin=null, ?string $ipAddress=null, ?string $userAgent=null, ?string $sessionId=null, ?Illuminate\Contracts\Auth\Authenticatable $actor=null): Nvl\Forms\Models\Form
@param (Form | string) $form
resolved @param (Nvl\Forms\Models\Form | string) $form
@param bool $recordView
@param (string | null) $origin
@param (string | null) $ipAddress
@param (string | null) $userAgent
@param (string | null) $sessionId
@param (Authenticatable | null) $actor
resolved @param (Illuminate\Contracts\Auth\Authenticatable | null) $actor
@return Form
resolved @return Nvl\Forms\Models\Form
public function get(Nvl\Forms\Models\Form|string $formIdentifier): Nvl\Forms\Models\Form
@param (Form | string) $formIdentifier
resolved @param (Nvl\Forms\Models\Form | string) $formIdentifier
@return Form
resolved @return Nvl\Forms\Models\Form
@throws ModelNotFoundException
resolved @throws Illuminate\Database\Eloquent\ModelNotFoundException
public function options(array $filters): Illuminate\Support\Collection
@param array<string, mixed> $filters
@return Collection<int, array{id: string, label: string, sublabel: ?string}>
resolved @return Illuminate\Support\Collection<int, array{id: string, label: string, sublabel: ?string}>
public function paginate(bool $paginate=true, ?int $perPage=null, array $filters=[], ?Illuminate\Contracts\Auth\Authenticatable $actor=null): Illuminate\Contracts\Pagination\LengthAwarePaginator|Illuminate\Support\Collection
@param bool $paginate
@param (int | null) $perPage
@param array<string, mixed> $filters
@return (LengthAwarePaginator<int, Form> | Collection<int, Form>)
resolved @return (Illuminate\Contracts\Pagination\LengthAwarePaginator<int, Nvl\Forms\Models\Form> | Illuminate\Support\Collection<int, Nvl\Forms\Models\Form>)
@throws FormException
resolved @throws Nvl\Forms\Exceptions\FormException
public function schema(string $formIdentifier): array
@param string $formIdentifier
@return array<string, mixed>
public function search(array $filters, ?Illuminate\Contracts\Auth\Authenticatable $actor=null): array
@param array<string, mixed> $filters
@return array{forms: \Illuminate\Database\Eloquent\Collection<int, Form>, total: int}
resolved @return array{forms: Illuminate\Database\Eloquent\Collection<int, Nvl\Forms\Models\Form>, total: int}
public function suggestions(string $query, int $limit=10): Illuminate\Support\Collection
@param string $query
@param int $limit
@return Collection<int, Form>
resolved @return Illuminate\Support\Collection<int, Nvl\Forms\Models\Form>
public function summary(Nvl\Forms\Models\Form|string $form, int $days=30, ?Illuminate\Contracts\Auth\Authenticatable $actor=null): array
@param (Form | string) $form
resolved @param (Nvl\Forms\Models\Form | string) $form
@param int $days
@return array{total_views: int, total_submissions: int, spam_blocked: int, conversion_rate: float, top_origins: array<string, int>}
```

## Nvl\Forms\Services\FormRetention

```text
final readonly class Nvl\Forms\Services\FormRetention
public function __construct(Nvl\Forms\Services\FormEntries $entries, Nvl\Forms\Contracts\FormEntryPrivacyPolicy $privacy, Nvl\Forms\Contracts\FormEntryDeletionPolicy $deletion, Nvl\Support\Ownership\Contracts\OwnershipBoundary $ownership, Nvl\Forms\Services\FormEntryExportService $exports): mixed
public function prune(Nvl\Forms\Models\Form|string $form, Illuminate\Contracts\Auth\Authenticatable $actor, bool $dryRun=false): array
@return array{entries: int, analytics: int, rates: int, exports: int, has_more: bool}
```

## Nvl\Forms\Services\FormSubmissions

```text
final class Nvl\Forms\Services\FormSubmissions
public function __construct(Nvl\Forms\Services\CustomFormRegistry $customRegistry, Nvl\Forms\Services\EntryCallbackRegistry $entryCallbacks, Nvl\Forms\Services\CustomSubmissionReceiptService $customReceipts, Nvl\Forms\Services\PublicFormTokenService $tokenService, Nvl\Forms\Services\FormEntryLocator $entryLocator, Illuminate\Container\Container $container, Nvl\Forms\Contracts\FormSpamDetector $spamDetection, Nvl\Forms\Services\FormSpamRejectionRecorder $spamRejectionRecorder, Nvl\Forms\Services\FormRegistrationFingerprint $registrationFingerprint, Nvl\Support\Ownership\Contracts\OwnershipBoundary $boundary, Nvl\Support\Events\DomainEventDispatcher $domainEvents, Nvl\Forms\Services\FormOriginAccessService $originAccess, Nvl\Forms\Contracts\FormRateLimiter $rateLimitService): mixed
public static function fieldRules(): array
@return array<string, array<int, mixed>>
public function submit(Nvl\Forms\Models\Form|string $formIdentifier, array $fields, array $protection): Nvl\Forms\Models\FormEntry|Nvl\Forms\Models\FormSubmissionReceipt
@param (Form | string) $formIdentifier
resolved @param (Nvl\Forms\Models\Form | string) $formIdentifier
@param SubmissionFields $fields
resolved @param array{subject?: ?string, firstName?: ?string, lastName?: ?string, email?: ?string, phone?: ?string, address?: ?string, body?: ?string, submissionData?: (array<string, mixed> | null)} $fields
@param array<string, mixed> $protection
@throws Throwable
```

## Nvl\Forms\Services\PublicFormTokenService

```text
final class Nvl\Forms\Services\PublicFormTokenService
public const string HEADER="X-Forms-Public-Token"
public function __construct(Nvl\Support\Ownership\Contracts\OwnershipBoundary $boundary, Nvl\Support\Ownership\Contracts\OwnershipContext $ownership): mixed
public function hasSigningKey(): bool
public function issue(Nvl\Forms\Models\Form $form, Carbon\CarbonInterface $expiresAt, string $site="default"): string
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param CarbonInterface $expiresAt
resolved @param Carbon\CarbonInterface $expiresAt
@return string
public function issuedAt(?string $token, Nvl\Forms\Models\Form $form, string $site="default"): ?float
public function validate(?string $token, Nvl\Forms\Models\Form $form, string $site="default"): bool
@param (string | null) $token
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@return bool
public function validateForHandle(?string $token, string $handle): bool
@param (string | null) $token
@param string $handle
@return bool
```

## Nvl\Forms\Support\FormErrorMapperRegistry

```text
final class Nvl\Forms\Support\FormErrorMapperRegistry
public function __construct(Illuminate\Contracts\Container\Container $container): mixed
@param Container $container
resolved @param Illuminate\Contracts\Container\Container $container
public function has(string $handle): bool
@param string $handle
@return bool
public function map(Nvl\Forms\Models\Form $form, Nvl\Support\Exceptions\BusinessException $exception): ?array
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param BusinessException $exception
resolved @param Nvl\Support\Exceptions\BusinessException $exception
@return (array<string, mixed> | null)
@throws BindingResolutionException
resolved @throws Illuminate\Contracts\Container\BindingResolutionException
public function register(string $handle, string|Nvl\Forms\Contracts\FormErrorMapper $mapper): void
@param string $handle
@param (class-string<FormErrorMapper> | FormErrorMapper) $mapper
resolved @param (class-string<Nvl\Forms\Contracts\FormErrorMapper> | Nvl\Forms\Contracts\FormErrorMapper) $mapper
```

## Nvl\Forms\Support\FormHandlerRegistry

```text
final class Nvl\Forms\Support\FormHandlerRegistry
public function all(): array
@return array<string, (class-string | callable | CustomFormHandler)>
resolved @return array<string, (class-string | callable | Nvl\Forms\Contracts\CustomFormHandler)>
public function clear(): void
public function forget(string $handle): void
@param string $handle
public function get(string $handle): string|callable|Nvl\Forms\Contracts\CustomFormHandler|null
@param string $handle
@return (class-string | callable | CustomFormHandler | null)
resolved @return (class-string | callable | Nvl\Forms\Contracts\CustomFormHandler | null)
public function register(string $handle, string|callable|Nvl\Forms\Contracts\CustomFormHandler $handler): void
@param string $handle
@param (class-string | callable | CustomFormHandler) $handler
resolved @param (class-string | callable | Nvl\Forms\Contracts\CustomFormHandler) $handler
```

## Nvl\Forms\Support\FormRenderDataRegistry

```text
final class Nvl\Forms\Support\FormRenderDataRegistry
public function __construct(Illuminate\Contracts\Container\Container $container): mixed
@param Container $container
resolved @param Illuminate\Contracts\Container\Container $container
public function getData(Nvl\Forms\Models\Form $form, array $context): array
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@param array<string, mixed> $context
@return array<string, mixed>
@throws BindingResolutionException
resolved @throws Illuminate\Contracts\Container\BindingResolutionException
public function getTranslations(Nvl\Forms\Models\Form $form): array
@param Form $form
resolved @param Nvl\Forms\Models\Form $form
@return array<string, mixed>
@throws BindingResolutionException
resolved @throws Illuminate\Contracts\Container\BindingResolutionException
public function has(string $handle): bool
@param string $handle
@return bool
public function register(string $handle, string|Nvl\Forms\Contracts\FormRenderDataProvider $provider): void
@param string $handle
@param (class-string<FormRenderDataProvider> | FormRenderDataProvider) $provider
resolved @param (class-string<Nvl\Forms\Contracts\FormRenderDataProvider> | Nvl\Forms\Contracts\FormRenderDataProvider) $provider
```
