<?php

declare(strict_types=1);

namespace Support;

/**
 * Finds the program file a configured path leads to, with file checks only
 * (no `which`, no process), so it is cheap enough to ask before every use.
 * The app uses it to fall back to its own code, or to refuse a file early,
 * when a program such as pdfinfo or ffmpeg is not installed.
 *
 * A bare name is looked up on the PATH of this PHP process. The queue worker
 * may have another PATH than the web server, which is why the admin panel
 * prefers a full path.
 */
final class Executable
{
    /**
     * The file the app runs for a path: the path itself when it is a full
     * path, otherwise the first match on the PATH. Null when there is none.
     */
    public static function find(string $path): ?string
    {
        if ($path === '') {
            return null;
        }

        if (str_starts_with($path, '/')) {
            return self::isProgram($path) ? $path : null;
        }

        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $folder) {
            if ($folder !== '' && self::isProgram("{$folder}/{$path}")) {
                return "{$folder}/{$path}";
            }
        }

        return null;
    }

    /** Whether a program is found for the path ({@see find()}). */
    public static function exists(string $path): bool
    {
        return self::find($path) !== null;
    }

    private static function isProgram(string $path): bool
    {
        return @is_file($path) && @is_executable($path);
    }
}
