<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion\Markdown;

use Domain\Knowledge\Document\Conversion\EbookText;
use DOMElement;
use DOMNode;
use DOMText;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Reads a FictionBook ebook (.fb2) as GitHub Markdown with no pandoc on the
 * server. Uploads always use it, even with pandoc installed: pandoc garbles
 * windows-1251 books and drops FB2 tables ({@see EbookText}).
 *
 * FictionBook is one XML file. The main body's sections become headings by
 * depth (the body title is the top heading), with paragraphs, subtitles,
 * poems, citations and tables inside. Links to the notes body become `[^1]`
 * marks listed at the end, as pandoc does. The XML declares its own
 * encoding (often windows-1251), which the XML parser honours.
 */
final class Fb2Reader
{
    private const string FB = 'http://www.gribuser.ru/xml/fictionbook/2.0';

    private const string XLINK = 'http://www.w3.org/1999/xlink';

    /** Largest file read; FictionBook files hold their images inline. */
    private const int MAX_BYTES = 100 * 1024 * 1024;

    /** @var array<string, DOMElement> note id → its section in a notes body */
    private array $noteSections = [];

    private NoteList $notes;

    public function toMarkdown(string $path): string
    {
        $size = @filesize($path);

        if ($size === false || $size > self::MAX_BYTES) {
            throw new RuntimeException('The FictionBook file is missing or too large to read.');
        }

        $book = ZipPackage::parseXml(File::get($path))?->documentElement;

        if (! $book instanceof DOMElement) {
            throw new RuntimeException('The FictionBook file is not well-formed XML.');
        }

        $this->notes = new NoteList;
        $this->noteSections = [];
        $bodies = [];

        foreach ($this->children($book, 'body') as $body) {
            if (in_array($body->getAttribute('name'), ['notes', 'comments', 'footnotes'], true)) {
                foreach ($this->children($body, 'section') as $section) {
                    $this->noteSections[$section->getAttribute('id')] = $section;
                }
            } else {
                $bodies[] = $body;
            }
        }

        $blocks = [];

        foreach ($bodies as $body) {
            array_push($blocks, ...$this->section($body, 0));
        }

        return Str::trim(MarkdownText::join([...$blocks, [MarkdownText::BLOCK, $this->notes->toMarkdown()]]));
    }

    /**
     * A body or section: its title as a heading one level below its parent's,
     * then what it holds.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function section(DOMElement $section, int $level): array
    {
        $blocks = [];

        foreach ($section->childNodes as $child) {
            if (! $child instanceof DOMElement || $child->namespaceURI !== self::FB) {
                continue;
            }

            array_push($blocks, ...match ($child->localName) {
                'title' => [[MarkdownText::BLOCK, MarkdownText::heading($level + 1, $this->lines($child, ' '))]],
                'subtitle' => [[MarkdownText::BLOCK, MarkdownText::heading($level + 2, $this->inline($child))]],
                'section' => $this->section($child, $level + 1),
                'p', 'text-author', 'date' => $this->paragraph($this->inline($child)),
                'epigraph', 'annotation' => $this->section($child, $level + 1),
                'poem' => $this->poem($child, $level),
                'cite' => $this->quote($child),
                'table' => [[MarkdownText::BLOCK, $this->table($child)]],
                default => [],
            });
        }

        return $blocks;
    }

    /**
     * A poem: its title, then each stanza with one line per verse.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function poem(DOMElement $poem, int $level): array
    {
        $blocks = [];

        foreach ($poem->childNodes as $child) {
            if (! $child instanceof DOMElement || $child->namespaceURI !== self::FB) {
                continue;
            }

            array_push($blocks, ...match ($child->localName) {
                'title' => [[MarkdownText::BLOCK, MarkdownText::heading($level + 2, $this->lines($child, ' '))]],
                'stanza' => $this->paragraph(implode("\n", array_map(
                    fn (DOMElement $line): string => Str::trim($this->inline($line)),
                    array_filter($this->children($child, '*'), static fn (DOMElement $line): bool => in_array($line->localName, ['title', 'subtitle', 'v'], true)),
                ))),
                'epigraph' => $this->section($child, $level + 1),
                'text-author', 'date' => $this->paragraph($this->inline($child)),
                default => [],
            });
        }

        return $blocks;
    }

    /**
     * A citation as a block quote.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function quote(DOMElement $cite): array
    {
        $lines = [];

        foreach ($cite->childNodes as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === self::FB && in_array($child->localName, ['p', 'subtitle', 'text-author', 'v'], true)) {
                $text = MarkdownText::inline($this->inline($child));

                if ($text !== '') {
                    $lines[] = '> '.Str::replace("\n", "\n> ", $text);
                }
            }
        }

        return $lines === [] ? [] : [[MarkdownText::BLOCK, implode("\n>\n", $lines)]];
    }

    private function table(DOMElement $table): string
    {
        $rows = [];

        foreach ($this->children($table, 'tr') as $row) {
            $cells = [];

            foreach ($row->childNodes as $cell) {
                if ($cell instanceof DOMElement && in_array($cell->localName, ['th', 'td'], true)) {
                    $cells[] = MarkdownText::inline($this->inline($cell));
                    array_push($cells, ...array_fill(0, max(0, (int) $cell->getAttribute('colspan') - 1), ''));
                }
            }

            $rows[] = $cells;
        }

        return MarkdownText::table($rows);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function paragraph(string $text): array
    {
        $text = MarkdownText::inline($text);

        return $text === '' ? [] : [[MarkdownText::BLOCK, MarkdownText::paragraph($text)]];
    }

    /** The paragraphs of a title, joined. */
    private function lines(DOMElement $title, string $glue): string
    {
        return implode($glue, array_map(fn (DOMElement $line): string => Str::trim($this->inline($line)), $this->children($title, 'p')));
    }

    /** The text of a paragraph, with note links made into `[^1]` marks. */
    private function inline(DOMNode $node): string
    {
        $text = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $text .= Str::replaceMatches('/\s+/u', ' ', $child->data) ?? '';
            } elseif ($child instanceof DOMElement && $child->localName === 'a' && $this->isNoteLink($child)) {
                $text .= $this->note($child);
            } elseif ($child instanceof DOMElement && $child->localName !== 'image') {
                $text .= $this->inline($child);
            }
        }

        return $text;
    }

    private function isNoteLink(DOMElement $link): bool
    {
        return $link->getAttribute('type') === 'note' || isset($this->noteSections[Str::ltrim($this->href($link), '#')]);
    }

    private function note(DOMElement $link): string
    {
        $section = $this->noteSections[Str::ltrim($this->href($link), '#')] ?? null;

        if ($section === null) {
            return $this->inline($link);
        }

        $paragraphs = [];

        foreach ($section->childNodes as $child) {
            // The note's title is only its number.
            if ($child instanceof DOMElement && $child->localName !== 'title') {
                $paragraphs[] = Str::trim($this->inline($child));
            }
        }

        return $this->notes->add(implode(' ', array_filter($paragraphs)));
    }

    private function href(DOMElement $link): string
    {
        return $link->getAttributeNS(self::XLINK, 'href') ?: $link->getAttribute('href');
    }

    /**
     * @return list<DOMElement>
     */
    private function children(DOMElement $parent, string $name): array
    {
        $children = [];

        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && ($name === '*' || $child->localName === $name)) {
                $children[] = $child;
            }
        }

        return $children;
    }
}
