<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion;

use DOMDocument;
use DOMElement;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use RuntimeException;
use ZipArchive;

/**
 * Reads the text of office and plain-text files as pages, with no office
 * suite on the server:
 *
 *  - DOCX, ODT, RTF  → pandoc, as Markdown (headings, lists and tables kept)
 *  - PPTX            → one page per slide, read straight from the file
 *  - XLSX            → one page per sheet, as a Markdown table (PhpSpreadsheet)
 *  - DOC, PPT        → catdoc / catppt (the old binary formats)
 *  - TXT, MD, CSV    → the file itself
 *
 * Slides and sheets are real pages. The other formats have no pages of their
 * own, so their text is cut into pages of about {@see CHARS_PER_PAGE}
 * characters at paragraph breaks; a page count is then an estimate.
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
        'docx' => 'pandoc',
        'odt' => 'pandoc',
        'rtf' => 'pandoc',
        'doc' => 'catdoc',
        'ppt' => 'catdoc',
        'pptx' => 'slides',
        'xlsx' => 'sheets',
        'txt' => 'plain',
        'md' => 'plain',
        'markdown' => 'plain',
        'csv' => 'plain',
    ];

    public function __construct(
        private readonly Pandoc $pandoc,
        private readonly CatDoc $catDoc,
    ) {}

    public function supports(string $extension): bool
    {
        return array_key_exists(strtolower($extension), self::READERS);
    }

    /**
     * The program that reads the format (its `services.bin` key), or null
     * when the app reads it itself.
     */
    public function programFor(string $extension): ?string
    {
        return match (self::READERS[strtolower($extension)] ?? null) {
            'pandoc' => 'pandoc',
            'catdoc' => $this->catDoc->programFor($extension),
            default => null,
        };
    }

    /**
     * The file's text, page by page. A file with no text gives one empty page.
     *
     * @return list<string>
     */
    public function pages(string $path, string $extension): array
    {
        $extension = strtolower($extension);

        if (! File::exists($path)) {
            throw new InvalidArgumentException("Input file does not exist: {$path}");
        }

        $pages = match (self::READERS[$extension] ?? null) {
            'pandoc' => $this->paginate($this->withoutImages($this->pandoc->toGithubMarkdown($path, $extension))),
            'catdoc' => $this->paginate($this->catDoc->toText($path, $extension)),
            'slides' => $this->slides($path),
            'sheets' => $this->sheets($path),
            'plain' => $this->paginate($this->plainText($path)),
            default => throw new InvalidArgumentException("Unsupported office format: {$extension}"),
        };

        return $pages === [] ? [''] : $pages;
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

        foreach (preg_split("/\n{2,}/", str_replace(["\r\n", "\r"], "\n", $text)) ?: [] as $paragraph) {
            if (blank($paragraph)) {
                continue;
            }

            $current[] = $paragraph;
            $length += mb_strlen($paragraph);

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
        return (string) preg_replace(['/!\[[^\]]*\]\([^)]*\)/', '/<img\b[^>]*>/i'], '', $markdown);
    }

    /** A plain-text file as UTF-8; older Windows files are often Windows-1252. */
    private function plainText(string $path): string
    {
        $text = (string) File::get($path);

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
                if (preg_match('#^ppt/slides/slide(\d+)\.xml$#', (string) $zip->getNameIndex($index), $match) === 1) {
                    $slides[(int) $match[1]] = $index;
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
            $line = trim($this->runsText($paragraph));

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
                $table = $this->markdownTable($sheet->toArray(null, true, false, false));

                $pages[] = trim('## '.$sheet->getTitle()."\n\n".$table);
            }

            return $pages;
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     */
    private function markdownTable(array $rows): string
    {
        $cells = array_values(array_filter(
            array_map(fn (array $row): array => array_map($this->cell(...), $row), $rows),
            fn (array $row): bool => implode('', $row) !== '',
        ));

        if ($cells === []) {
            return '';
        }

        $width = max(array_map($this->usedWidth(...), $cells));
        $lines = [];

        foreach ($cells as $number => $row) {
            $row = array_pad(array_slice($row, 0, $width), $width, '');
            $lines[] = '| '.implode(' | ', $row).' |';

            if ($number === 0) {
                $lines[] = '|'.str_repeat(' --- |', $width);
            }
        }

        return implode("\n", $lines);
    }

    private function cell(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        $text = is_scalar($value) ? (string) $value : '';

        return trim(str_replace(['|', "\r\n", "\n", "\r"], ['\\|', ' ', ' ', ' '], $text));
    }

    /**
     * @param  list<string>  $row
     */
    private function usedWidth(array $row): int
    {
        for ($column = count($row); $column > 0; $column--) {
            if ($row[$column - 1] !== '') {
                return $column;
            }
        }

        return 1;
    }
}
