<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge\Document\OldOffice;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Builds small Word documents in the binary format (.doc), for tests of the
 * built-in reader: Word 97 files with styles, lists, tables, footnotes,
 * fields and tracked deletions, and Word 6 files with 8-bit text.
 *
 * The text is stored the way a fast-saved file stores it: in two pieces, the
 * second one first and stale text between them, the first one as 8-bit text
 * when it is plain ASCII. So every test also covers the piece table.
 */
final class WordBinaryFile
{
    public const string FIELD_BEGIN = "\x13";

    public const string FIELD_SEPARATOR = "\x14";

    public const string FIELD_END = "\x15";

    /** Text the piece table does not point to: an earlier save's words. */
    public const string STALE = "STALE WORDS FROM AN EARLIER SAVE\r";

    private const int TEXT_START = 0x400;

    /** @var list<array{runs: list<string|array{deleted?: string, note?: string}>, end: string, style: int, sprms: string}> */
    private array $paragraphs = [];

    /** @var array<int, array{0: int, 1: int, 2: string, 3: string}> style number → [built-in id, based on, name, paragraph properties] */
    private array $styles;

    /** @var array<int, list<array{bullet: bool, start: int}>> list id → levels */
    private array $lists = [];

    /** @var list<array{list: int, starts: array<int, int>}> */
    private array $overrides = [];

    private bool $encrypted = false;

    public function __construct()
    {
        $this->styles = [
            0 => [0, 0x0FFF, 'Normal', ''],
            1 => [1, 0, 'heading 1', self::sprm(0x2640, 0)],
            2 => [2, 0, 'heading 2', self::sprm(0x2640, 1)],
        ];
    }

    /** A two-byte property code with its operand, as paragraph and character properties store it. */
    public static function sprm(int $code, int $value): string
    {
        $size = match ($code >> 13) {
            0, 1 => 1,
            2, 4, 5 => 2,
            7 => 3,
            default => 4,
        };

        return pack('v', $code).substr(pack('V', $value), 0, $size);
    }

    /**
     * A paragraph. A run is plain text, ['deleted' => text] for a tracked
     * deletion, or ['note' => text] for a footnote referenced there.
     *
     * @param  string|list<string|array{deleted?: string, note?: string}>  $runs
     */
    public function paragraph(string|array $runs, int $style = 0, string $sprms = '', string $end = "\r"): self
    {
        $this->paragraphs[] = ['runs' => Arr::wrap($runs), 'end' => $end, 'style' => $style, 'sprms' => $sprms];

        return $this;
    }

    public function style(int $number, int $builtIn, int $basedOn, string $name, string $sprms = ''): self
    {
        $this->styles[$number] = [$builtIn, $basedOn, $name, $sprms];

        return $this;
    }

    /**
     * A list definition; one level makes a simple list.
     *
     * @param  list<array{bullet: bool, start: int}>  $levels
     */
    public function listDefinition(int $id, array $levels): self
    {
        $this->lists[$id] = $levels;

        return $this;
    }

    /**
     * A list override paragraphs point to, optionally restarting a level.
     * Returns its number.
     *
     * @param  array<int, int>  $starts  level → start number
     */
    public function listOverride(int $list, array $starts = []): int
    {
        $this->overrides[] = ['list' => $list, 'starts' => $starts];

        return count($this->overrides);
    }

    public function item(string $text, int $override, int $level = 0): self
    {
        return $this->paragraph($text, 0, self::sprm(0x460B, $override).self::sprm(0x260A, $level));
    }

    /**
     * A table row: one paragraph per cell, then the row's end mark.
     *
     * @param  list<string>  $cells
     */
    public function row(array $cells): self
    {
        foreach ($cells as $cell) {
            $this->paragraph($cell, 0, self::sprm(0x2416, 1), "\x07");
        }

        return $this->paragraph('', 0, self::sprm(0x2416, 1).self::sprm(0x2417, 1), "\x07");
    }

    public function encrypted(): self
    {
        $this->encrypted = true;

        return $this;
    }

