<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion\Markdown;

use RuntimeException;

/**
 * Reads an old Word document (.doc, Word 97 to 2003) as GitHub Markdown
 * with no catdoc on the server.
 *
 * The text is in the file's WordDocument stream, in pieces that the piece
 * table lists, each stored as 8-bit Windows-1252 or as UTF-16. Reading the
 * pieces in order gives the current text even of a file Word saved in a
 * hurry ("fast save"), where later edits sit at the end of the stream. The
 * formatting pages kept beside the text (MS-DOC) give:
 *
 *  - headings from the paragraph style (the built-in "heading 1"…, "Title")
 *    or its outline level;
 *  - bulleted and numbered lists from the list tables, nested by level;
 *  - tables as pipe tables (a table inside a cell is read into the cell;
 *    a one-column table, a box around text, is read as paragraphs);
 *  - tracked changes accepted: deleted text is left out;
 *  - footnotes and endnotes as `[^1]` marks, listed at the end, and the
 *    text of text boxes after the body;
 *  - field codes, pictures, comments, headers and footers left out.
 *
 * Word 6 and Word 95 files give their text, footnotes and text boxes only,
 * decoded by the code page of their language. Older and password-protected
 * files are refused.
 */
final class DocReader
{
    private const int WORD_IDENT = 0xA5EC;

    /** Word 6 and Word 95 files: 8-bit text, and a header of another layout. */
    private const int WORD_6_IDENT = 0xA5DC;

    private const int FIRST_WORD_97_VERSION = 0x00C0;

    private const int COMPLEX = 0x0004;

    private const int ENCRYPTED = 0x0100;

    private const int TABLE_STREAM_1 = 0x0200;

    // Entries in the header's list of table-stream parts (MS-DOC FibRgFcLcb97).
    private const int STYLES = 1;

    private const int FOOTNOTE_REFERENCES = 2;

    private const int FOOTNOTE_TEXTS = 3;

    private const int CHARACTER_PAGES = 12;

    private const int PARAGRAPH_PAGES = 13;

    private const int PIECE_TABLE = 33;

    private const int ENDNOTE_REFERENCES = 46;

    private const int ENDNOTE_TEXTS = 47;

    private const int LISTS = 73;

    private const int LIST_OVERRIDES = 74;

    private const int PARAGRAPH_END = 0x0D;

    private const int CELL_END = 0x07;

    private const int PAGE_BREAK = 0x0C;

    private const int FIELD_BEGIN = 0x13;

    private const int FIELD_SEPARATOR = 0x14;

    private const int FIELD_END = 0x15;

    /** Stands in for a note reference until the note gets its number (a private-use character). */
    private const int NOTE_MARK = 0xE000;

    /** The style id of "Title"; 1 to 9 are "heading 1" to "heading 9". */
    private const int TITLE_STYLE = 62;

    private const int NO_STYLE = 0x0FFF;

    private const int BULLET = 23;

    private const int NO_NUMBER = 255;

    /** 8-bit text is Windows-1252: these bytes are not the same code point in Unicode. */
    private const array WINDOWS_1252 = [
        0x80 => 0x20AC, 0x82 => 0x201A, 0x83 => 0x0192, 0x84 => 0x201E, 0x85 => 0x2026, 0x86 => 0x2020,
        0x87 => 0x2021, 0x88 => 0x02C6, 0x89 => 0x2030, 0x8A => 0x0160, 0x8B => 0x2039, 0x8C => 0x0152,
        0x8E => 0x017D, 0x91 => 0x2018, 0x92 => 0x2019, 0x93 => 0x201C, 0x94 => 0x201D, 0x95 => 0x2022,
        0x96 => 0x2013, 0x97 => 0x2014, 0x98 => 0x02DC, 0x99 => 0x2122, 0x9A => 0x0161, 0x9B => 0x203A,
        0x9C => 0x0153, 0x9E => 0x017E, 0x9F => 0x0178,
    ];

    private const array PLAIN_PARAGRAPH = [
        'style' => 0, 'inTable' => false, 'rowEnd' => false, 'depth' => 0, 'innerRowEnd' => false,
        'outline' => null, 'list' => null, 'level' => null,
    ];

    private string $word = '';

    private string $table = '';

    private int $partListOffset = 0;

    private int $partCount = 0;

    /** Where the header lists the lengths of the stories. */
    private int $storyLengthsOffset = 0;

