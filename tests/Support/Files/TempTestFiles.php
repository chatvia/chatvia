<?php

declare(strict_types=1);

namespace Tests\Support\Files;

use Illuminate\Support\Facades\File;

/**
 * The temp files the running test made ({@see tempTestFile()},
 * {@see deleteAfterTest()}). Pest.php deletes them after every test, so a
 * failed assertion leaves none behind.
 */
final class TempTestFiles
{
    /** @var list<string> */
    private static array $paths = [];

    public static function track(string $path): string
    {
        self::$paths[] = $path;

        return $path;
    }

    public static function delete(): void
    {
        File::delete(self::$paths);

        self::$paths = [];
    }
}
