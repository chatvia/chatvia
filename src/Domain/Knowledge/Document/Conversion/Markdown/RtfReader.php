<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion\Markdown;

use RuntimeException;

/**
 * Reads a Rich Text Format file (.rtf) as GitHub Markdown with no pandoc on
 * the server.
 *
 * RTF is plain text with control words (`\par`, `\'e9`, `\u8226`) and
 * groups in braces. The reader walks it once, keeping each group's state,
 * and gives:
 *
 *  - headings from the outline level of the paragraph or its style, or a
 *    style named "heading 1"… or "Title";
 *  - list items from the number or bullet the file writes before each one
 *    (`\listtext`), nested by level;
 *  - tables as pipe tables (a table inside a cell is read into the cell;
 *    a one-column table, a box around text, is read as paragraphs);
 *  - footnotes and endnotes as `[^1]` marks, listed at the end;
 *  - the text of fields (the visible link text, not the link) and of text
 *    boxes;
 *  - hidden text, tracked deletions, pictures, embedded objects, comments,
 *    headers and footers left out.
 *
 * Text in 8-bit code pages is decoded by the code page of its font (or of
 * the document), so a Russian or Japanese file reads as such.
 */
final class RtfReader
{
    /** Largest file read; pictures make RTF files large. */
    private const int MAX_BYTES = 100 * 1024 * 1024;

    /** Groups whose content is not text of the document. */
    private const array SKIPPED = [
        'colortbl', 'info', 'pict', 'nonshppict', 'shprslt', 'sp', 'sn', 'sv', 'header', 'headerl', 'headerr',
        'headerf', 'footer', 'footerl', 'footerr', 'footerf', 'fldinst', 'annotation', 'atnid', 'atnauthor',
        'atndate', 'atnref', 'atntime', 'atrfstart', 'atrfend', 'xe', 'tc', 'tcn', 'txe', 'rxe', 'ftnsep',
        'ftnsepc', 'ftncn', 'aftnsep', 'aftnsepc', 'aftncn', 'objdata', 'objclass', 'objname', 'nonesttables',
        'template', 'private', 'pn', 'pntxta', 'pntxtb', 'datafield', 'bkmkstart', 'bkmkend', 'fldtype',
    ];

    /** Groups starting with `\*` that hold text; any other such group is skipped. */
    private const array STARRED_TEXT = ['footnote', 'shpinst', 'shptxt', 'ud', 'fldrslt'];

    /** Control words that stand for a character. */
    private const array CHARACTERS = [
        'tab' => "\t", 'line' => "\n", 'softline' => "\n", 'emdash' => '—', 'endash' => '–', 'emspace' => ' ',
        'enspace' => ' ', 'qmspace' => ' ', 'bullet' => '•', 'lquote' => '‘', 'rquote' => '’',
        'ldblquote' => '“', 'rdblquote' => '”', 'zwj' => '', 'zwnj' => '', 'ltrmark' => '', 'rtlmark' => '',
        'chftn' => '', 'chdate' => '', 'chtime' => '', 'chpgn' => '', 'chatn' => '', 'sectnum' => '',
    ];

    /** Font character set → code page (RTF spec, `\fcharset`). */
    private const array CHARSETS = [
        0 => 0, 1 => 0, 2 => 1252, 77 => 10000, 128 => 932, 129 => 949, 130 => 1361, 134 => 936, 136 => 950,
        161 => 1253, 162 => 1254, 163 => 1258, 177 => 1255, 178 => 1256, 186 => 1257, 204 => 1251,
        222 => 874, 238 => 1250, 254 => 437, 255 => 850,
    ];

    private string $rtf = '';

    private int $position = 0;

    private int $length = 0;

    /** The code page of the document (`\ansicpg`), for text in a font without one. */
    private int $codePage = 1252;

    /** @var array<int, int> font number → code page (0: the document's) */
    private array $fonts = [];

    private int $defaultFont = 0;

    /** @var array<int, array{base: ?int, name: string, outline: ?int}> style number → style */
    private array $styles = [];

    /**
     * The state of the open groups; the last one is in effect.
     *
     * @var list<array{target: string, font: int, skip: int, hidden: bool, style: int, outline: ?int, table: bool, depth: int, list: bool, level: int}>
     */
    private array $groups = [];

    /** Undecoded bytes of `\'hh` escapes, decoded together (a double-byte character takes two). */
    private string $bytes = '';

    private int $pendingSurrogate = 0;

    /** The characters still to skip after a `\u` (its stand-in for old readers). */
    private int $skipCharacters = 0;

    private string $paragraph = '';

