<?php

declare(strict_types=1);

namespace Nvl\Forms\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nvl\Forms\Database\Factories\FormSubmissionReceiptFactory;
use Nvl\Forms\Definitions\Tables\FormsTables;
use Nvl\Forms\Enums\FormSubmissionReceiptState;
use Nvl\Support\Config\PackageStorage;

/**
 * Durable idempotency and registration claim for custom form handlers.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $form_id
 * @property string|null $idempotency_key
 * @property string $payload_digest
 * @property string|null $registration_fingerprint
 * @property FormSubmissionReceiptState $state
 * @property string|null $result_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Form $form
 *
 * @api
 *
 * @nvl-consumer-read id
 */
final class FormSubmissionReceipt extends Model
{
    /** @use HasFactory<FormSubmissionReceiptFactory> */
    use HasFactory;

    use HasUuids;

    protected $table = FormsTables::SubmissionReceipts;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'form_id',
        'tenant_id',
        'idempotency_key',
        'payload_digest',
        'registration_fingerprint',
        'state',
        'result_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'state' => FormSubmissionReceiptState::class,
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): FormSubmissionReceiptFactory
    {
        return FormSubmissionReceiptFactory::new();
    }

    /**
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /** Resolve the configured package storage table. */
    public function getTable(): string
    {
        return FormsTables::get(FormsTables::SubmissionReceipts);
    }

    /** Resolve the package connection through shared infrastructure defaults. */
    public function getConnectionName(): ?string
    {
        return PackageStorage::connectionName($this->connection ?? PackageStorage::connection('forms') ?? parent::getConnectionName());
    }
}