    /** Writes the document to a temporary file and returns its path. */
    public function save(): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'doc');
        file_put_contents($path, CompoundFileWriter::write($this->streams()));

        return $path;
    }

    /**
     * A Word 6 or Word 95 file: 8-bit text in the code page of its language,
     * saved in full (no piece table).
     */
    public static function word6(string $text, int $languageId, string $encoding): string
    {
        $bytes = (string) iconv('UTF-8', $encoding, $text);
        $header = Str::repeat("\0", 0x300);
        $put = static function (int $offset, string $value) use (&$header): void {
            $header = substr_replace($header, $value, $offset, strlen($value));
        };

        $put(0, pack('vvvv', 0xA5DC, 101, 0, $languageId));
        $put(0x18, pack('VV', 0x300, 0x300 + strlen($bytes)));
        $put(0x34, pack('V', strlen($bytes)));

        // str_pad, not Str::padRight: these are bytes, and mb_str_pad counts characters.
        $path = (string) tempnam(sys_get_temp_dir(), 'doc');
        file_put_contents($path, CompoundFileWriter::write(['WordDocument' => str_pad($header.$bytes, 4096, "\0")]));

        return $path;
    }

    /**
     * @return array<string, string>
     */
    private function streams(): array
    {
        // The body, then the footnotes, as one run of characters.
        $text = '';
        $marks = [];
        $deleted = [];
        $references = [];
        $notes = [];
        $starts = [];

        foreach ($this->paragraphs as $index => $paragraph) {
            $starts[$index] = $this->length($text);

            foreach ($paragraph['runs'] as $run) {
                if (! is_array($run)) {
                    $text .= $run;
                } elseif (isset($run['deleted'])) {
                    $deleted[] = [$this->length($text), $this->length($text) + $this->length($run['deleted'])];
                    $text .= $run['deleted'];
                } else {
                    $references[] = $this->length($text);
                    $notes[] = (string) $run['note'];
                    $text .= "\x02";
                }
            }

            $marks[$index] = $this->length($text);
            $text .= $paragraph['end'];
        }

        $bodyLength = $this->length($text);
        $noteStarts = [0];

        foreach ($notes as $note) {
            $text .= "\x02".$note."\r";
            $noteStarts[] = $this->length($text) - $bodyLength;
        }

        $noteLength = $this->length($text) - $bodyLength;
        $text .= "\r";

        // Two pieces split between paragraphs, stored second one first with stale text between them.
        $units = array_values((array) unpack('v*', mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')));
        $split = $starts[intdiv(count($starts), 2)] ?? 0;
        $first = array_slice($units, 0, $split);
        $second = array_slice($units, $split);
        $firstIsAscii = max([0, ...$first]) < 0x80;

        $word = Str::repeat("\0", self::TEXT_START);
        $secondAt = strlen($word);
        $word .= pack('v*', ...$second);
        $word .= mb_convert_encoding(self::STALE, 'UTF-16LE', 'UTF-8');
        $firstAt = strlen($word);
        $word .= $firstIsAscii ? pack('C*', ...$first) : pack('v*', ...$first);

        $fc = static fn (int $cp): int => $cp < $split ? $firstAt + $cp * ($firstIsAscii ? 1 : 2) : $secondAt + ($cp - $split) * 2;
        $width = static fn (int $cp): int => $cp < $split && $firstIsAscii ? 1 : 2;

        $table = '';
        $parts = [];
        $addPart = static function (int $index, string $bytes) use (&$table, &$parts): void {
            $parts[$index] = [strlen($table), strlen($bytes)];
            $table .= $bytes;
        };

        // The piece table.
        $pieces = pack('VVV', 0, $split, count($units))
            .pack('vVv', 0, $firstIsAscii ? ($firstAt * 2) | 0x40000000 : $firstAt, 0)
            .pack('vVv', 0, $secondAt, 0);
        $addPart(33, "\x02".pack('V', strlen($pieces)).$pieces);

        // Paragraph properties: one run per paragraph mark.
        $paragraphRuns = [];

        foreach ($this->paragraphs as $index => $paragraph) {
            $paragraphRuns[] = [$fc($marks[$index]), $fc($marks[$index]) + $width($marks[$index]), pack('v', $paragraph['style']).$paragraph['sprms']];
        }

        [$word, $bins] = $this->formattingPages($word, $paragraphRuns, true);
        $addPart(13, $bins);

        // Character properties: the tracked deletions.
        $deletions = array_map(static fn (array $range): array => [$fc($range[0]), $fc($range[1]), self::sprm(0x0800, 1)], $deleted);
        [$word, $bins] = $this->formattingPages($word, $deletions, false);
        $addPart(12, $bins);

        $addPart(1, $this->styleSheet());

        if ($references !== []) {
            $addPart(2, pack('V*', ...[...$references, $bodyLength]).Str::repeat(pack('v', 1), count($references)));
            $addPart(3, pack('V*', ...[...$noteStarts, $noteLength + 1]));
        }

        [$lists, $levels] = $this->listTables();

        if ($lists !== '') {
            $addPart(73, $lists);
            $table .= $levels;
            $addPart(74, $this->overrideTable());
        }

        $flags = 0x0004 | 0x0200 | ($this->encrypted ? 0x0100 : 0);
        $header = pack('vvvvvv', 0xA5EC, 0x00C1, 0, 0x0409, 0, $flags).Str::repeat("\0", 20)
            .pack('v', 14).Str::repeat("\0", 28)
            .pack('v', 22).pack('V*', strlen($word), 0, 0, $bodyLength, $noteLength, 0, 0, 0, 0, 0, 0, ...array_fill(0, 11, 0))
            .pack('v', 93);

        for ($index = 0; $index < 93; $index++) {
            $header .= pack('VV', ...($parts[$index] ?? [0, 0]));
        }

        $word = substr_replace($word, $header.pack('v', 0), 0, strlen($header) + 2);

        return ['WordDocument' => str_pad($word, 4096, "\0"), '1Table' => $table];
    }

    /**
     * Appends 512-byte formatting pages for runs of properties to the
     * stream (the gaps between runs get no properties) and gives the table
     * that points to them.
     *
     * @param  list<array{0: int, 1: int, 2: string}>  $runs
     * @return array{0: string, 1: string}
     */
    private function formattingPages(string $word, array $runs, bool $paragraphs): array
    {
        if ($runs === []) {
            return [$word, ''];
        }

        usort($runs, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $filled = [];
        $at = $runs[0][0];

        foreach ($runs as [$start, $end, $properties]) {
            if ($start > $at) {
                $filled[] = [$at, $start, null];
            }

            $filled[] = [$start, $end, $properties];
            $at = $end;
        }

        $boundaries = [];
        $pages = [];

        foreach (array_chunk($filled, 8) as $chunk) {
            $count = count($chunk);
            $page = Str::repeat("\0", 512);
            $offsets = pack('V*', ...[...array_column($chunk, 0), $chunk[$count - 1][1]]);
            $page = substr_replace($page, $offsets, 0, strlen($offsets));
            $free = 511;

            foreach ($chunk as $index => [, , $properties]) {
                $offset = 0;

                if ($properties !== null) {
                    $data = $paragraphs
                        ? (strlen($properties) % 2 === 1 ? chr(intdiv(strlen($properties) + 1, 2)).$properties : "\0".chr(intdiv(strlen($properties), 2)).$properties)
                        : chr(strlen($properties)).$properties;
                    $free = ($free - strlen($data)) & ~1;
                    $page = substr_replace($page, $data, $free, strlen($data));
                    $offset = intdiv($free, 2);
                }

                $entry = $paragraphs ? chr($offset).Str::repeat("\0", 12) : chr($offset);
                $page = substr_replace($page, $entry, ($count + 1) * 4 + $index * strlen($entry), strlen($entry));
            }

            $page[511] = chr($count);
            $word = str_pad($word, (int) ceil(strlen($word) / 512) * 512, "\0");
            $pages[] = intdiv(strlen($word), 512);
            $boundaries[] = $chunk[0][0];
            $word .= $page;
        }

        $boundaries[] = $filled[count($filled) - 1][1];

        return [$word, pack('V*', ...[...$boundaries, ...$pages])];
    }

    private function styleSheet(): string
    {
        $count = max(array_keys($this->styles)) + 1;
        $sheet = pack('v', 18).pack('vv', $count, 10).Str::repeat("\0", 14);

        for ($number = 0; $number < $count; $number++) {
            if (! isset($this->styles[$number])) {
                $sheet .= pack('v', 0);

                continue;
            }

            [$builtIn, $basedOn, $name, $sprms] = $this->styles[$number];
            $utf16 = mb_convert_encoding($name, 'UTF-16LE', 'UTF-8');
            $paragraph = pack('v', $number).$sprms;
            $style = pack('vvvvv', $builtIn, 1 | ($basedOn << 4), 2 | ($number << 4), 0, 0)
                .pack('v', intdiv(strlen($utf16), 2)).$utf16."\0\0"
                .pack('v', strlen($paragraph)).$paragraph.(strlen($paragraph) % 2 === 1 ? "\0" : '')
                .pack('v', 0);
            $sheet .= pack('v', strlen($style)).$style;
        }

        return $sheet;
    }

    /**
     * The list definitions, and the levels that follow them.
     *
     * @return array{0: string, 1: string}
     */
    private function listTables(): array
    {
        if ($this->lists === []) {
            return ['', ''];
        }

        $definitions = pack('v', count($this->lists));
        $levels = '';

        foreach ($this->lists as $id => $listLevels) {
            $definitions .= pack('VV', $id, 0).Str::repeat(pack('v', 0x0FFF), 9).chr(count($listLevels) === 1 ? 1 : 0)."\0";

            foreach (array_pad($listLevels, count($listLevels) === 1 ? 1 : 9, ['bullet' => true, 'start' => 1]) as $level) {
                $levels .= pack('V', $level['start']).chr($level['bullet'] ? 23 : 0).Str::repeat("\0", 23).pack('v', 0);
            }
        }

        return [$definitions, $levels];
    }

    private function overrideTable(): string
    {
        $table = pack('V', count($this->overrides));
        $data = '';

        foreach ($this->overrides as $override) {
            $table .= pack('V', $override['list']).Str::repeat("\0", 8).chr(count($override['starts']))."\0\0\0";
            $data .= pack('V', 0);

            foreach ($override['starts'] as $level => $start) {
                $data .= pack('VV', $start, $level | 0x10);
            }
        }

        return $table.$data;
    }

    /** A length in UTF-16 code units, as Word counts characters. */
    private function length(string $text): int
    {
        return intdiv(strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2);
    }
}