    /** The code page of a Word 6 or 95 file's 8-bit text; null for Word 97 and later. */
    private ?int $codePage = null;

    /** @var list<array{cp: int, end: int, fc: int, compressed: bool}> */
    private array $pieces = [];

    /** @var list<array{0: int, 1: int, 2: array{style: int, inTable: bool, rowEnd: bool, depth: int, innerRowEnd: bool, outline: ?int, list: ?int, level: ?int}}> */
    private array $paragraphRuns = [];

    /** @var list<array{0: int, 1: int}> byte ranges of deleted text */
    private array $deletions = [];

    /** @var array<int, array{id: int, base: int, name: string, outline: ?int, list: ?int, level: ?int}> */
    private array $styles = [];

    /** @var array<int, array<int, array{bullet: bool, start: int}>> list id → level → format */
    private array $lists = [];

    /** @var array<int, array{list: int, starts: array<int, int>, formats: array<int, array{bullet: bool, start: int}>}> override number → its list and changes */
    private array $overrides = [];

    /** @var array<int, string> position of a note reference in the body → the note's text */
    private array $noteTexts = [];

    /** @var array<int, array<int, int>> list id → level → the last number given */
    private array $counters = [];

    /** @var array<string, true> "override:level" restarts already applied */
    private array $restarted = [];

    private ?int $lastList = null;

    private bool $newList = false;

    private NoteList $notes;

    public function toMarkdown(string $path): string
    {
        $file = CompoundFile::open($path);

        try {
            $this->reset();
            $this->word = $file->read('WordDocument') ?? throw new RuntimeException('The file is not a Word document.');
            $flags = $this->readHeader();

            // Word 6 and 95 keep the tables in the main stream.
            $this->table = $this->codePage !== null ? $this->word : ($file->read(($flags & self::TABLE_STREAM_1) !== 0 ? '1Table' : '0Table')
                ?? throw new RuntimeException('The Word document has no table stream.'));
        } finally {
            $file->close();
        }

        $this->readPieces($flags);

        if ($this->codePage === null) {
            $this->readParagraphRuns();
            $this->readDeletions();
            $this->readStyles();
            $this->readLists();
        }

        $lengths = $this->storyLengths();
        $this->readNotes(self::FOOTNOTE_REFERENCES, self::FOOTNOTE_TEXTS, $lengths[0]);
        $this->readNotes(self::ENDNOTE_REFERENCES, self::ENDNOTE_TEXTS, array_sum(array_slice($lengths, 0, 5)));

        $textBoxes = array_sum(array_slice($lengths, 0, 6));

        return trim(MarkdownText::join([
            ...$this->blocks($this->paragraphs(0, $lengths[0], withNotes: true)),
            ...$this->blocks($this->paragraphs($textBoxes, $textBoxes + $lengths[6])),
            [MarkdownText::BLOCK, $this->notes->toMarkdown()],
        ]));
    }

    private function reset(): void
    {
        $this->pieces = $this->paragraphRuns = $this->deletions = $this->styles = $this->lists = [];
        $this->overrides = $this->noteTexts = $this->counters = $this->restarted = [];
        $this->lastList = $this->codePage = null;
        $this->newList = false;
        $this->notes = new NoteList;
    }

    /** Checks the file header and finds the list of table-stream parts; returns the header flags. */
    private function readHeader(): int
    {
        $ident = $this->uint16($this->word, 0);
        $flags = $this->uint16($this->word, 0x0A);

        if ($ident !== self::WORD_IDENT && $ident !== self::WORD_6_IDENT) {
            throw new RuntimeException('The file is not a Word document, or one older than Word 6.');
        }

        if (($flags & self::ENCRYPTED) !== 0) {
            throw new RuntimeException('The Word document is password protected.');
        }

        if ($ident === self::WORD_6_IDENT) {
            // A fixed header: the story lengths, then the table-stream parts up to the piece table.
            $this->codePage = CodePage::forLanguage($this->uint16($this->word, 6));
            $this->storyLengthsOffset = 0x34;
            $this->partListOffset = 0x58;
            $this->partCount = self::PIECE_TABLE + 1;

            return $flags;
        }

        if ($this->uint16($this->word, 2) < self::FIRST_WORD_97_VERSION) {
            throw new RuntimeException('The Word document has an unknown header.');
        }

        $longs = 34 + $this->uint16($this->word, 32) * 2;
        $partList = $longs + 2 + $this->uint16($this->word, $longs) * 4;

        $this->storyLengthsOffset = $longs + 2 + 12;
        $this->partCount = $this->uint16($this->word, $partList);
        $this->partListOffset = $partList + 2;

        return $flags;
    }

