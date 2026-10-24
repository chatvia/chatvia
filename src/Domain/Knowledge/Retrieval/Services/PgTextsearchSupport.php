<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Services;

use Domain\Knowledge\Retrieval\Enums\LexicalDriver;
use Domain\Knowledge\Retrieval\Enums\PgTextsearchState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reads from the database how ready pg_textsearch is ({@see PgTextsearchState}).
 * shared_preload_libraries can be read only by a superuser (or a member of
 * pg_read_all_settings); when it cannot be read, a package on the server
 * counts as ready, and creating the extension tells the rest.
 */
final readonly class PgTextsearchSupport
{
    public const string EXTENSION = 'pg_textsearch';

    public function __construct(private LexicalSearch $lexical) {}

    public function state(): PgTextsearchState
    {
        $row = DB::selectOne('SELECT installed_version FROM pg_available_extensions WHERE name = ?', [self::EXTENSION]);

        if ($row === null) {
            return PgTextsearchState::NotInstalled;
        }

        if ($row->installed_version !== null) {
            return $this->lexical->driver() === LexicalDriver::PgTextsearch
                ? PgTextsearchState::Active
                : PgTextsearchState::SwitchedOff;
        }

        $preloaded = rescue(fn (): string => (string) DB::scalar("SELECT current_setting('shared_preload_libraries')"), report: false);

        return $preloaded === null || $this->preloads($preloaded)
            ? PgTextsearchState::ReadyToEnable
            : PgTextsearchState::NotPreloaded;
    }

    /** Whether the setting's list names the library, quoted or not. */
    private function preloads(string $libraries): bool
    {
        return Str::of($libraries)
            ->explode(',')
            ->map(fn (string $library): string => Str::trim($library, " \t\""))
            ->contains(self::EXTENSION);
    }
}
