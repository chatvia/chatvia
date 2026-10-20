<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

/**
 * Reads the text of the old binary Office formats (Word 97–2003 `.doc`,
 * PowerPoint 97–2003 `.ppt`) with `catdoc` and `catppt` from the small catdoc
 * package. Only a backup: the app reads these formats itself and runs the
 * programs, when installed, for a file its readers cannot read; see
 * {@see OfficeText}.
 */
class CatDoc
{
    private const int TIMEOUT_SECONDS = 300;

    /** Extension → the `services.bin` key of the program that reads it. */
    private const array PROGRAMS = [
        'doc' => 'catdoc',
        'ppt' => 'catppt',
    ];

    public function supports(string $extension): bool
    {
        return array_key_exists(strtolower($extension), self::PROGRAMS);
    }

    public function toText(string $path, string $extension): string
    {
        $program = self::PROGRAMS[strtolower($extension)] ?? null;

        if ($program === null) {
            throw new InvalidArgumentException("Unsupported catdoc format: {$extension}");
        }

        if (! File::exists($path)) {
            throw new InvalidArgumentException("Input file does not exist: {$path}");
        }

        $command = [(string) config("services.bin.{$program}"), '-d', 'utf-8'];

        // catdoc wraps lines at 72 columns unless told not to.
        if ($program === 'catdoc') {
            $command[] = '-w';
        }

        $command[] = $path;

        $result = Process::timeout(self::TIMEOUT_SECONDS)->run($command);

        if (! $result->successful()) {
            throw new RuntimeException("{$program} failed: {$result->errorOutput()}");
        }

        return $result->output();
    }
}
