<?php

namespace Support\Traits;

use Illuminate\Support\Str;

/**
 * @property string|null $external_id
 */
trait SupportsExternalId
{
    /**
     * Named `bootSupportsExternalId` rather than `booted` so models using
     * this trait can still override `booted()` themselves (e.g. for
     * cascade deletes) without shadowing the auto-generated external_id
     * hook. Laravel auto-invokes any `boot<TraitName>` method during model
     * boot.
     */
    protected static function bootSupportsExternalId(): void
    {
        static::creating(function ($model): void {
            if (! $model->external_id) {
                $model->external_id = Str::uuid7()->toString();
            }
        });
    }
}
