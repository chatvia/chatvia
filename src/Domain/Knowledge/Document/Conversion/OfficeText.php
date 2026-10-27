<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion;

use Domain\Knowledge\Document\Conversion\Markdown\MarkdownReaders;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use RuntimeException;
use SplFileObject;
use SplTempFileObject;
use Support\Executable;
use Throwable;
use ZipArchive;

/**
 * Reads the text of office and plain-text files as pages, with no office
 * suite and no other program needed on the server:
 *
 *  - DOCX, ODT       → pandoc when it is installed, otherwise the built-in
 *                      readers, as Markdown (headings, lists and tables kept)
 *  - RTF, DOC        → the built-in readers, as Markdown; pandoc and catdoc
 *                      are only a backup for a file they cannot read
 *  - PPT, PPTX       → one page per slide, read straight from the file
 *                      (catppt is a backup for a .ppt the reader cannot read)
 *  - XLSX, CSV       → one labelled line per row ("Plan: Pro · Price: 199"),
 *                      in pages cut between rows, so every chunk keeps the
 *                      column names (PhpSpreadsheet for XLSX)
 *  - TXT, MD         → the file itself
 *
 * For RTF, DOC and PPT the built-in readers go first: compared on sample
 * files (tests/Eval/DocumentReaderEvalTest.php) they kept every word the
 * programs found, while pandoc drops the footnotes and tables of RTF files,
 * and catdoc and catppt can show text that a later save replaced.
 *
 * Slides are real pages; a sheet is one page, or more when it is long. The
 * other formats have no pages of their own, so their text is cut into pages
 * of about {@see CHARS_PER_PAGE} characters at paragraph breaks; a page count
 * is then an estimate.
 */
class OfficeText
{
    /** About one printed page; characters, so it works for every script. */
    public const int CHARS_PER_PAGE = 3000;

    /** Largest slide part read from a presentation, a guard against zip bombs. */
    private const int MAX_PART_BYTES = 20 * 1024 * 1024;

    private const string DRAWING_NS = 'http://schemas.openxmlformats.org/drawingml/2006/main';

    /** Extension → how its text is read. */
    private const array READERS = [
        'docx' => 'document',
        'odt' => 'document',
        'rtf' => 'document',
        'doc' => 'document',
        'ppt' => 'old-slides',
        'pptx' => 'slides',
        'xlsx' => 'sheets',
        'txt' => 'plain',
        'md' => 'plain',
        'markdown' => 'plain',
        'csv' => 'csv',
    ];

    /** The program that can also read each format (its `services.bin` key). */
    private const array PROGRAMS = ['docx' => 'pandoc', 'odt' => 'pandoc', 'rtf' => 'pandoc', 'doc' => 'catdoc', 'ppt' => 'catppt'];

    /** The formats read by their program first when it is installed; the others by the built-in reader first. */
    private const array PROGRAM_FIRST = ['docx', 'odt'];

    public function __construct(
        private readonly Pandoc $pandoc,
        private readonly CatDoc $catDoc,
        private readonly MarkdownReaders $readers,
    ) {}

    public function supports(string $extension): bool
    {
        return array_key_exists(Str::lower($extension), self::READERS);
    }

    /**
     * The program a format needs on this server, or null. None does: every
     * format has a built-in reader, and pandoc, catdoc and catppt are used
     * only when they are installed.
     */
    public function programFor(string $extension): ?string
    {
        return null;
    }

    /**
     * The file's text, page by page. A file with no text gives one empty page.
     *
     * @return list<string>
     */
    public function pages(string $path, string $extension): array
    {
        $extension = Str::lower($extension);

        if (! File::exists($path)) {
            throw new InvalidArgumentException("Input file does not exist: {$path}");
        }

        $pages = match (self::READERS[$extension] ?? null) {
            'document' => $this->paginate($this->withoutImages($this->documentText($path, $extension))),
            'old-slides' => $this->oldSlides($path),
            'slides' => $this->slides($path),
            'sheets' => $this->sheets($path),
            'csv' => $this->records(null, $this->csvRows($path)),
            'plain' => $this->paginate($this->plainText($path)),
            default => throw new InvalidArgumentException("Unsupported office format: {$extension}"),
        };

        return $pages === [] ? [''] : $pages;
    }

    /**
     * A word-processor file's text. DOCX and ODT: pandoc when it is
     * installed, otherwise the built-in reader. RTF and DOC: the built-in
     * reader, with the program as a backup for a file it cannot read.
     */
    private function documentText(string $path, string $extension): string
    {
        if (in_array($extension, self::PROGRAM_FIRST, true) && $this->installed($extension)) {
            return $this->programText($path, $extension);
        }

        try {
            return $this->readers->toMarkdown($path, $extension);
        } catch (Throwable $e) {
            return $this->backup($e, $extension, fn (): string => $this->programText($path, $extension));
        }
    }

