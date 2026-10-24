<?php

declare(strict_types=1);

namespace Tests\Support\Integration\Tool;

use Illuminate\Database\Eloquent\Model;

/**
 * Which search vectors a tool row (an MCP tool, a catalog tool, a function, a
 * form) holds now: the dense `embedding` the routing compares by meaning, and
 * the keyword `search_text` it matches by words. Read from the database, since
 * the columns are not loaded on the model.
 */
final class ToolVectors
{
    /** @return array{dense: bool, keyword: bool} */
    public static function of(Model $row): array
    {
        $query = fn () => $row->newQuery()->whereKey($row->getKey());

        return [
            'dense' => $query()->whereNotNull('embedding')->exists(),
            'keyword' => $query()->whereNotNull('search_text')->exists(),
        ];
    }

    /** Whether the row holds both vectors. */
    public static function embedded(Model $row): bool
    {
        return self::of($row) === ['dense' => true, 'keyword' => true];
    }
}
