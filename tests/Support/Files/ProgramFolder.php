<?php

declare(strict_types=1);

namespace Tests\Support\Files;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * A temp folder of stand-in programs, for the tests of how the app finds the
 * programs it runs (pdfinfo, pandoc, weasyprint…): a script that can run
 * for each program, and a plain file that cannot for each "not runnable"
 * name. The folder is deleted after the test.
 */
final class ProgramFolder
{
    /**
     * @param  list<string>  $programs  Names that get a script that can run.
     * @param  list<string>  $notRunnable  Names that get a file that cannot run.
     * @return string The folder's path.
     */
    public static function make(array $programs, array $notRunnable = []): string
    {
        $folder = TempTestFiles::track(sys_get_temp_dir().'/chatvia-programs-'.Str::lower(Str::random(10)));
        File::ensureDirectoryExists($folder);

        foreach ($programs as $name) {
            File::put("{$folder}/{$name}", "#!/bin/sh\nexit 0\n");
            File::chmod("{$folder}/{$name}", 0755);
        }

        foreach ($notRunnable as $name) {
            File::put("{$folder}/{$name}", 'not a program');
            File::chmod("{$folder}/{$name}", 0644);
        }

        return $folder;
    }
}
