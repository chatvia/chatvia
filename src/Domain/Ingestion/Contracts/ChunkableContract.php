<?php

namespace Domain\Ingestion\Contracts;

/**
 * Marker contract for any model that can be chunked and ingested. In
 * practice every implementor is an Eloquent model, so callers should
 * type-narrow with `instanceof Model` (or rely on the documented
 * `$id` / `$filepath` properties below) when accessing schema data.
 *
 * @property int $id
 * @property string $filepath
 *
 * @method int|string|null getKey()
 */
interface ChunkableContract {}
