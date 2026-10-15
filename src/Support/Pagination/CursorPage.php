<?php

declare(strict_types=1);

namespace Support\Pagination;

use DateTimeImmutable;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Cursor;

/**
 * Keyset ("cursor") pages for list endpoints. A page never uses OFFSET, so a
 * deep page costs the same as the first, and rows added meanwhile never shift
 * a page.
 *
 * The cursor comes back from the client, so it is checked before it reaches
 * the query: one this list did not hand out (other columns, a value of the
 * wrong kind) starts again at the first page instead of failing inside the
 * query (a missing column throws, a wrong type is an SQL error).
 */
final class CursorPage
{
    /**
     * One page of `$query`, newest first by its primary key. Takes a model
     * query or a relation; on a many-to-many relation the related table's key
     * orders the page and the pivot columns still load.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>|Relation<TModel, *, *>  $query
     * @return CursorPaginator<int, TModel>
     */
    public static function newestFirst(Builder|Relation $query, int $perPage, ?string $cursor): CursorPaginator
    {
        $key = $query->getModel()->getQualifiedKeyName();

        // An empty string, not null, means "first page": with null the
        // paginator would read a `cursor` from the current request.
        return $query->orderByDesc($key)->cursorPaginate($perPage, cursor: self::decode($cursor, [$key => 'int']) ?? '');
    }

    /**
     * The decoded "next page" cursor, or null when it is missing or does not
     * carry exactly `$columns`, each with a value of its kind (`timestamp` is
     * a `Y-m-d H:i:s` string).
     *
     * @param  array<string, 'int'|'string'|'timestamp'>  $columns
     */
    public static function decode(?string $encoded, array $columns): ?Cursor
    {
        $cursor = Cursor::fromEncoded($encoded);

        if (! $cursor instanceof Cursor || $cursor->pointsToNextItems() !== true) {
            return null;
        }

        $parameters = $cursor->toArray();
        unset($parameters['_pointsToNextItems']);

        if (array_keys($parameters) !== array_keys($columns)) {
            return null;
        }

        foreach ($columns as $column => $kind) {
            if (! self::isOfKind($parameters[$column], $kind)) {
                return null;
            }
        }

        return $cursor;
    }

    private static function isOfKind(mixed $value, string $kind): bool
    {
        return match ($kind) {
            'int' => is_int($value),
            'string' => is_string($value),
            'timestamp' => is_string($value) && self::isTimestamp($value),
            default => false,
        };
    }

    /** True for a `Y-m-d H:i:s` string that names a real moment (no overflowing fields). */
    private static function isTimestamp(string $value): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value);

        return $parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d H:i:s') === $value;
    }
}
