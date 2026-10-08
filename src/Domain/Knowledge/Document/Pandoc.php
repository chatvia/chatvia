<?php

namespace Domain\Knowledge\Document;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

/**
 * Thin wrapper over the `pandoc` binary, used to extract text from the
 * structured-text ebook formats the document parsers can't read (EPUB, FictionBook).
 *
 * These are already text, so there's nothing to OCR: `toMarkdown()` feeds the
 * direct-chunk ingestion pipeline (titles preserved for the text splitter) and
 * `toText()` feeds FileAnalyzer's word-count page estimate. We never produce a
 * PDF — there's no LaTeX/PDF engine installed. The reader is passed explicitly
 * via `--from` because the uploaded temp file carries no extension for Pandoc
 * to infer the input format from.
 */
class Pandoc
{
    private string $binPath;

    private int $timeout = 600;

    /** Extension → Pandoc reader name. */
    private const READERS = [
        'epub' => 'epub',
        'fb2' => 'fb2',
    ];

    public function __construct()
    {
        $this->binPath = config('services.bin.pandoc');
    }

    public function toText(string $path, string $extension): string
    {
        return $this->convert($path, $extension, 'plain');
    }

    public function toMarkdown(string $path, string $extension): string
    {
        return $this->convert($path, $extension, 'markdown');
    }

    private function convert(string $path, string $extension, string $to): string
    {
        if (! File::exists($path)) {
            throw new InvalidArgumentException("Input file does not exist: {$path}");
        }

        $reader = self::readerFor($extension);

        if ($reader === null) {
            throw new InvalidArgumentException("Unsupported Pandoc format: {$extension}");
        }

        $result = Process::timeout($this->timeout)->run([
            $this->binPath,
            '--from', $reader,
            '--to', $to,
            '--wrap', 'none',
            $path,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException("Pandoc conversion failed: {$result->errorOutput()}");
        }

        return $result->output();
    }

    private static function readerFor(string $extension): ?string
    {
        return self::READERS[strtolower($extension)] ?? null;
    }
}
