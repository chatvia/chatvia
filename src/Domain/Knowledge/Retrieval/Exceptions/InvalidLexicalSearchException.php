<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Exceptions;

use RuntimeException;

/**
 * A lexical-search setting that cannot be used: an unknown driver name, or a
 * text-search configuration that is not a plain identifier (it is interpolated
 * into index DDL and queries, so anything else is refused).
 */
final class InvalidLexicalSearchException extends RuntimeException
{
    public static function driver(mixed $value): self
    {
        $given = is_scalar($value) ? (string) $value : get_debug_type($value);

        return new self("SEARCH_LEXICAL_DRIVER must be \"auto\", \"pg_textsearch\" or \"postgres\", \"{$given}\" given.");
    }

    public static function textConfig(mixed $value): self
    {
        $given = is_scalar($value) ? (string) $value : get_debug_type($value);

        return new self("SEARCH_TEXT_CONFIG must name a Postgres text search configuration (e.g. \"simple\"), \"{$given}\" given.");
    }
}
