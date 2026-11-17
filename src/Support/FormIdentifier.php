<?php

declare(strict_types=1);

namespace Nvl\Forms\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Str;

/**
 * Resolves native UUID keys before they reach a typed database column.
 *
 * @internal
 */
final class FormIdentifier
{
    /**
     * Return the record's UUID key; a malformed identifier or a persisted model whose key was changed
     * in memory names no record, on every driver.
     *
     * @param  class-string<Model>  $model
     */
    public static function of(Model|string $record, string $model): string
    {
        $key = $record instanceof Model ? $record->getKey() : $record;
        $keyChanged = $record instanceof Model && $record->exists && $record->isDirty($record->getKeyName());

        if ($keyChanged || ! is_string($key) || ! Str::isUuid($key)) {
            throw (new ModelNotFoundException)->setModel($model);
        }

        return $key;
    }

    private function __construct() {}
}