    /**
     * An old PowerPoint file's text, one page per slide; when catppt reads
     * it as a backup, the text has no slide breaks and is cut into pages.
     *
     * @return list<string>
     */
    private function oldSlides(string $path): array
    {
        try {
            return $this->readers->slides($path);
        } catch (Throwable $e) {
            return $this->backup($e, 'ppt', fn (): array => $this->paginate($this->programText($path, 'ppt')));
        }
    }

    /**
     * Reads a file the built-in reader could not read with the format's
     * program, when it is installed and is only the backup. When the
     * program cannot read it either, the reader's error stands: it says
     * why, such as a password.
     *
     * @template T
     *
     * @param  callable(): T  $read
     * @return T
     */
    private function backup(Throwable $error, string $extension, callable $read): mixed
    {
        if (in_array($extension, self::PROGRAM_FIRST, true) || ! $this->installed($extension)) {
            throw $error;
        }

        try {
            return $read();
        } catch (Throwable) {
            throw $error;
        }
    }

    private function programText(string $path, string $extension): string
    {
        return self::PROGRAMS[$extension] === 'pandoc'
            ? $this->pandoc->toGithubMarkdown($path, $extension)
            : $this->catDoc->toText($path, $extension);
    }

    private function installed(string $extension): bool
    {
        return Executable::exists((string) config('services.bin.'.self::PROGRAMS[$extension]));
    }

    /**
     * Cuts text into pages at paragraph breaks, each about CHARS_PER_PAGE long
     * (a single longer paragraph stays whole).
     *
     * @return list<string>
     */
    private function paginate(string $text): array
    {
        $pages = [];
        $current = [];
        $length = 0;

        foreach (Str::of($text)->replace(["\r\n", "\r"], "\n")->split("/\n{2,}/") as $paragraph) {
            if (blank($paragraph)) {
                continue;
            }

            $current[] = $paragraph;
            $length += Str::length($paragraph);

            if ($length >= self::CHARS_PER_PAGE) {
                $pages[] = implode("\n\n", $current);
                $current = [];
                $length = 0;
            }
        }

        if ($current !== []) {
            $pages[] = implode("\n\n", $current);
        }

        return $pages;
    }

    /** Pandoc keeps image references (`![](media/image1.png)`); they carry no text. */
    private function withoutImages(string $markdown): string
    {
        return Str::replaceMatches(['/!\[[^\]]*\]\([^)]*\)/', '/<img\b[^>]*>/i'], '', $markdown) ?? '';
    }

    /** A plain-text file as UTF-8; older Windows files are often Windows-1252. */
    private function plainText(string $path): string
    {
        $text = File::get($path);

        return mb_check_encoding($text, 'UTF-8') ? $text : mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
    }

    /**
     * The text of each slide, in slide order: every paragraph of every text
     * box and table cell on its own line.
     *
     * @return list<string>
     */
    private function slides(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('Could not open the presentation.');
        }

        try {
            $slides = [];

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $number = Str::match('#^ppt/slides/slide(\d+)\.xml$#', (string) $zip->getNameIndex($index));

                if ($number !== '') {
                    $slides[(int) $number] = $index;
                }
            }

            ksort($slides);

