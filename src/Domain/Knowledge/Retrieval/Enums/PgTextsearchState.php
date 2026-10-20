<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Enums;

/**
 * How far the database is from BM25 keyword search with pg_textsearch, so
 * the setup and Status pages can say the next step: install the package,
 * load it when PostgreSQL starts, create the extension, or nothing.
 */
enum PgTextsearchState: string
{
    /** Created, and keyword search ranks with it. */
    case Active = 'active';

    /** Created, but SEARCH_LEXICAL_DRIVER keeps the built-in search. */
    case SwitchedOff = 'switched_off';

    /** On the server and loaded (or the load cannot be read): only the extension is missing. */
    case ReadyToEnable = 'ready_to_enable';

    /** On the server, but not in shared_preload_libraries. */
    case NotPreloaded = 'not_preloaded';

    /** Not on the database server. */
    case NotInstalled = 'not_installed';
}
