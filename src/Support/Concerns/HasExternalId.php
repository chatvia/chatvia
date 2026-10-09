<?php

namespace Support\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Auto-fills a UUID `external_id` when a model is created. The integer primary
 * key stays internal; `external_id` is the stable, non-guessable identifier
 * exposed through the API. Apply to any model that has an `external_id` column.
 */
trait HasExternalId
{
    protected static function bootHasExternalId(): void
    {
        static::creating(function (Model $model): void {
            if (! $model->getAttribute('external_id')) {
                $model->setAttribute('external_id', (string) Str::uuid7());
            }
        });
    }
}