    private string $marker = '';

    private string $note = '';

    /** @var list<string> */
    private array $cell = [];

    /** @var list<string> */
    private array $row = [];

    /** @var list<list<string>> */
    private array $rows = [];

    /** @var list<array{0: string, 1: string}> */
    private array $blocks = [];

    /** @var array<int, int> list level → the last number seen, for lists whose numbers are not digits */
    private array $counters = [];

    private bool $inList = false;

    private NoteList $notes;

    public function toMarkdown(string $path): string
    {
        $size = @filesize($path);

        if ($size === false || $size > self::MAX_BYTES) {
            throw new RuntimeException('The RTF file is missing or too large to read.');
        }

        $this->rtf = (string) file_get_contents($path);

        if (! str_starts_with(ltrim($this->rtf), '{\\rtf')) {
            throw new RuntimeException('The file is not an RTF document.');
        }

        $this->reset();
        $this->parse();
        $this->endParagraph('par');
        $this->flushTable();

        $this->rtf = '';

        return trim(MarkdownText::join([...$this->blocks, [MarkdownText::BLOCK, $this->notes->toMarkdown()]]));
    }

    private function reset(): void
    {
        $this->position = 0;
        $this->length = strlen($this->rtf);
        $this->codePage = 1252;
        $this->fonts = $this->styles = $this->cell = $this->row = $this->rows = $this->blocks = $this->counters = [];
        $this->defaultFont = $this->pendingSurrogate = $this->skipCharacters = 0;
        $this->bytes = $this->paragraph = $this->marker = $this->note = '';
        $this->inList = false;
        $this->notes = new NoteList;
        $this->groups = [[
            'target' => 'body', 'font' => -1, 'skip' => 1, 'hidden' => false, 'style' => 0, 'outline' => null,
            'table' => false, 'depth' => 1, 'list' => false, 'level' => 0,
        ]];
    }

    private function parse(): void
    {
        $groupStart = false;
        $starred = false;

        while ($this->position < $this->length) {
            $char = $this->rtf[$this->position];

            if ($char === '{') {
                $this->position++;
                $this->flushBytes();
                $this->skipCharacters = 0;
                $this->groups[] = $this->state();
                $groupStart = true;
                $starred = false;

                continue;
            }

            if ($char === '}') {
                $this->position++;
                $this->skipCharacters = 0;
                $this->closeGroup();
                $groupStart = false;

                continue;
            }

            if ($char === '\\') {
                $wasStart = $groupStart;
                $groupStart = false;

                if ($this->controlSymbol()) {
                    $starred = $wasStart && $this->rtf[$this->position - 1] === '*';
                    $groupStart = $starred;

                    continue;
                }

                if (preg_match('/\G\\\\([a-zA-Z]{1,32})(-?\d{1,10})? ?/', $this->rtf, $match, 0, $this->position) !== 1) {
                    $this->position++;

                    continue;
                }

                $this->position += strlen($match[0]);
                $this->controlWord($match[1], isset($match[2]) ? (int) $match[2] : null, $wasStart, $starred && $wasStart);
                $starred = false;

                continue;
            }

            $groupStart = false;
            $starred = false;

            if ($char === "\r" || $char === "\n") {
                $this->position++;

                continue;
            }

            $run = strcspn($this->rtf, "\\{}\r\n", $this->position);
            $text = substr($this->rtf, $this->position, $run);
            $this->position += $run;

            if ($this->skipCharacters > 0) {
                $skipped = min($this->skipCharacters, strlen($text));
                $this->skipCharacters -= $skipped;
                $text = substr($text, $skipped);
            }

            if ($text !== '') {
                $this->bytes .= $text;
            }
        }
    }

    /**
     * A control symbol (`\'hh`, `\~`, `\{`…): handles it and returns true,
     * or returns false when the backslash starts a control word.
     */
    private function controlSymbol(): bool
    {
        $next = $this->rtf[$this->position + 1] ?? '';

        if (ctype_alpha($next)) {
            return false;
        }

        $this->position += 2;

        if ($next === "'") {
            $hex = substr($this->rtf, $this->position, 2);
            $this->position += 2;

            if ($this->skipCharacters > 0) {
                $this->skipCharacters--;
            } elseif (ctype_xdigit($hex)) {
                $this->bytes .= chr((int) hexdec($hex));
            }

            return true;
        }

        if ($this->skipCharacters > 0 && $next !== '*') {
            $this->skipCharacters--;

            return true;
        }

        match ($next) {
            '\\', '{', '}' => $this->bytes .= $next,
            '~' => $this->emit(' '),
            '_' => $this->emit('-'),
            "\n", "\r" => $this->endParagraph('par'),
            default => null,
        };

        return true;
    }