    /**
     * The lengths, in characters, of the stories that follow each other in
     * the text: body, footnotes, headers, macros, comments, endnotes, text
     * boxes and header text boxes.
     *
     * @return list<int>
     */
    private function storyLengths(): array
    {
        return array_map(fn (int $index): int => $this->uint32($this->word, $this->storyLengthsOffset + $index * 4), range(0, 7));
    }

    /** @return array{0: int, 1: int} the offset and length of a table-stream part */
    private function part(int $index): array
    {
        if ($index >= $this->partCount) {
            return [0, 0];
        }

        $at = $this->partListOffset + $index * 8;

        return [$this->uint32($this->word, $at), $this->uint32($this->word, $at + 4)];
    }

    private function partBytes(int $index): string
    {
        [$offset, $length] = $this->part($index);

        return $length > 0 ? substr($this->table, $offset, $length) : '';
    }

    /** The piece table: where each run of text is stored and how. */
    private function readPieces(int $flags): void
    {
        $clx = $this->partBytes(self::PIECE_TABLE);

        if ($this->codePage !== null && ($clx === '' || ($flags & self::COMPLEX) === 0)) {
            // A Word 6 file saved in full keeps all its text in one run.
            $this->pieces[] = ['cp' => 0, 'end' => array_sum($this->storyLengths()) + 1, 'fc' => $this->uint32($this->word, 0x18), 'compressed' => true];

            return;
        }

        $position = 0;
        $table = null;

        // Formatting entries come first; the piece table is the entry of type 2.
        while ($position < strlen($clx)) {
            $type = ord($clx[$position]);

            if ($type === 1) {
                $position += 3 + $this->uint16($clx, $position + 1);
            } elseif ($type === 2) {
                $table = substr($clx, $position + 5, $this->uint32($clx, $position + 1));

                break;
            } else {
                break;
            }
        }

        if ($table === null) {
            throw new RuntimeException('The Word document has no piece table.');
        }

        [$positions, $descriptors] = $this->plc($table, 8);

        foreach ($descriptors as $index => $descriptor) {
            $fc = $this->uint32($descriptor, 2);
            $compressed = $this->codePage !== null || ($fc & 0x40000000) !== 0;

            $this->pieces[] = [
                'cp' => $positions[$index],
                'end' => $positions[$index + 1],
                'fc' => $this->codePage !== null ? $fc : ($compressed ? intdiv($fc & 0x3FFFFFFF, 2) : $fc & 0x3FFFFFFF),
                'compressed' => $compressed,
            ];
        }
    }

