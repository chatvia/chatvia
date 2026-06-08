<?php

namespace Domain\Knowledge\Document;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

/**
 * Thin wrapper over the `pandoc` binary, used to extract plain text from the
 * document formats Mistral OCR accepts but LibreOffice can't open (EPUB,
 * LaTeX, BibTeX, FictionBook, Jupyter, DocBook/JATS XML, OPML, Troff).
 *
 * We never produce a PDF here — there's no LaTeX/PDF engine installed — only
 * plain text, which FileAnalyzer turns into a page-count estimate for quota.
 * The reader is passed explicitly via `--from` because the uploaded temp file
 * carries no extension for Pandoc to infer the input format from.
 */
class Pandoc
{
    private string $binPath;

    private int $timeout = 600;

    /** Extension → Pandoc reader name. */
    private const READERS = [
        'epub' => 'epub',
        'fb2' => 'fb2',
        'ipynb' => 'ipynb',
        'xml' => 'docbook', // ambiguous (DocBook vs JATS); DocBook is the common case
        'tex' => 'latex',
        'bib' => 'biblatex',
        'opml' => 'opml',
        '1' => 'man',
        'man' => 'man',
    ];

    public function __construct()
    {
        $this->binPath = config('services.bin.pandoc');
    }

    public static function readerFor(string $extension): ?string
    {
        return self::READERS[strtolower($extension)] ?? null;
    }

    public static function supports(string $extension): bool
    {
        return self::readerFor($extension) !== null;
    }

    public function toText(string $path, string $extension): string
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
            '--to', 'plain',
            '--wrap', 'none',
            $path,
        ]);

        if (! $result->successful()) {
            throw new RuntimeException("Pandoc conversion failed: {$result->errorOutput()}");
        }

        return $result->output();
    }
}