    private function controlWord(string $word, ?int $value, bool $groupStart, bool $starred): void
    {
        if ($this->skipCharacters > 0 && $word !== 'bin') {
            $this->skipCharacters--;

            return;
        }

        if ($groupStart && $this->destination($word, $starred)) {
            return;
        }

        $state = &$this->groups[array_key_last($this->groups)];

        switch ($word) {
            case 'par':
            case 'sect':
            case 'page':
            case 'cell':
            case 'row':
            case 'nestcell':
            case 'nestrow':
                $this->endParagraph($word);
                break;
            case 'pard':
                $this->flushBytes();
                $state['style'] = 0;
                $state['outline'] = null;
                $state['table'] = false;
                $state['depth'] = 1;
                $state['list'] = false;
                $state['level'] = 0;
                break;
            case 'plain':
                $this->flushBytes();
                $state['font'] = -1;
                $state['hidden'] = false;
                break;
            case 'intbl':
                $state['table'] = true;
                break;
            case 'itap':
                $state['depth'] = max(0, (int) $value);
                $state['table'] = $state['table'] || $value > 0;
                break;
            case 's':
                $state['style'] = (int) $value;
                break;
            case 'outlinelevel':
                $state['outline'] = (int) $value;
                break;
            case 'ls':
                $state['list'] = $value > 0;
                break;
            case 'ilvl':
                $state['level'] = max(0, min(8, (int) $value));
                break;
            case 'f':
                $this->flushBytes();
                $state['font'] = (int) $value;
                break;
            case 'v':
            case 'deleted':
                $this->flushBytes();
                $state['hidden'] = $value !== 0;
                break;
            case 'uc':
                $state['skip'] = max(0, (int) $value);
                break;
            case 'u':
                $this->unicode((int) $value);
                $this->skipCharacters = $state['skip'];
                break;
            case 'bin':
                $this->flushBytes();
                $this->position += max(0, (int) $value);
                break;
            case 'ansicpg':
                $this->codePage = (int) $value;
                break;
            case 'mac':
                $this->codePage = 10000;
                break;
            case 'pc':
                $this->codePage = 437;
                break;
            case 'pca':
                $this->codePage = 850;
                break;
            case 'deff':
                $this->defaultFont = (int) $value;
                break;
            default:
                if (isset(self::CHARACTERS[$word])) {
                    $this->emit(self::CHARACTERS[$word]);
                }
        }
    }

    /**
     * Handles a control word that opens a group: a table read on its own, a
     * group to skip, or one that sends its text elsewhere. Returns false for
     * an ordinary control word.
     */
    private function destination(string $word, bool $starred): bool
    {
        $state = &$this->groups[array_key_last($this->groups)];

        if ($word === 'fonttbl' || $word === 'stylesheet') {
            $start = $this->position;
            $this->skipGroup();
            $word === 'fonttbl' ? $this->readFonts(substr($this->rtf, $start, $this->position - $start)) : $this->readStyles(substr($this->rtf, $start, $this->position - $start));

            return true;
        }

        if (in_array($word, self::SKIPPED, true) || ($starred && ! in_array($word, self::STARRED_TEXT, true) && $word !== 'listtext' && $word !== 'pntext')) {
            $this->skipGroup();

            return true;
        }

        if ($word === 'listtext' || $word === 'pntext') {
            $this->flushBytes();
            $state['target'] = 'marker';
            $this->marker = '';

            return true;
        }

        if ($word === 'footnote') {
            $this->flushBytes();
            $state['target'] = 'note';
            $this->note = '';

            return true;
        }

        if ($word === 'upr') {
            // The text twice: as 8-bit text, then as Unicode in `\ud`; only the second is read.
            $state['target'] = 'none';

            return true;
        }

        if ($word === 'ud') {
            $state['target'] = $this->groups[count($this->groups) - 3]['target'] ?? 'body';

            return true;
        }

        return false;
    }