    /** The paragraph properties, from the formatting pages the paragraph table points to. */
    private function readParagraphRuns(): void
    {
        foreach ($this->formattingPages(self::PARAGRAPH_PAGES) as $page) {
            $count = ord($page[511]);

            for ($index = 0; $index < $count; $index++) {
                $offset = ord($page[($count + 1) * 4 + $index * 13]) * 2;

                $this->paragraphRuns[] = [
                    $this->uint32($page, $index * 4),
                    $this->uint32($page, ($index + 1) * 4),
                    $this->paragraphProperties($page, $offset),
                ];
            }
        }

        usort($this->paragraphRuns, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
    }

    /**
     * @return array{style: int, inTable: bool, rowEnd: bool, depth: int, innerRowEnd: bool, outline: ?int, list: ?int, level: ?int}
     */
    private function paragraphProperties(string $page, int $offset): array
    {
        $properties = self::PLAIN_PARAGRAPH;

        if ($offset === 0 || $offset >= 510) {
            return $properties;
        }

        $size = ord($page[$offset]);
        $data = $size === 0 ? substr($page, $offset + 2, ord($page[$offset + 1]) * 2) : substr($page, $offset + 1, $size * 2 - 1);
        $properties['style'] = $this->uint16($data, 0);

        foreach ($this->sprms(substr($data, 2)) as [$sprm, $operand]) {
            switch ($sprm) {
                case 0x4600:
                    $properties['style'] = $this->uint16($operand, 0);
                    break;
                case 0x2416:
                    $properties['inTable'] = ord($operand) !== 0;
                    break;
                case 0x2417:
                    $properties['rowEnd'] = ord($operand) !== 0;
                    break;
                case 0x6649:
                    $properties['depth'] = $this->uint32($operand, 0);
                    break;
                case 0x244C:
                    $properties['innerRowEnd'] = ord($operand) !== 0;
                    break;
                case 0x2640:
                    $properties['outline'] = ord($operand);
                    break;
                case 0x460B:
                    $properties['list'] = $this->uint16($operand, 0);
                    break;
                case 0x260A:
                    $properties['level'] = ord($operand);
                    break;
            }
        }

        return $properties;
    }

    /** Tracked deletions, from the character properties. */
    private function readDeletions(): void
    {
        foreach ($this->formattingPages(self::CHARACTER_PAGES) as $page) {
            $count = ord($page[511]);

            for ($index = 0; $index < $count; $index++) {
                $offset = ord($page[($count + 1) * 4 + $index]) * 2;

                if ($offset === 0) {
                    continue;
                }

                foreach ($this->sprms(substr($page, $offset + 1, ord($page[$offset]))) as [$sprm, $operand]) {
                    if ($sprm === 0x0800 && in_array(ord($operand), [1, 0x81], true)) {
                        $this->deletions[] = [$this->uint32($page, $index * 4), $this->uint32($page, ($index + 1) * 4)];
                    }
                }
            }
        }

        usort($this->deletions, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
    }

    /**
     * The 512-byte formatting pages that a table of character or paragraph
     * runs points to.
     *
     * @return list<string>
     */
    private function formattingPages(int $part): array
    {
        $pages = [];

        foreach ($this->plc($this->partBytes($part), 4)[1] as $entry) {
            $page = substr($this->word, ($this->uint32($entry, 0) & 0x3FFFFF) * 512, 512);

            if (strlen($page) === 512) {
                $pages[] = $page;
            }
        }

        return $pages;
    }

    /**
     * The style sheet: each paragraph style's name, the style it is based
     * on, and its outline level and list.
     */
    private function readStyles(): void
    {
        $sheet = $this->partBytes(self::STYLES);
        $headerSize = $this->uint16($sheet, 0);
        $count = $this->uint16($sheet, 2);
        $baseSize = $this->uint16($sheet, 4);
        $position = 2 + $headerSize;

        for ($index = 0; $index < $count && $position + 2 <= strlen($sheet); $index++) {
            $size = $this->uint16($sheet, $position);
            $style = substr($sheet, $position + 2, $size);
            $position += 2 + $size;

            // Only paragraph styles (kind 1) carry paragraph properties.
            if ($size === 0 || ($this->uint16($style, 2) & 0x0F) !== 1) {
                continue;
            }

            $nameLength = $this->uint16($style, $baseSize);
            $name = mb_convert_encoding(substr($style, $baseSize + 2, $nameLength * 2), 'UTF-8', 'UTF-16LE');
            $properties = $baseSize + 2 + ($nameLength + 1) * 2;
            $definition = ['id' => $this->uint16($style, 0) & 0x0FFF, 'base' => $this->uint16($style, 2) >> 4, 'name' => $name, 'outline' => null, 'list' => null, 'level' => null];

            // The paragraph properties: their size, the style number, the properties.
            foreach ($this->sprms(substr($style, $properties + 4, $this->uint16($style, $properties) - 2)) as [$sprm, $operand]) {
                match ($sprm) {
                    0x2640 => $definition['outline'] = ord($operand),
                    0x460B => $definition['list'] = $this->uint16($operand, 0),
                    0x260A => $definition['level'] = ord($operand),
                    default => null,
                };
            }

            $this->styles[$index] = $definition;
        }
    }

    /** The list definitions and the overrides that paragraphs point to. */
    private function readLists(): void
    {
        [$offset, $length] = $this->part(self::LISTS);

        if ($length >= 2) {
            $count = max(0, $this->int16($this->table, $offset));
            $position = $offset + 2 + $count * 28;

            for ($index = 0; $index < $count && $position < strlen($this->table); $index++) {
                $definition = $offset + 2 + $index * 28;
                $id = $this->uint32($this->table, $definition);
                $levels = (ord(($this->table[$definition + 26] ?? "\0")[0]) & 1) === 1 ? 1 : 9;

                for ($level = 0; $level < $levels; $level++) {
                    [$this->lists[$id][$level], $position] = $this->listLevel($position);
                }
            }
        }

        [$offset, $length] = $this->part(self::LIST_OVERRIDES);

        if ($length < 4) {
            return;
        }

        $count = min($this->uint32($this->table, $offset), intdiv($length, 16));
        $levelCounts = [];

        for ($index = 0; $index < $count; $index++) {
            $at = $offset + 4 + $index * 16;
            $this->overrides[$index + 1] = ['list' => $this->uint32($this->table, $at), 'starts' => [], 'formats' => []];
            $levelCounts[$index + 1] = ord(($this->table[$at + 12] ?? "\0")[0]);
        }

        $position = $offset + 4 + $count * 16;

        foreach ($levelCounts as $number => $levels) {
            $position += 4;

            for ($index = 0; $index < $levels && $position < strlen($this->table); $index++) {
                $start = $this->int32($this->table, $position);
                $flags = $this->uint32($this->table, $position + 4);
                $level = $flags & 0x0F;
                $position += 8;

                if (($flags & 0x10) !== 0) {
                    $this->overrides[$number]['starts'][$level] = $start;
                }

                if (($flags & 0x20) !== 0) {
                    [$this->overrides[$number]['formats'][$level], $position] = $this->listLevel($position);
                }
            }
        }
    }

    /**
     * One list level: whether it is bulleted and where it starts.
     *
     * @return array{0: array{bullet: bool, start: int}, 1: int} the level and the offset after it
     */
    private function listLevel(int $position): array
    {
        $format = ord(($this->table[$position + 4] ?? "\0")[0]);
        $start = $this->int32($this->table, $position);
        $position += 28 + ord(($this->table[$position + 24] ?? "\0")[0]) + ord(($this->table[$position + 25] ?? "\0")[0]);
        $position += 2 + $this->uint16($this->table, $position) * 2;

        return [['bullet' => in_array($format, [self::BULLET, self::NO_NUMBER], true), 'start' => $start], $position];
    }

    /** The footnotes or endnotes, keyed by where they are referenced in the body. */
    private function readNotes(int $references, int $texts, int $storyStart): void
    {
        [$referencePositions] = $this->plc($this->partBytes($references), 2);
        [$textPositions] = $this->plc($this->partBytes($texts), 0);

        foreach (array_slice($referencePositions, 0, -1) as $index => $position) {
            if (! isset($textPositions[$index + 1])) {
                break;
            }

            $paragraphs = $this->paragraphs($storyStart + $textPositions[$index], $storyStart + $textPositions[$index + 1]);
            $this->noteTexts[$position] = implode(' ', array_column($paragraphs, 'text'));
        }
    }

    /**
     * The paragraphs of a stretch of text, each with the character that
     * ends it (paragraph mark, cell mark or page break) and its properties.
     * Field codes, deleted text, pictures and other marks are left out.
     *
     * @return list<array{text: string, end: int, notes: list<int>, properties: array{style: int, inTable: bool, rowEnd: bool, depth: int, innerRowEnd: bool, outline: ?int, list: ?int, level: ?int}}>
     */
    private function paragraphs(int $from, int $to, bool $withNotes = false): array
    {
        $paragraphs = [];
        $units = [];
        $notes = [];
        $fields = [];
        $inCode = false;

        foreach ($this->pieces as $piece) {
            $start = max($from, $piece['cp']);
            $end = min($to, $piece['end']);

            if ($start >= $end) {
                continue;
            }

            $width = $piece['compressed'] ? 1 : 2;
            $first = $piece['fc'] + ($start - $piece['cp']) * $width;
            $bytes = substr($this->word, $first, ($end - $start) * $width);
            $bytes = substr($bytes, 0, strlen($bytes) - strlen($bytes) % $width);

            if ($bytes === '') {
                continue;
            }

            foreach ($this->codeUnits($bytes, $width) as $index => $code) {
                $fc = $first + $index * $width;

                if ($this->deletions !== [] && $this->isDeleted($fc)) {
                    continue;
                }

                if (in_array($code, [self::PARAGRAPH_END, self::CELL_END, self::PAGE_BREAK], true)) {
                    $paragraphs[] = ['text' => $this->utf8($units), 'end' => $code, 'notes' => $notes, 'properties' => $this->propertiesAt($fc)];
                    $units = $notes = [];
                } elseif ($code === self::FIELD_BEGIN) {
                    $fields[] = true;
                    $inCode = true;
                } elseif ($code === self::FIELD_SEPARATOR || $code === self::FIELD_END) {
                    if ($code === self::FIELD_END) {
                        array_pop($fields);
                    } elseif ($fields !== []) {
                        $fields[array_key_last($fields)] = false;
                    }

                    $inCode = in_array(true, $fields, true);
                } elseif ($inCode) {
                    continue;
                } elseif ($withNotes && isset($this->noteTexts[$start + $index])) {
                    $units[] = self::NOTE_MARK;
                    $notes[] = $start + $index;
                } elseif ($code >= 0x20 && $code !== self::NOTE_MARK) {
                    $units[] = $code === 0xA0 ? 0x20 : $code;
                } elseif ($code === 0x09 || $code === 0x0B) {
                    $units[] = $code === 0x0B ? 0x0A : 0x09;
                } elseif ($code === 0x1E) {
                    $units[] = 0x2D;
                }
            }
        }

        if ($units !== []) {
            $paragraphs[] = ['text' => $this->utf8($units), 'end' => self::PARAGRAPH_END, 'notes' => $notes, 'properties' => self::PLAIN_PARAGRAPH];
        }

        return $paragraphs;
    }

    /**
     * The text of a piece as UTF-16 code units: 8-bit text is Windows-1252
     * (Word 97 and later) or the file's code page (Word 6 and 95).
     *
     * @return list<int>
     */
    private function codeUnits(string $bytes, int $width): array
    {
        if ($width === 2) {
            return array_values((array) unpack('v*', $bytes));
        }

        if ($this->codePage !== null) {
            $utf16 = mb_convert_encoding(CodePage::toUtf8($bytes, $this->codePage), 'UTF-16LE', 'UTF-8');

            return $utf16 === '' ? [] : array_values((array) unpack('v*', $utf16));
        }

        return array_map(static fn (int $byte): int => self::WINDOWS_1252[$byte] ?? $byte, array_values((array) unpack('C*', $bytes)));
    }

    /**
     * Turns paragraphs into Markdown blocks: table cells gathered into rows
     * and rows into tables, the rest as headings, list items or paragraphs.
     *
     * @param  list<array{text: string, end: int, notes: list<int>, properties: array{style: int, inTable: bool, rowEnd: bool, depth: int, innerRowEnd: bool, outline: ?int, list: ?int, level: ?int}}>  $paragraphs
     * @return list<array{0: string, 1: string}>
     */
    private function blocks(array $paragraphs): array
    {
        $blocks = [];
        $rows = [];
        $row = [];
        $cell = [];
        $guessTables = $this->paragraphRuns === [];
        $lastEnd = null;

        foreach ($paragraphs as $paragraph) {
            $properties = $paragraph['properties'];
            $text = $this->withNoteMarks($paragraph);
            $depth = $properties['inTable'] ? max(1, $properties['depth']) : 0;

            if ($guessTables && $paragraph['end'] === self::CELL_END) {
                // Without paragraph properties, an empty cell right after a cell ends the row.
                $depth = 1;
                $properties['rowEnd'] = $text === '' && $lastEnd === self::CELL_END && $row !== [];
            }

            $lastEnd = $paragraph['end'];

            if ($depth > 1) {
                if (! $properties['innerRowEnd']) {
                    $cell[] = $text;
                }

                continue;
            }

            if ($depth === 1) {
                if ($properties['rowEnd']) {
                    $rows[] = $row;
                    $row = $cell = [];
                } else {
                    $cell[] = $text;

                    if ($paragraph['end'] === self::CELL_END) {
                        $row[] = MarkdownText::inline(implode("\n", $cell));
                        $cell = [];
                    }
                }

                continue;
            }

            if ($rows !== [] || $row !== []) {
                array_push($blocks, ...MarkdownText::tableBlocks($row === [] ? $rows : [...$rows, $row]));
                $rows = $row = $cell = [];
            }

            $blocks[] = $this->block(MarkdownText::inline($text), $properties);
        }

        if ($rows !== [] || $row !== []) {
            array_push($blocks, ...MarkdownText::tableBlocks($row === [] ? $rows : [...$rows, $row]));
        }

        return $blocks;
    }

    /**
     * @param  array{style: int, inTable: bool, rowEnd: bool, depth: int, innerRowEnd: bool, outline: ?int, list: ?int, level: ?int}  $properties
     * @return array{0: string, 1: string}
     */
    private function block(string $text, array $properties): array
    {
        if ($text === '') {
            return [MarkdownText::BLOCK, ''];
        }

        $heading = $properties['outline'] !== null
            ? ($properties['outline'] < 9 ? $properties['outline'] + 1 : null)
            : $this->styleHeading($properties['style']);

        if ($heading !== null) {
            return [MarkdownText::BLOCK, MarkdownText::heading($heading, $text)];
        }

        $list = $properties['list'] ?? $this->styleValue($properties['style'], 'list');

        if ($list !== null && $list > 0 && isset($this->overrides[$list])) {
            $level = max(0, min(8, $properties['level'] ?? $this->styleValue($properties['style'], 'level') ?? 0));
            $item = MarkdownText::item($level, $this->marker($list, $level), $text);

            return [$this->newList ? MarkdownText::LIST_START : MarkdownText::ITEM, $item];
        }

        return [MarkdownText::BLOCK, MarkdownText::paragraph($text)];
    }

    /** The heading level a paragraph style gives, following the styles it is based on. */
    private function styleHeading(int $style): ?int
    {
        for ($depth = 0; $depth < 10 && isset($this->styles[$style]); $depth++) {
            $definition = $this->styles[$style];

            if ($definition['id'] >= 1 && $definition['id'] <= 9) {
                return $definition['id'];
            }

            if ($definition['id'] === self::TITLE_STYLE || in_array(strtolower($definition['name']), ['title', 'toc heading'], true)) {
                return 1;
            }

            if (preg_match('/^heading\s*([1-9])$/i', $definition['name'], $match) === 1) {
                return (int) $match[1];
            }

            if ($definition['outline'] !== null) {
                return $definition['outline'] < 9 ? $definition['outline'] + 1 : null;
            }

            if ($definition['base'] === self::NO_STYLE) {
                return null;
            }

            $style = $definition['base'];
        }

        return null;
    }

    /** A list setting of a paragraph style, or of the nearest style it is based on that has one. */
    private function styleValue(int $style, string $key): ?int
    {
        for ($depth = 0; $depth < 10 && isset($this->styles[$style]); $depth++) {
            if ($this->styles[$style][$key] !== null) {
                return $this->styles[$style][$key];
            }

            $style = $this->styles[$style]['base'];
        }

        return null;
    }

    /**
     * The marker of the next list item: "-" for bullets, its number for
     * numbered lists. Word numbers a list across every override that points
     * to it; an override with a start number restarts it once.
     */
    private function marker(int $override, int $level): string
    {
        $list = $this->overrides[$override]['list'];
        $format = $this->overrides[$override]['formats'][$level] ?? $this->lists[$list][$level] ?? ['bullet' => true, 'start' => 1];
        $counters = array_filter($this->counters[$list] ?? [], static fn (int $deeper): bool => $deeper <= $level, ARRAY_FILTER_USE_KEY);
        $restart = isset($this->overrides[$override]['starts'][$level]) && ! isset($this->restarted[$override.':'.$level]);

        if ($restart) {
            $this->restarted[$override.':'.$level] = true;
            $counters[$level] = $this->overrides[$override]['starts'][$level];
        } else {
            $counters[$level] = isset($counters[$level]) ? $counters[$level] + 1 : $format['start'];
        }

        // Only a top-level item starts a list; a nested list stays inside its item.
        $this->newList = $level === 0 && ($list !== $this->lastList || $restart);
        $this->lastList = $level === 0 ? $list : $this->lastList;
        $this->counters[$list] = $counters;

        return $format['bullet'] ? '-' : $counters[$level].'.';
    }

    /**
     * @param  array{text: string, notes: list<int>}  $paragraph
     */
    private function withNoteMarks(array $paragraph): string
    {
        $text = $paragraph['text'];

        foreach ($paragraph['notes'] as $position) {
            $mark = $this->notes->add($this->noteTexts[$position]);
            $at = strpos($text, "\u{E000}");
            $text = $at === false ? $text.$mark : substr_replace($text, $mark, $at, 3);
        }

        return $text;
    }

    /**
     * @return array{style: int, inTable: bool, rowEnd: bool, depth: int, innerRowEnd: bool, outline: ?int, list: ?int, level: ?int}
     */
    private function propertiesAt(int $fc): array
    {
        $low = 0;
        $high = count($this->paragraphRuns) - 1;

        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);
            [$start, $end, $properties] = $this->paragraphRuns[$middle];

            if ($fc < $start) {
                $high = $middle - 1;
            } elseif ($fc >= $end) {
                $low = $middle + 1;
            } else {
                return $properties;
            }
        }

        return self::PLAIN_PARAGRAPH;
    }

