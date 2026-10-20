<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion\Markdown;

/**
 * Writes the GitHub Markdown blocks the built-in readers share: headings,
 * paragraphs, list items, pipe tables and the footnote list. Text is kept
 * plain (no bold or italics); what matters for search is that words,
 * headings and table cells stay apart and in order.
 */
final class MarkdownText
{
    /** A list item is joined to the item before it by a single line break. */
    public const string ITEM = 'item';

    /** The first item of a new list: a blank line keeps it from joining the list before. */
    public const string LIST_START = 'list';

    public const string BLOCK = 'block';

    /**
     * Tidies the text of one paragraph: soft hyphens out, runs of spaces and
     * tabs made one space, each line trimmed, and a `<` that would open an
     * HTML tag escaped, so placeholder text like "<date>" stays text.
     */
    public static function inline(string $text): string
    {
        $text = str_replace(["\u{00AD}", "\r\n", "\r"], ['', "\n", "\n"], $text);
        $text = (string) preg_replace('/<(?=[A-Za-z\/!?])/', '\\<', $text);
        $text = (string) preg_replace('/[ \t]+/u', ' ', $text);
        $lines = array_map(trim(...), explode("\n", $text));

        return trim(implode("\n", array_filter($lines, static fn (string $line): bool => $line !== '')));
    }

    public static function heading(int $level, string $text): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        return $text === '' ? '' : str_repeat('#', max(1, min(6, $level))).' '.$text;
    }

    /**
     * A paragraph. A line that starts like a heading or a list item ("#",
     * "1.", "-") is escaped, so it stays a plain line of text.
     */
    public static function paragraph(string $text): string
    {
        return (string) preg_replace(['/^(\s*)#/m', '/^(\s*\d+)([.)])(?=\s|$)/m', '/^(\s*)([-+*])(?=\s)/m'], ['$1\\\\#', '$1\\\\$2', '$1\\\\$2'], $text);
    }

    /** One list item, indented four spaces per level so any marker nests. */
    public static function item(int $level, string $marker, string $text): string
    {
        $indent = str_repeat(' ', 4 * max(0, $level));

        return $indent.$marker.' '.str_replace("\n", "\n".$indent.str_repeat(' ', mb_strlen($marker) + 1), self::paragraph($text));
    }

    /**
     * A pipe table; the first row is the header. Rows are padded to the
     * widest row; columns empty in every row are dropped.
     *
     * @param  list<list<string>>  $rows
     */
    public static function table(array $rows): string
    {
        $rows = array_values(array_filter($rows, static fn (array $row): bool => implode('', $row) !== ''));

        if ($rows === []) {
            return '';
        }

        $width = max(array_map(count(...), $rows));
        $rows = array_map(static fn (array $row): array => array_pad($row, $width, ''), $rows);
        $used = array_values(array_filter(range(0, $width - 1), static fn (int $column): bool => implode('', array_column($rows, $column)) !== ''));

        $lines = [];

        foreach ($rows as $number => $row) {
            $cells = array_map(static fn (int $column): string => self::cell($row[$column]), $used);
            $lines[] = '| '.implode(' | ', $cells).' |';

            if ($number === 0) {
                $lines[] = '|'.str_repeat(' --- |', count($used));
            }
        }

        return implode("\n", $lines);
    }

    /**
     * A table as blocks: a pipe table, or, for a one-column table (almost
     * always a box drawn around text), each line of it as a paragraph.
     *
     * @param  list<list<string>>  $rows
     * @return list<array{0: string, 1: string}>
     */
    public static function tableBlocks(array $rows): array
    {
        if (max([0, ...array_map(count(...), $rows)]) > 1) {
            return [[self::BLOCK, self::table($rows)]];
        }

        $blocks = [];

        foreach (explode("\n", implode("\n", array_merge(...[[], ...$rows]))) as $line) {
            if (filled($line)) {
                $blocks[] = [self::BLOCK, self::paragraph(trim($line))];
            }
        }

        return $blocks;
    }

    /**
     * Joins blocks: the items of one list one line apart, everything else
     * a blank line apart.
     *
     * @param  list<array{0: string, 1: string}>  $blocks  [kind, markdown]
     */
    public static function join(array $blocks): string
    {
        $out = '';
        $previous = null;

        foreach ($blocks as [$kind, $markdown]) {
            if ($markdown === '') {
                continue;
            }

            if ($previous !== null) {
                $out .= $kind === self::ITEM && in_array($previous, [self::ITEM, self::LIST_START], true) ? "\n" : "\n\n";
            }

            $out .= $markdown;
            $previous = $kind;
        }

        return $out;
    }

    private static function cell(string $text): string
    {
        $text = trim((string) preg_replace('/\s*\n\s*/u', '<br>', trim($text)));

        return str_replace('|', '\\|', $text);
    }
}