    /** Skips to the end of the current group, past its nested groups and any binary data. */
    private function skipGroup(): void
    {
        $depth = 1;

        while ($this->position < $this->length) {
            $this->position += strcspn($this->rtf, '\\{}', $this->position);
            $char = $this->rtf[$this->position] ?? '';

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                if (--$depth === 0) {
                    return;
                }
            } elseif ($char === '\\') {
                if (preg_match('/\G\\\\bin(\d{1,10}) ?/', $this->rtf, $match, 0, $this->position) === 1) {
                    $this->position += strlen($match[0]) + (int) $match[1];

                    continue;
                }

                $this->position++;
            }

            $this->position++;
        }
    }

    private function closeGroup(): void
    {
        $this->flushBytes();
        $closed = array_pop($this->groups);

        if ($this->groups === []) {
            // More closing braces than opening ones: keep a state to read on.
            $this->groups[] = $closed;

            return;
        }

        if ($closed['target'] === 'note' && $this->state()['target'] !== 'note') {
            $mark = $this->notes->add($this->note);
            $this->note = '';
            $this->appendTo($this->state()['target'], $mark);
        }

        if ($closed['target'] === 'marker' && $this->state()['target'] !== 'marker') {
            $this->inList = true;
        }
    }

    /**
     * @return array{target: string, font: int, skip: int, hidden: bool, style: int, outline: ?int, table: bool, depth: int, list: bool, level: int}
     */
    private function state(): array
    {
        return $this->groups[array_key_last($this->groups)];
    }

    private function emit(string $text): void
    {
        $this->flushBytes();
        $state = $this->state();

        if (! $state['hidden']) {
            $this->appendTo($state['target'], $text);
        }
    }

    private function appendTo(string $target, string $text): void
    {
        match ($target) {
            'body' => $this->paragraph .= $text,
            'note' => $this->note .= $text,
            'marker' => $this->marker .= $text,
            default => null,
        };
    }

    /** A `\u` character; a pair of surrogates makes one character. */
    private function unicode(int $value): void
    {
        $value = $value < 0 ? $value + 0x10000 : $value;

        if ($value >= 0xD800 && $value <= 0xDBFF) {
            $this->flushBytes();
            $this->pendingSurrogate = $value;

            return;
        }

        if ($value >= 0xDC00 && $value <= 0xDFFF) {
            if ($this->pendingSurrogate !== 0) {
                $this->emit((string) mb_chr(0x10000 + (($this->pendingSurrogate - 0xD800) << 10) + ($value - 0xDC00), 'UTF-8'));
            }

            $this->pendingSurrogate = 0;

            return;
        }

        $this->pendingSurrogate = 0;
        $this->emit(in_array($value, [0, 0xA0], true) ? ' ' : (string) mb_chr($value, 'UTF-8'));
    }

    /** Decodes the bytes gathered so far by the code page of the current font. */
    private function flushBytes(): void
    {
        if ($this->bytes === '') {
            return;
        }

        $bytes = $this->bytes;
        $this->bytes = '';
        $state = $this->state();

        if ($state['hidden'] || $state['target'] === 'none') {
            return;
        }

        $font = $state['font'] === -1 ? $this->defaultFont : $state['font'];
        $codePage = $this->fonts[$font] ?? 0;

        $this->appendTo($state['target'], str_replace("\u{A0}", ' ', CodePage::toUtf8($bytes, $codePage === 0 ? $this->codePage : $codePage)));
    }

    private function endParagraph(string $word): void
    {
        $this->flushBytes();
        $state = $this->state();

        if ($state['target'] === 'note') {
            $this->note .= "\n";

            return;
        }

        if ($state['target'] !== 'body') {
            return;
        }

        $text = $this->paragraph;
        $this->paragraph = '';
        $marker = trim($this->marker);
        $inList = $this->inList || $state['list'];
        $this->marker = '';
        $this->inList = false;

        if (in_array($word, ['nestcell', 'nestrow'], true) || ($state['table'] && $state['depth'] > 1)) {
            if ($word !== 'nestrow') {
                $this->cell[] = $text;
            }

            return;
        }

        if ($word === 'row') {
            $this->rows[] = $this->row;
            $this->row = $this->cell = [];

            return;
        }

        if ($word === 'cell' || $state['table']) {
            $this->cell[] = $text;

            if ($word === 'cell') {
                $this->row[] = MarkdownText::inline(implode("\n", $this->cell));
                $this->cell = [];
            }

            return;
        }

        $this->flushTable();
        $text = MarkdownText::inline($text);

        if ($text === '') {
            return;
        }

        $heading = $state['outline'] !== null ? ($state['outline'] < 9 ? $state['outline'] + 1 : null) : $this->styleHeading($state['style']);

        if ($heading !== null) {
            $this->blocks[] = [MarkdownText::BLOCK, MarkdownText::heading($heading, $text)];
            $this->counters = [];

            return;
        }

        if ($inList) {
            $this->blocks[] = [$this->listKind($state['level']), MarkdownText::item($state['level'], $this->listMarker($marker, $state['level']), $text)];

            return;
        }

        $this->counters = [];
        $this->blocks[] = [MarkdownText::BLOCK, MarkdownText::paragraph($text)];
    }

    /** The first item of a list starts it; the next ones join it. */
    private function listKind(int $level): string
    {
        $last = end($this->blocks);

        return $last !== false && in_array($last[0], [MarkdownText::ITEM, MarkdownText::LIST_START], true) || $level > 0 ? MarkdownText::ITEM : MarkdownText::LIST_START;
    }

    /** "-" for a bullet; the number the file shows (or the next one) for a numbered item. */
    private function listMarker(string $marker, int $level): string
    {
        $this->counters = array_filter($this->counters, static fn (int $deeper): bool => $deeper <= $level, ARRAY_FILTER_USE_KEY);

        if (preg_match('/(\d+)[.)]?\s*$/', $marker, $match) === 1) {
            $this->counters[$level] = (int) $match[1];

            return $match[1].'.';
        }

        if (preg_match('/^\(?([a-zA-Z]{1,4})[.)]$/', $marker) === 1) {
            $this->counters[$level] = ($this->counters[$level] ?? 0) + 1;

            return $this->counters[$level].'.';
        }

        return '-';
    }

    private function flushTable(): void
    {
        if ($this->cell !== [] && implode('', $this->cell) !== '') {
            $this->row[] = MarkdownText::inline(implode("\n", $this->cell));
        }

        if ($this->row !== []) {
            $this->rows[] = $this->row;
        }

        array_push($this->blocks, ...MarkdownText::tableBlocks($this->rows));

        $this->cell = $this->row = $this->rows = [];
    }

    /** The heading level a style gives, following the styles it is based on. */
    private function styleHeading(int $style): ?int
    {
        for ($depth = 0; $depth < 10 && isset($this->styles[$style]); $depth++) {
            $definition = $this->styles[$style];

            if ($definition['outline'] !== null) {
                return $definition['outline'] < 9 ? $definition['outline'] + 1 : null;
            }

            if (preg_match('/^heading\s*([1-9])$/i', $definition['name'], $match) === 1) {
                return (int) $match[1];
            }

            if (in_array(strtolower($definition['name']), ['title', 'toc heading'], true)) {
                return 1;
            }

            if ($definition['base'] === null || $definition['base'] === $style) {
                return null;
            }

            $style = $definition['base'];
        }

        return null;
    }

    /** The code page of each font, from its character set or its own `\cpg`. */
    private function readFonts(string $table): void
    {
        preg_match_all('/\\\\f(\d+)((?:(?!\\\\f\d).)*)/s', $table, $fonts, PREG_SET_ORDER);

        foreach ($fonts as [, $number, $body]) {
            $codePage = preg_match('/\\\\cpg(\d+)/', $body, $match) === 1 ? (int) $match[1] : null;

            if ($codePage === null && preg_match('/\\\\fcharset(\d+)/', $body, $match) === 1) {
                $codePage = self::CHARSETS[(int) $match[1]] ?? 0;
            }

            $this->fonts[(int) $number] = $codePage ?? 0;
        }
    }

    /** Each paragraph style's name, outline level and the style it is based on. */
    private function readStyles(string $sheet): void
    {
        $depth = 0;
        $start = 0;

        for ($index = 0, $length = strlen($sheet); $index < $length; $index++) {
            if ($sheet[$index] === '\\') {
                $index++;
            } elseif ($sheet[$index] === '{' && $depth++ === 0) {
                $start = $index;
            } elseif ($sheet[$index] === '}' && --$depth === 0) {
                $this->readStyle(substr($sheet, $start + 1, $index - $start - 1));
            }
        }
    }

    private function readStyle(string $style): void
    {
        if (str_starts_with($style, '\\*') || preg_match('/^\\\\s(\d+)/', ltrim($style), $number) !== 1 && preg_match('/\\\\s(\d+)(?![a-z])/', $style, $number) !== 1) {
            return;
        }

        do {
            $style = (string) preg_replace('/\{[^{}]*\}/', '', $style, -1, $count);
        } while ($count > 0);

        $outline = preg_match('/\\\\outlinelevel(\d+)/', $style, $match) === 1 ? (int) $match[1] : null;
        $base = preg_match('/\\\\sbasedon(\d+)/', $style, $match) === 1 ? (int) $match[1] : null;
        $name = trim((string) preg_replace(['/\\\\\'[0-9a-fA-F]{2}/', '/\\\\[a-zA-Z]+-?\d* ?/', '/\\\\./'], '', $style));

        $this->styles[(int) $number[1]] = ['base' => $base, 'name' => rtrim($name, '; '), 'outline' => $outline];
    }
}
