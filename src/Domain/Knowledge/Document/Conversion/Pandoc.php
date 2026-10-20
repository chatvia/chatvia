<?php

namespace Domain\Knowledge\Document\Conversion;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

/**
 * Thin wrapper over the `pandoc` binary, used to extract text from structured
 * documents: EPUB books when pandoc is installed ({@see EbookText}; the app
 * reads them itself otherwise) and the word-processor formats
 * {@see OfficeText} reads with it (DOCX, ODT, RTF). FictionBook stays
 * readable here for the reader comparison eval; uploads read it in PHP.
 *
 * These are already text, so there's nothing to OCR: `toMarkdown()` feeds the
 * direct-chunk ingestion pipeline (titles preserved for the text splitter),
 * and `toGithubMarkdown()` gives office documents Markdown with pipe tables.
 * We never produce a PDF — there's no LaTeX/PDF engine installed. The reader
 * is passed explicitly via `--from` because the uploaded temp file carries no
 * extension for Pandoc to infer the input format from.
 */
class Pandoc
{
    private readonly string $binPath;

    private int $timeout = 600;

    /** Extension → Pandoc reader name. */
    private const array READERS = [
        'epub' => 'epub',
        'fb2' => 'fb2',
        'docx' => 'docx',
        'odt' => 'odt',
        'rtf' => 'rtf',
    ];

    public function __construct()
    {
        $this->binPath = config('services.bin.pandoc');
    }

    public function toMarkdown(string $path, string $extension): string
    {
        return $this->convert($path, $extension, 'markdown');
    }

    /** GitHub-flavoured Markdown: tables as pipe tables, no Pandoc-only syntax. */
    public function toGithubMarkdown(string $path, string $extension): string
    {
        return $this->convert($path, $extension, 'gfm');
    }

    public function supports(string $extension): bool
    {
        return $this->readerFor($extension) !== null;
    }

    private function convert(string $path, string $extension, string $to): string
    {
        if (! File::exists($path)) {
            throw new InvalidArgumentException("Input file does not exist: {$path}");
        }

        $reader = $this->readerFor($extension);

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

    private function readerFor(string $extension): ?string
    {
        return self::READERS[strtolower($extension)] ?? null;
    }
}