    private function isDeleted(int $fc): bool
    {
        $low = 0;
        $high = count($this->deletions) - 1;

        while ($low <= $high) {
            $middle = intdiv($low + $high, 2);

            if ($fc < $this->deletions[$middle][0]) {
                $high = $middle - 1;
            } elseif ($fc >= $this->deletions[$middle][1]) {
                $low = $middle + 1;
            } else {
                return true;
            }
        }

        return false;
    }

    /**
     * The properties in a list of property changes, each a two-byte code
     * whose top bits give the operand's size.
     *
     * @return list<array{0: int, 1: string}>
     */
    private function sprms(string $list): array
    {
        $sprms = [];
        $position = 0;
        $length = strlen($list);

        while ($position + 2 <= $length) {
            $sprm = $this->uint16($list, $position);
            $position += 2;

            $size = match ($sprm >> 13) {
                0, 1 => 1,
                2, 4, 5 => 2,
                3 => 4,
                7 => 3,
                default => $this->variableSize($sprm, $list, $position),
            };

            if ($size < 1 || $position + $size > $length) {
                break;
            }

            $sprms[] = [$sprm, substr($list, $position, $size)];
            $position += $size;
        }

        return $sprms;
    }

    /** The size of an operand that gives its own size: in its first byte, with two exceptions. */
    private function variableSize(int $sprm, string $list, int $position): int
    {
        // Table definitions give a two-byte size.
        if ($sprm === 0xD608 || $sprm === 0xD606) {
            return $this->uint16($list, $position) + 1;
        }

        $size = ord(($list[$position] ?? "\0")[0]);

        // Tab changes too long for a one-byte size are counted from their parts.
        if ($sprm === 0xC615 && $size === 255) {
            $deleted = ord(($list[$position + 1] ?? "\0")[0]);
            $added = ord(($list[$position + 2 + $deleted * 4] ?? "\0")[0]);

            return 3 + $deleted * 4 + $added * 3;
        }

        return 1 + $size;
    }

