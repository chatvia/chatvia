<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services\Chunking\Splitting;

/**
 * Splits Markdown (crawled web pages, .md files, ebooks) by its structure:
 * one section per heading, small neighbouring sections packed together, and
 * a section too big for one chunk cut between its blocks (paragraphs,
 * lists, code blocks, tables). A code block or table that fits is never cut;
 * one that does not keeps its fences, or its header row, in every part.
 * Every chunk starts with the headings above it, so a chunk from the middle
 * of a long section still says what it is about. Headings inside code blocks
 * are not headings.
 */
final readonly class MarkdownSplitter
{
    private const string FENCE = '/^ {0,3}(`{3,}|~{3,})/';

    private const string ATX_HEADING = '/^ {0,3}(#{1,6})(?:[ \t]+(.*?))?(?:[ \t]+#+)?[ \t]*$/';

    private const string SETEXT_UNDERLINE = '/^ {0,3}(=+|-+)[ \t]*$/';

    private const string THEMATIC_BREAK = '/^ {0,3}([-*_])(?:[ \t]*\1){2,}[ \t]*$/';

    private const string LIST_ITEM = '/^ {0,3}(?:[-+*]|\d{1,9}[.)])(?:[ \t]|$)/';

    private const string TABLE_DELIMITER = '/^ {0,3}\|?[ \t]*:?-+:?[ \t]*(?:\|[ \t]*:?-+:?[ \t]*)*\|?[ \t]*$/';

    public function __construct(
        private RecursiveSplitter $splitter,
    ) {}

    /**
     * @return list<string> the non-empty chunks, trimmed, in reading order
     */
    public function split(string $text): array
    {
        $sections = $this->sections($this->blocks(str_replace(["\r\n", "\r"], "\n", $text)));
        $chunks = [];
        $group = [];
        $groupLength = 0;
        $prefix = '';

        foreach ($sections as $section) {
            $body = $this->sectionText($section);
            $length = $this->length($body."\n\n");

            if ($group !== [] && $this->length($prefix) + $groupLength + $length <= $this->splitter->size) {
                $group[] = $section;
                $groupLength += $length;

                continue;
            }

            // A group of bare headings followed by more content is left out:
            // the headings come back above the chunks that follow.
            if ($group !== [] && ! $this->onlyHeadings($group)) {
                $chunks[] = $this->groupChunk($prefix, $group);
            }

            $group = [];
            $prefix = $this->prefix($section['path']);

            if ($this->length($prefix) + $length <= $this->splitter->size) {
                $group = [$section];
                $groupLength = $length;

                continue;
            }

            array_push($chunks, ...$this->splitSection($section));
        }

        if ($group !== []) {
            $chunks[] = $this->groupChunk($prefix, $group);
        }

        return RecursiveSplitter::trimmed($chunks);
    }

    // --- Reading the Markdown ------------------------------------------------

    /**
     * The blocks of the text, in order: headings, fenced code blocks, tables
     * and text (paragraphs, lists, quotes, rules).
     *
     * @return list<array{type: 'heading'|'code'|'table'|'text', lines: list<string>, level?: int, title?: string}>
     */
    private function blocks(string $text): array
    {
        $lines = explode("\n", $text);
        $count = count($lines);
        $blocks = [];
        $paragraph = [];

        for ($i = 0; $i < $count; $i++) {
            $line = $lines[$i];

            if (preg_match(self::FENCE, $line, $fence) === 1) {
                $blocks = $this->withText($blocks, $paragraph);
                $paragraph = [];
                $code = [$line];

                while (++$i < $count) {
                    $code[] = $lines[$i];

                    if ($this->closesFence($lines[$i], $fence[1])) {
                        break;
                    }
                }

                $blocks[] = ['type' => 'code', 'lines' => $code];

                continue;
            }

            if (preg_match(self::ATX_HEADING, $line, $heading) === 1) {
                $blocks = $this->withText($blocks, $paragraph);
                $paragraph = [];
                $blocks[] = ['type' => 'heading', 'lines' => [$line], 'level' => strlen($heading[1]), 'title' => trim($heading[2] ?? '')];

                continue;
            }

            if (trim($line) === '') {
                $blocks = $this->withText($blocks, $paragraph);
                $paragraph = [];

                continue;
            }

            // A line of = or - under a paragraph (not a list) makes it a heading.
            if ($paragraph !== [] && preg_match(self::SETEXT_UNDERLINE, $line, $underline) === 1 && preg_match(self::LIST_ITEM, $paragraph[0]) !== 1) {
                $blocks[] = [
                    'type' => 'heading',
                    'lines' => [...$paragraph, $line],
                    'level' => $underline[1][0] === '=' ? 1 : 2,
                    'title' => implode(' ', array_map(trim(...), $paragraph)),
                ];
                $paragraph = [];

                continue;
            }

            if (preg_match(self::THEMATIC_BREAK, $line) === 1) {
                $blocks = $this->withText($blocks, $paragraph);
                $paragraph = [];
                $blocks[] = ['type' => 'text', 'lines' => [$line]];

                continue;
            }

            if (str_contains($line, '|') && isset($lines[$i + 1]) && str_contains($lines[$i + 1], '|') && preg_match(self::TABLE_DELIMITER, $lines[$i + 1]) === 1) {
                $blocks = $this->withText($blocks, $paragraph);
                $paragraph = [];
                $table = [$line, $lines[++$i]];

                while (isset($lines[$i + 1]) && trim($lines[$i + 1]) !== '' && str_contains($lines[$i + 1], '|')) {
                    $table[] = $lines[++$i];
                }

                $blocks[] = ['type' => 'table', 'lines' => $table];

                continue;
            }

            $paragraph[] = $line;
        }

        return $this->withText($blocks, $paragraph);
    }

    /**
     * The sections of the blocks: each heading with the blocks under it,
     * and the headings above it (as "## Title" lines). Text before the first
     * heading is a section without one.
     *
     * @param  list<array{type: 'heading'|'code'|'table'|'text', lines: list<string>, level?: int, title?: string}>  $blocks
     * @return list<array{path: list<string>, heading: ?string, source: string, blocks: list<array{type: 'heading'|'code'|'table'|'text', lines: list<string>, level?: int, title?: string}>}>
     */
    private function sections(array $blocks): array
    {
        $sections = [];
        $path = [];
        $current = ['path' => [], 'heading' => null, 'source' => '', 'blocks' => []];

        foreach ($blocks as $block) {
            if ($block['type'] !== 'heading') {
                $current['blocks'][] = $block;

                continue;
            }

            if ($current['heading'] !== null || $current['blocks'] !== []) {
                $sections[] = $current;
            }

            $level = $block['level'] ?? 1;
            $path = array_filter($path, static fn (int $above): bool => $above < $level, ARRAY_FILTER_USE_KEY);
            $heading = rtrim(str_repeat('#', $level).' '.($block['title'] ?? ''));
            $current = ['path' => array_values($path), 'heading' => $heading, 'source' => implode("\n", $block['lines']), 'blocks' => []];
            $path[$level] = $heading;
        }

        if ($current['heading'] !== null || $current['blocks'] !== []) {
            $sections[] = $current;
        }

        return $sections;
    }

    // --- Cutting a section ---------------------------------------------------

    /**
     * A section too big for one chunk, cut between its blocks. Every part
     * starts with the section's heading and the headings above it.
     *
     * @param  array{path: list<string>, heading: ?string, source: string, blocks: list<array{type: 'heading'|'code'|'table'|'text', lines: list<string>, level?: int, title?: string}>}  $section
     * @return list<string>
     */
    private function splitSection(array $section): array
    {
        if ($section['blocks'] === []) {
            return $this->splitter->split($section['source'], [PatternSeparator::words()]);
        }

        $prefix = $this->prefix(array_values(array_filter([...$section['path'], $section['heading']], is_string(...))));
        $splitter = $this->splitter->withSize($this->splitter->size - $this->length($prefix));
        $pieces = [];

        foreach ($section['blocks'] as $block) {
            foreach ($this->blockParts($block, $splitter) as $part) {
                $pieces[] = [$part."\n\n", $this->length($part."\n\n")];
            }
        }

        $chunks = [];

        foreach ($splitter->pack($pieces) as $chunk) {
            if (trim($chunk) !== '') {
                $chunks[] = $prefix.trim($chunk);
            }
        }

        return $chunks;
    }

    /**
     * A block as one part when it fits, else cut: a code block by lines with
     * its fences in every part, a table by rows with its header in every
     * part, text by lines, sentences and words.
     *
     * @param  array{type: 'heading'|'code'|'table'|'text', lines: list<string>, level?: int, title?: string}  $block
     * @return list<string>
     */
    private function blockParts(array $block, RecursiveSplitter $splitter): array
    {
        $text = implode("\n", $block['lines']);
        $length = $this->length($text);

        if ($length <= $splitter->size) {
            return [$text];
        }

        return match ($block['type']) {
            'code' => $this->codeParts($block['lines'], $splitter),
            'table' => $this->tableParts($block['lines'], $splitter),
            default => $splitter->split($text, [PatternSeparator::lines(), new SentenceSeparator, PatternSeparator::words()]),
        };
    }

    /**
     * @param  list<string>  $lines  the opening fence, the code and, when closed, the closing fence
     * @return list<string>
     */
    private function codeParts(array $lines, RecursiveSplitter $splitter): array
    {
        $open = $lines[0];
        preg_match(self::FENCE, $open, $fence);
        $marker = $fence[1] ?? '```';
        $closed = count($lines) > 1 && $this->closesFence($lines[count($lines) - 1], $marker);
        $close = $closed ? trim($lines[count($lines) - 1]) : $marker;
        $code = implode("\n", array_slice($lines, 1, $closed ? -1 : null));
        $room = $splitter->size - $this->length($open."\n\n".$close);

        if ($room < 1) {
            return $splitter->split(implode("\n", $lines), [PatternSeparator::lines(), PatternSeparator::words()]);
        }

        $parts = [];

        foreach ($splitter->withSize($room)->chunks($code, [PatternSeparator::lines(), PatternSeparator::words()]) as $part) {
            if (trim($part) !== '') {
                $parts[] = $open."\n".rtrim($part, "\n")."\n".$close;
            }
        }

        return $parts;
    }

    /**
     * @param  list<string>  $lines  the header row, the delimiter row and the body rows
     * @return list<string>
     */
    private function tableParts(array $lines, RecursiveSplitter $splitter): array
    {
        $header = $lines[0]."\n".$lines[1];
        $room = $splitter->size - $this->length($header."\n");
        $rows = array_slice($lines, 2);

        if ($room < 1 || $rows === []) {
            return $splitter->split(implode("\n", $lines), [PatternSeparator::lines(), PatternSeparator::words()]);
        }

        $rowSplitter = $splitter->withSize($room);
        $pieces = [];

        foreach ($rows as $row) {
            $length = $this->length($row."\n");

            if ($length <= $room) {
                $pieces[] = [$row."\n", $length];

                continue;
            }

            foreach ($rowSplitter->split($row, [PatternSeparator::words()]) as $part) {
                $pieces[] = [$part."\n", $this->length($part."\n")];
            }
        }

        return array_map(
            static fn (string $chunk): string => $header."\n".rtrim($chunk, "\n"),
            $rowSplitter->pack($pieces),
        );
    }

    // --- Helpers -------------------------------------------------------------

    /**
     * The heading lines above a chunk, then a blank line. When they would
     * take more than half the size, only the closest heading is kept, and
     * none when even that one is too long.
     *
     * @param  list<string>  $headings
     */
    private function prefix(array $headings): string
    {
        if ($headings === []) {
            return '';
        }

        $half = intdiv($this->splitter->size, 2);
        $prefix = implode("\n", $headings)."\n\n";

        if ($this->length($prefix) <= $half) {
            return $prefix;
        }

        $closest = $headings[count($headings) - 1]."\n\n";

        return $this->length($closest) <= $half ? $closest : '';
    }

    /**
     * @param  list<array{path: list<string>, heading: ?string, source: string, blocks: list<array{type: 'heading'|'code'|'table'|'text', lines: list<string>, level?: int, title?: string}>}>  $group
     */
    private function groupChunk(string $prefix, array $group): string
    {
        return $prefix.implode("\n\n", array_map($this->sectionText(...), $group));
    }

    /**
     * @param  array{path: list<string>, heading: ?string, source: string, blocks: list<array{type: 'heading'|'code'|'table'|'text', lines: list<string>, level?: int, title?: string}>}  $section
     */
    private function sectionText(array $section): string
    {
        $parts = array_map(static fn (array $block): string => implode("\n", $block['lines']), $section['blocks']);

        if ($section['heading'] !== null) {
            array_unshift($parts, $section['source']);
        }

        return implode("\n\n", $parts);
    }

    /**
     * @param  list<array{path: list<string>, heading: ?string, source: string, blocks: list<array{type: 'heading'|'code'|'table'|'text', lines: list<string>, level?: int, title?: string}>}>  $group
     */
    private function onlyHeadings(array $group): bool
    {
        return array_all($group, fn (array $section): bool => $section['blocks'] === []);
    }

    private function length(string $text): int
    {
        return $this->splitter->measure->length($text);
    }

    /**
     * @param  list<array{type: 'heading'|'code'|'table'|'text', lines: list<string>, level?: int, title?: string}>  $blocks
     * @param  list<string>  $paragraph
     * @return list<array{type: 'heading'|'code'|'table'|'text', lines: list<string>, level?: int, title?: string}>
     */
    private function withText(array $blocks, array $paragraph): array
    {
        if ($paragraph !== []) {
            $blocks[] = ['type' => 'text', 'lines' => $paragraph];
        }

        return $blocks;
    }

    /** Whether the line closes a code block opened with $marker: the same character, at least as many. */
    private function closesFence(string $line, string $marker): bool
    {
        return preg_match('/^ {0,3}'.preg_quote($marker[0], '/').'{'.strlen($marker).',}[ \t]*$/', $line) === 1;
    }
}
