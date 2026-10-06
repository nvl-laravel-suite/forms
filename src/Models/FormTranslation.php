<?php

declare(strict_types=1);

namespace Nvl\Forms\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Nvl\Forms\Database\Factories\FormTranslationFactory;
use Nvl\Forms\Definitions\Tables\FormsTables;
use Nvl\Support\Config\PackageStorage;

/**
 * Stores locale-specific public copy and arbitrary form content.
 *
 * @property string $id
 * @property string|null $tenant_id
 * @property string $form_id
 * @property string $locale
 * @property string|null $name
 * @property string|null $description
 * @property string|null $submit_button_label
 * @property string|null $success_title
 * @property string|null $success_message
 * @property array<string, mixed>|null $content
 *
 * @api
 *
 * @nvl-consumer-read id
 */
final class FormTranslation extends Model
{
    /** @use HasFactory<FormTranslationFactory> */
    use HasFactory;

    use HasUuids;

    public const string TABLE = FormsTables::I18n;

    protected $table = self::TABLE;

    /** @var list<string> */
    protected $fillable = [
        'form_id',
        'tenant_id',
        'locale',
        'name',
        'description',
        'submit_button_label',
        'success_title',
        'success_message',
        'content',
    ];

    /**
     * Return translation attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
        ];
    }

    /**
     * Return the owning form.
     *
     * @return BelongsTo<Form, $this>
     */
    public function form(): BelongsTo
    {
        return $this->belongsTo(Form::class);
    }

    /** Resolve the configured package storage table. */
    public function getTable(): string
    {
        return FormsTables::get(FormsTables::I18n);
    }

    /** Resolve the package connection through shared infrastructure defaults. */
    public function getConnectionName(): ?string
    {
        return PackageStorage::connectionName($this->connection ?? PackageStorage::connection('forms') ?? parent::getConnectionName());
    }

    /**
     * Return the package's runtime fixture factory.
     *
     * @internal
     */
    protected static function newFactory(): FormTranslationFactory
    {
        return FormTranslationFactory::new();
    }
}
