<?php

declare(strict_types=1);

namespace Tests\Eval\Support;

/**
 * Compares two Markdown readings of one document, pandoc's (the reference)
 * and a built-in reader's (the candidate), for the document reader eval:
 * how many words they share, whether the word order holds, and which
 * headings, tables, footnotes and word breaks the candidate lost.
 *
 * Markup is stripped before words are compared, so `**bold**` against
 * `bold`, or an HTML table against a pipe table, costs nothing; only the
 * text and the structure counts below differ.
 */
final readonly class MarkdownComparison
{
    /** HTML tags that end a word; any other tag (a span, a bookmark) may sit inside one. */
    private const string BLOCK_TAGS = 'p|div|br|td|th|tr|table|thead|tbody|li|ul|ol|h[1-6]|figure|figcaption|blockquote|section|aside|col|colgroup|dl|dt|dd|hr|pre';

    /** @var list<string> */
    private array $referenceWords;

    /** @var list<string> */
    private array $candidateWords;

    public function __construct(
        public string $reference,
        public string $candidate,
    ) {
        $this->referenceWords = self::words($reference);
        $this->candidateWords = self::words($candidate);
    }

    /**
     * Every measure of the pair.
     *
     * @return array{
     *     words: array{int, int},
     *     recall: float,
     *     precision: float,
     *     order: float,
     *     headings: array{int, int},
     *     headings_lost: list<string>,
     *     headings_extra: list<string>,
     *     tables: array{int, int},
     *     rows: array{int, int},
     *     footnotes: array{int, int},
     *     run_together: list<string>,
     *     split_apart: list<string>,
     *     missing: list<string>,
     *     extra: list<string>,
     * }
     */
    public function metrics(): array
    {
        $common = $this->shared($this->referenceWords, $this->candidateWords);
        $referenceHeadings = self::headings($this->reference);
        $candidateHeadings = self::headings($this->candidate);
        $referenceTables = self::tables($this->reference);
        $candidateTables = self::tables($this->candidate);

        return [
            'words' => [count($this->referenceWords), count($this->candidateWords)],
            'recall' => $this->referenceWords === [] ? 1.0 : $common / count($this->referenceWords),
            'precision' => $this->candidateWords === [] ? 1.0 : $common / count($this->candidateWords),
            'order' => $this->trigramOverlap($this->referenceWords, $this->candidateWords),
            'headings' => [count($referenceHeadings), count($candidateHeadings)],
            'headings_lost' => $this->difference($referenceHeadings, $candidateHeadings),
            'headings_extra' => $this->difference($candidateHeadings, $referenceHeadings),
            'tables' => [$referenceTables['tables'], $candidateTables['tables']],
            'rows' => [$referenceTables['rows'], $candidateTables['rows']],
            'footnotes' => [self::footnotes($this->reference), self::footnotes($this->candidate)],
            'run_together' => self::runTogether($this->referenceWords, $this->candidateWords),
            'split_apart' => self::runTogether($this->candidateWords, $this->referenceWords),
            'missing' => array_slice($this->difference($this->referenceWords, $this->candidateWords), 0, 12),
            'extra' => array_slice($this->difference($this->candidateWords, $this->referenceWords), 0, 12),
        ];
    }

    /** The text with Markdown and HTML markup taken out. */
    public static function plainText(string $markdown): string
    {
        // Links inside links (pandoc's contents entries) come off one layer at a time.
        for ($layer = 0; $layer < 3; $layer++) {
            $markdown = (string) preg_replace('/(?<!!)\[([^\[\]]*)\]\([^)]*\)/', '$1', $markdown);
        }

        $text = (string) preg_replace([
            '/^\[\^[^\]]+\]:[ \t]*/m',     // footnote labels (the note text stays)
            '/\[\^[^\]]+\]/',              // footnote marks
            '/!\[[^\]]*\]\([^)]*\)/',      // images
            '/\[([^\]]*)\]\([^)]*\)/',     // links: their text stays
            '/(?<!\\\\)<\/?(?:'.self::BLOCK_TAGS.')\b[^>]*>/i',
            '/(?<!\\\\)<\/?[a-z][a-z0-9]*\b[^>]*>/i',  // an escaped \<date> is text
        ], ['', ' ', ' ', '$1', ' ', ''], $markdown);

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\u{00AD}", "\u{00A0}"], ['', ' '], $text);

        return (string) preg_replace('/\\\\([[:punct:]])/', '$1', $text);
    }

    /**
     * The words of a Markdown text, lowercased, in order. A word is a run of
     * letters, digits and combining marks, so punctuation never counts.
     *
     * @return list<string>
     */
    public static function words(string $markdown): array
    {
        preg_match_all('/[\p{L}\p{N}\p{M}]+/u', mb_strtolower(self::plainText($markdown)), $matches);

        return $matches[0];
    }

    /**
     * The headings (ATX and setext) outside code blocks, as their words.
     *
     * @return list<string>
     */
    public static function headings(string $markdown): array
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $markdown));
        $headings = [];
        $fenced = false;

        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*(```|~~~)/', $line) === 1) {
                $fenced = ! $fenced;

                continue;
            }

            $next = $lines[$index + 1] ?? '';

            if ($fenced) {
                continue;
            }

            if (preg_match('/^ {0,3}#{1,6}\s+(.*?)\s*#*\s*$/u', $line, $match) === 1) {
                $headings[] = implode(' ', self::words($match[1]));
            } elseif (trim($line) !== '' && ! str_starts_with(ltrim($line), '|') && preg_match('/^ {0,3}(=+|-+)\s*$/', $next) === 1 && preg_match('/^ {0,3}[-*+]\s/', $line) !== 1) {
                // Setext: the heading may span the lines of its paragraph.
                $text = $line;

                for ($back = $index - 1; $back >= 0 && trim($lines[$back]) !== ''; $back--) {
                    $text = $lines[$back].' '.$text;
                }

                $headings[] = implode(' ', self::words($text));
            }
        }

        return array_values(array_filter($headings, static fn (string $heading): bool => $heading !== ''));
    }

    /**
     * The tables (pipe and HTML, outermost only) and their rows.
     *
     * @return array{tables: int, rows: int}
     */
    public static function tables(string $markdown): array
    {
        $tables = 0;
        $rows = 0;

        foreach (explode("\n", $markdown) as $line) {
            $line = trim($line);

            if (preg_match('/^\|(\s*:?-+:?\s*\|)+$/', $line) === 1 || preg_match('/^:?-+:?\s*(\|\s*:?-+:?\s*)+\|?$/', $line) === 1) {
                $tables++;
            } elseif (str_starts_with($line, '|') && str_ends_with($line, '|') && trim($line, "| \t") !== '') {
                // A row with no text (pandoc's stand-in header) is not counted.
                $rows++;
            }
        }

        preg_match_all('/<(\/?)(table|tr)\b/i', $markdown, $tags, PREG_SET_ORDER);
        $depth = 0;

        foreach ($tags as [, $closing, $tag]) {
            if (strtolower($tag) === 'table') {
                $depth += $closing === '' ? 1 : -1;
                $tables += $closing === '' && $depth === 1 ? 1 : 0;
            } elseif ($closing === '' && $depth === 1) {
                $rows++;
            }
        }

        return ['tables' => $tables, 'rows' => $rows];
    }

    /** The number of distinct footnote definitions (`[^1]: …`). */
    public static function footnotes(string $markdown): int
    {
        preg_match_all('/^\[\^([^\]]+)\]:/m', $markdown, $matches);

        return count(array_unique($matches[1]));
    }

    /**
     * Words of the candidate that are two neighbouring reference words run
     * together ("endof" for "end of"), shown as the reference pair. Pairs
     * with a one-letter word are skipped: "a T-piece" would match any "at".
     *
     * @param  list<string>  $reference
     * @param  list<string>  $candidate
     * @return list<string>
     */
    public static function runTogether(array $reference, array $candidate): array
    {
        $known = array_flip($reference);
        $joined = [];

        for ($index = 0; $index < count($reference) - 1; $index++) {
            if (mb_strlen($reference[$index]) > 1 && mb_strlen($reference[$index + 1]) > 1) {
                $joined[$reference[$index].$reference[$index + 1]] = $reference[$index].' '.$reference[$index + 1];
            }
        }

        $found = [];

        foreach ($candidate as $word) {
            if (! isset($known[$word]) && isset($joined[$word])) {
                $found[] = $joined[$word];
            }
        }

        return $found;
    }

    /**
     * How many words two word lists share, counting repeats.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private function shared(array $a, array $b): int
    {
        $counts = array_count_values($b);
        $common = 0;

        foreach (array_count_values($a) as $word => $count) {
            $common += min($count, $counts[$word] ?? 0);
        }

        return $common;
    }

    /**
     * The share of three-word sequences two texts have in common (Dice):
     * 1.0 when the words come in the same order.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private function trigramOverlap(array $a, array $b): float
    {
        $trigrams = static fn (array $words): array => array_map(
            static fn (int $index): string => $words[$index].' '.$words[$index + 1].' '.$words[$index + 2],
            count($words) < 3 ? [] : range(0, count($words) - 3),
        );

        $first = $trigrams($a);
        $second = $trigrams($b);

        if ($first === [] && $second === []) {
            return 1.0;
        }

        return 2 * $this->shared($first, $second) / (count($first) + count($second));
    }

    /**
     * The items of $a that $b does not have (counting repeats).
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     * @return list<string>
     */
    private function difference(array $a, array $b): array
    {
        $counts = array_count_values($b);
        $left = [];

        foreach ($a as $item) {
            if (($counts[$item] ?? 0) > 0) {
                $counts[$item]--;
            } else {
                $left[] = $item;
            }
        }

        return $left;
    }
}