    /**
     * A table of positions with an item of fixed size for each span:
     * n + 1 positions, then n items.
     *
     * @return array{0: list<int>, 1: list<string>}
     */
    private function plc(string $bytes, int $itemSize): array
    {
        $count = intdiv(strlen($bytes) - 4, 4 + $itemSize);

        if ($count < 0) {
            return [[], []];
        }

        $positions = [];
        $items = [];

        for ($index = 0; $index <= $count; $index++) {
            $positions[] = $this->uint32($bytes, $index * 4);
        }

        for ($index = 0; $index < $count; $index++) {
            $items[] = substr($bytes, ($count + 1) * 4 + $index * $itemSize, $itemSize);
        }

        return [$positions, $items];
    }

    /**
     * @param  list<int>  $units  UTF-16 code units
     */
    private function utf8(array $units): string
    {
        $bytes = '';

        foreach (array_chunk($units, 8192) as $chunk) {
            $bytes .= pack('v*', ...$chunk);
        }

        return $bytes === '' ? '' : mb_convert_encoding($bytes, 'UTF-8', 'UTF-16LE');
    }

    private function uint16(string $bytes, int $offset): int
    {
        return $offset >= 0 && strlen($bytes) >= $offset + 2 ? (int) unpack('v', $bytes, $offset)[1] : 0;
    }

    private function int16(string $bytes, int $offset): int
    {
        $value = $this->uint16($bytes, $offset);

        return $value >= 0x8000 ? $value - 0x10000 : $value;
    }

    private function uint32(string $bytes, int $offset): int
    {
        return $offset >= 0 && strlen($bytes) >= $offset + 4 ? (int) unpack('V', $bytes, $offset)[1] : 0;
    }

    private function int32(string $bytes, int $offset): int
    {
        $value = $this->uint32($bytes, $offset);

        return $value >= 0x80000000 ? $value - 0x100000000 : $value;
    }
}