            return array_values(array_map(fn (int $index): string => $this->slideText($zip, $index), $slides));
        } finally {
            $zip->close();
        }
    }

    private function slideText(ZipArchive $zip, int $index): string
    {
        $stat = $zip->statIndex($index);
        $xml = $stat !== false && $stat['size'] <= self::MAX_PART_BYTES ? $zip->getFromIndex($index) : false;

        if ($xml === false || blank($xml)) {
            return '';
        }

        $dom = new DOMDocument;

        // LIBXML_NONET: an uploaded file must not make the server fetch URLs.
        if (! @$dom->loadXML($xml, LIBXML_NONET)) {
            return '';
        }

        $lines = [];

        foreach ($dom->getElementsByTagNameNS(self::DRAWING_NS, 'p') as $paragraph) {
            $line = Str::trim($this->runsText($paragraph));

            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    private function runsText(DOMElement $paragraph): string
    {
        $text = '';

        foreach ($paragraph->getElementsByTagNameNS(self::DRAWING_NS, 't') as $run) {
            $text .= $run->textContent;
        }

        return $text;
    }

    /**
     * Each sheet as a Markdown table under the sheet's name. Formulas give
     * their calculated value.
     *
     * @return list<string>
     */
    private function sheets(string $path): array
    {
        $reader = new Xlsx;
        $reader->setReadDataOnly(true);
        $reader->setReadEmptyCells(false);

        $spreadsheet = $reader->load($path);

        try {
            $pages = [];

            foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
                $pages = [...$pages, ...$this->records($sheet->getTitle(), $sheet->toArray(null, true, false, false))];
            }

            return $pages;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * A table as labelled lines, one per row ("Plan: Pro · Price: 199"), in
     * pages of about {@see CHARS_PER_PAGE} characters cut between rows. The
     * first row with two or more values names the columns; single-value rows
     * above it (a title) are kept as text. A table of one row, or of one
     * column, is kept as plain lines. Each page starts with the sheet's name
     * and, when there are several, the rows it holds.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return list<string>
     */
    private function records(?string $title, array $rows): array
    {
        $cells = array_values(array_filter(
            array_map(fn (array $row): array => array_map($this->cell(...), $row), $rows),
            fn (array $row): bool => implode('', $row) !== '',
        ));

        if ($cells === []) {
            return [filled($title) ? '## '.$title : ''];
        }

        $lead = [];

        while ($cells !== [] && $this->filled(reset($cells)) < 2) {
            $lead[] = implode(' ', array_filter(array_shift($cells), static fn (string $cell): bool => $cell !== ''));
        }

        $header = array_shift($cells);
        $lines = match (true) {
            $header === null => [],
            $cells === [] => [implode(' · ', array_filter($header, static fn (string $cell): bool => $cell !== ''))],
            default => array_map(fn (array $row): string => $this->record($header, $row), $cells),
        };

        return $this->pagesOfRows($title, $lead, $lines);
    }

    /**
     * One row as "Column: value" pairs; an empty value is left out, a column
     * without a name is called by its number.
     *
     * @param  list<string>  $header
     * @param  list<string>  $row
     */
    private function record(array $header, array $row): string
    {
        $pairs = [];

        foreach ($row as $index => $value) {
            if ($value === '') {
                continue;
            }

            $name = ($header[$index] ?? '') !== '' ? $header[$index] : 'Column '.($index + 1);
            $pairs[] = $name.': '.$value;
        }

        return implode(' · ', $pairs);
    }

    /**
     * @param  list<string>  $lead  text above the table, on the first page
     * @param  list<string>  $lines  one line per row
     * @return list<string>
     */
    private function pagesOfRows(?string $title, array $lead, array $lines): array
    {
        $chunks = [];
        $current = [];
        $length = 0;
        $first = 1;

        foreach (array_values(array_filter($lines, static fn (string $line): bool => $line !== '')) as $number => $line) {
            if ($current !== [] && $length + Str::length($line) > self::CHARS_PER_PAGE) {
                $chunks[] = [$first, $number, $current];
                $current = [];
                $length = 0;
                $first = $number + 1;
            }

            $current[] = $line;
            $length += Str::length($line) + 1;
        }

        if ($current !== [] || $chunks === []) {
            $chunks[] = [$first, $first + count($current) - 1, $current];
        }

        $pages = [];

        foreach ($chunks as $index => [$from, $to, $rows]) {
            $heading = filled($title) ? '## '.$title : '';

            if (count($chunks) > 1) {
                $heading = Str::trim($heading.' (rows '.$from.'–'.$to.')');
            }

            $body = [...($index === 0 ? $lead : []), ...$rows];
            $pages[] = Str::trim($heading."\n\n".implode("\n", $body));
        }

        return $pages;
    }

    /**
     * A CSV file's rows. The separator is the one of comma, semicolon and tab
     * the first line uses most; quoted values may hold the separator and
     * line breaks.
     *
     * @return list<list<string>>
     */
    private function csvRows(string $path): array
    {
        $text = $this->plainText($path);
        $firstLine = Str::before(Str::replace("\r\n", "\n", $text), "\n");
        $separator = collect([',', ';', "\t"])->sortByDesc(static fn (string $candidate): int => Str::substrCount($firstLine, $candidate))->first();

        $file = new SplTempFileObject;
        $file->fwrite($text);
        $file->rewind();
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
        $file->setCsvControl($separator, '"', '');

        $rows = [];

        foreach ($file as $row) {
            if (is_array($row) && $row !== [null]) {
                $rows[] = array_map(static fn (mixed $value): string => (string) $value, $row);
            }
        }

        return $rows;
    }

    /** @param  list<string>  $row */
    private function filled(array $row): int
    {
        return count(array_filter($row, static fn (string $cell): bool => $cell !== ''));
    }

    private function cell(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        $text = is_scalar($value) ? (string) $value : '';

        return Str::trim(Str::replace(["\r\n", "\n", "\r"], ' ', $text));
    }
}
