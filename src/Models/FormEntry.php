<?php

declare(strict_types=1);

namespace Nvl\Forms\Models;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;
use LogicException;
use Nvl\Forms\Builders\FormEntryBuilder;
use Nvl\Forms\Database\Factories\FormEntryFactory;
use Nvl\Forms\Definitions\Tables\FormsTables;
use Nvl\Forms\Exceptions\FormRetentionPredicateChanged;
use Nvl\Forms\Models\Concerns\HasFormOwnership;
use Nvl\Forms\Services\FormModelRegistry;
use Nvl\Support\Config\PackageStorage;
use Nvl\Support\Ownership\Contracts\Ownable;

/**
 * FormEntry model representing a submission to a form.
 *
 * @property string $id
 * @property string $form_id
 * @property string|null $subject
 * @property string|null $email
 * @property string|null $first_name
 * @property string|null $last_name
 * @property string|null $phone
 * @property string|null $address
 * @property string|null $body
 * @property array<string, mixed>|null $submission_data
 * @property string $submitted_from
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string|null $session_id
 * @property bool $is_spam
 * @property int|null $spam_score
 * @property array<string, mixed>|null $security_flags
 * @property string|null $idempotency_key
 * @property string|null $payload_digest
 * @property string|null $registration_fingerprint
 * @property Carbon|null $redacted_at
 * @property Carbon|null $anonymized_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Form $form
 *
 * @api
 */
class FormEntry extends Model implements Ownable
{
    use HasFormOwnership;

    public const string OWNERSHIP_RESOURCE = 'forms.entries';

    /** @use HasFactory<FormEntryFactory> */
    use HasFactory;

    use HasUuids;

    private bool $idempotentReplay = false;

    private ?CarbonImmutable $retentionCutoff = null;

    /** @var (Closure(): void)|null */
    private ?Closure $retentionAuthorization = null;

    /**
     * @internal Preserve Eloquent hooks while fencing the actual DELETE by source, authority and age.
     *
     * @param  Closure(): void  $authorize
     *
     * @throws FormRetentionPredicateChanged When the final DELETE no longer satisfies the cutoff.
     */
    public function deleteBefore(CarbonImmutable $cutoff, Closure $authorize): ?bool
    {
        $this->retentionCutoff = $cutoff;
        $this->retentionAuthorization = $authorize;
        try {
            return $this->delete();
        } finally {
            $this->retentionCutoff = null;
            $this->retentionAuthorization = null;
        }
    }

    /** Preserve ordinary deletes; a renewed entry must not emit a deleted event. */
    protected function performDeleteOnModel(): void
    {
        if ($this->retentionCutoff === null) {
            parent::performDeleteOnModel();

            return;
        }
        if ($this->retentionAuthorization === null) {
            throw new LogicException('Conditional Form retention requires current authorization.');
        }
        ($this->retentionAuthorization)();
        $deleted = $this->newModelQuery()->where($this->getKeyName(), $this->getKeyForSaveQuery())
            ->where($this->qualifyColumn('created_at'), '<', $this->retentionCutoff)->delete();
        if ($deleted !== 1) {
            throw new FormRetentionPredicateChanged('The Form entry no longer satisfies the fixed retention cutoff.');
        }
        $this->exists = false;
    }

    /** Return this persisted entry's required UUID identifier. */
    public function identifier(): string
    {
        $identifier = $this->getAttribute($this->getKeyName());

        if (! is_string($identifier) || $identifier === '') {
            throw new LogicException('Persisted form entries require a UUID identifier.');
        }

        return $identifier;
    }

    protected $table = FormsTables::Entries;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'form_id',
        'subject',
        'email',
        'first_name',
        'last_name',
        'phone',
        'address',
        'body',
        'submission_data',
        'submitted_from',
        'ip_address',
        'user_agent',
        'session_id',
        'is_spam',
        'spam_score',
        'security_flags',
        'idempotency_key',
        'payload_digest',
        'registration_fingerprint',
        'redacted_at',
        'anonymized_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submission_data' => 'array',
            'is_spam' => 'boolean',
            'spam_score' => 'integer',
            'security_flags' => 'array',
            'redacted_at' => 'datetime',
            'anonymized_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Create a new Eloquent builder instance for the model.
     *
     * @param  Builder  $query  Base query builder
     * @return FormEntryBuilder<FormEntry>
     */
    public function newEloquentBuilder($query): FormEntryBuilder
    {
        return new FormEntryBuilder($query);
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): FormEntryFactory
    {
        return FormEntryFactory::new();
    }

    /**
     * Get the parent form relationship.
     *
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(FormModelRegistry::modelClass(), 'form_id');
    }

    /**
     * Get the full name of the submitter.
     *
     * @return string|null Submitter full name when available
     */
    public function getFullNameAttribute(): ?string
    {
        $parts = array_filter([$this->first_name, $this->last_name], static fn ($v): bool => is_string($v) && $v !== '');

        return count($parts) === 0 ? null : implode(' ', $parts);
    }

    /**
     * Check if the entry has contact information.
     *
     * @return bool Whether at least email or phone is present
     */
    public function hasContactInfo(): bool
    {
        return ($this->email !== null && $this->email !== '') || ($this->phone !== null && $this->phone !== '');
    }

    /**
     * Mark this in-memory result as a replay of a previously persisted entry.
     */
    public function markAsIdempotentReplay(): self
    {
        $this->idempotentReplay = true;

        return $this;
    }

    /**
     * Determine whether this instance was returned by an idempotent replay.
     */
    public function isIdempotentReplay(): bool
    {
        return $this->idempotentReplay;
    }

    /**
     * Get a specific security flag value.
     *
     * @param  string  $key  Security flag key
     * @param  mixed|null  $default  Default value when the flag is absent
     * @return mixed Stored flag value or the provided default
     */
    public function getSecurityFlag(string $key, mixed $default = null): mixed
    {
        return ($this->security_flags ?? [])[$key] ?? $default;
    }

    /**
     * Set a security flag value in memory (does not persist).
     *
     * Callers are responsible for persisting the model after setting flags.
     *
     * @param  string  $key  Security flag key
     * @param  mixed  $value  Value to store
     */
    public function setSecurityFlag(string $key, mixed $value): void
    {
        $flags = $this->security_flags ?? [];
        $flags[$key] = $value;
        $this->security_flags = $flags;
    }

    /** Resolve the configured package storage table. */
    public function getTable(): string
    {
        return FormsTables::get(FormsTables::Entries);
    }

    /** Resolve the package connection through shared infrastructure defaults. */
    public function getConnectionName(): ?string
    {
        return PackageStorage::connectionName($this->connection ?? PackageStorage::connection('forms') ?? parent::getConnectionName());
    }
}
