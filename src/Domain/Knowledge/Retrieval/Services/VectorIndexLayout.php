<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Services;

use Domain\Knowledge\Retrieval\Actions\RebuildVectorIndexAction;
use Domain\Knowledge\Retrieval\Data\VectorIndexStateData;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Reads the live layout of a vchordrq index from the catalog: its partitions
 * (`lists`, from the index options) and the row count it was built for (from
 * the index comment written by {@see RebuildVectorIndexAction}).
 *
 * Searches need the partitions on every query (vchordrq rejects probes that
 * do not match them), so those are cached briefly; a rebuild forgets them.
 */
final class VectorIndexLayout
{
    private const int CACHE_SECONDS = 600;

    /**
     * The index's current state, straight from the catalog; null when the index
     * does not exist (for example for a moment while a rebuild swaps it).
     */
    public function read(string $index): ?VectorIndexStateData
    {
        $row = DB::selectOne(
            "SELECT array_to_json(c.reloptions) AS options, obj_description(c.oid, 'pg_class') AS comment
             FROM pg_class c
             WHERE c.oid = to_regclass(?) AND c.relkind = 'i'",
            [$index],
        );

        if ($row === null) {
            return null;
        }

        return new VectorIndexStateData(
            lists: $this->parseLists((string) ($row->options ?? '')),
            builtForRows: preg_match('/\brows=(\d+)/', (string) ($row->comment ?? ''), $match) === 1 ? (int) $match[1] : null,
        );
    }

    /**
     * The index's partitions, cached.
     *
     * @return list<int>|null null when the index does not exist
     */
    public function lists(string $index): ?array
    {
        $key = $this->cacheKey($index);
        $cached = Cache::get($key);

        if (is_array($cached)) {
            return array_values(array_map(intval(...), $cached));
        }

        $state = $this->read($index);

        if (! $state instanceof VectorIndexStateData) {
            return null;
        }

        Cache::put($key, $state->lists, self::CACHE_SECONDS);

        return $state->lists;
    }

    public function forget(string $index): void
    {
        Cache::forget($this->cacheKey($index));
    }

    /**
     * `lists` from the index's `options` TOML (`lists = [4096]` under
     * `[build.internal]`, or the dotted `build.internal.lists = …`). No
     * options, or no lists, is VectorChord's default: a flat index.
     *
     * @return list<int>
     */
    private function parseLists(string $optionsJson): array
    {
        $options = json_decode($optionsJson !== '' ? $optionsJson : '[]', true);

        foreach (is_array($options) ? $options : [] as $option) {
            if (! is_string($option) || ! str_starts_with($option, 'options=')) {
                continue;
            }

            if (preg_match('/^\s*(?:build\.internal\.)?lists\s*=\s*\[([^\]]*)\]/m', substr($option, 8), $match) !== 1) {
                return [];
            }

            return array_values(array_map(intval(...), array_filter(
                array_map(trim(...), explode(',', $match[1])),
                static fn (string $value): bool => $value !== '',
            )));
        }

        return [];
    }

    private function cacheKey(string $index): string
    {
        return 'vector-index:lists:'.$index;
    }
}
