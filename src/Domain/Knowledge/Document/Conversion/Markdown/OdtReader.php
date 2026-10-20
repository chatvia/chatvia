<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion\Markdown;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;
use RuntimeException;

/**
 * Reads an OpenDocument text file (.odt) as GitHub Markdown with no pandoc
 * on the server. A prototype: the pandoc comparison eval measures it before
 * it is used for uploads.
 *
 * It reads `content.xml`: headings (`text:h`), lists with their bullet or
 * number style, tables as pipe tables, footnotes and endnotes as `[^1]`
 * marks listed at the end, and text boxes. Tracked deletions (stored apart
 * from the text), comments and generated indexes such as a table of
 * contents are left out.
 */
final class OdtReader
{
    private const string TEXT = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';

    private const string TABLE = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';

    private const string OFFICE = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';

    private const string DRAW = 'urn:oasis:names:tc:opendocument:xmlns:drawing:1.0';

    private const string STYLE = 'urn:oasis:names:tc:opendocument:xmlns:style:1.0';

    private const string XLINK = 'http://www.w3.org/1999/xlink';

    /** Generated lists (contents, indexes): pandoc leaves them out too. */
    private const array INDEXES = ['table-of-content', 'illustration-index', 'table-index', 'object-index', 'user-index', 'alphabetical-index', 'bibliography'];

    /** Text-namespace elements that carry no visible text. */
    private const array HIDDEN = [
        'tracked-changes', 'change', 'change-start', 'change-end', 'bookmark', 'bookmark-start', 'bookmark-end',
        'reference-mark', 'reference-mark-start', 'reference-mark-end', 'soft-page-break', 'toc-mark',
        'toc-mark-start', 'toc-mark-end', 'alphabetical-index-mark', 'alphabetical-index-mark-start',
        'alphabetical-index-mark-end', 'user-index-mark', 'note-citation', 'sequence-decls', 'variable-decls',
        'user-field-decls', 'dde-connection-decls',
        // The cached label of a numbered heading or list item, and the reading aid over ruby text.
        'number', 'ruby-text',
    ];

    /** Most columns read from one table row; a repeat count can be huge. */
    private const int MAX_COLUMNS = 64;

    /** @var array<string, array<int, array{bullet: bool, start: int}>> list style → level → format */
    private array $listStyles = [];

    /** @var array<string, int> list style → the last number given at the top level */
    private array $continued = [];

    private int $tableDepth = 0;

    private NoteList $notes;

    public function toMarkdown(string $path): string
    {
        $package = ZipPackage::open($path);

        try {
            $this->listStyles = $this->continued = [];
            $this->tableDepth = 0;
            $this->notes = new NoteList;

            $content = $package->xml('content.xml');
            $text = $this->first($this->first($content?->documentElement, self::OFFICE, 'body'), self::OFFICE, 'text');

            if (! $content instanceof DOMDocument || ! $text instanceof DOMElement) {
                throw new RuntimeException('The OpenDocument file has no readable text.');
            }

            $this->readListStyles($package->xml('styles.xml'));
            $this->readListStyles($content);

            return trim(MarkdownText::join([
                ...$this->blocks($text, 0),
                [MarkdownText::BLOCK, $this->notes->toMarkdown()],
            ]));
        } finally {
            $package->close();
        }
    }

    private function readListStyles(?DOMDocument $document): void
    {
        foreach ($document?->getElementsByTagNameNS(self::TEXT, 'list-style') ?? [] as $style) {
            $levels = [];

            foreach ($style->childNodes as $level) {
                if ($level instanceof DOMElement && $level->namespaceURI === self::TEXT) {
                    $levels[(int) $level->getAttributeNS(self::TEXT, 'level')] = [
                        // A number style with no format shows no number.
                        'bullet' => $level->localName !== 'list-level-style-number' || $level->getAttributeNS(self::STYLE, 'num-format') === '',
                        'start' => (int) ($level->getAttributeNS(self::TEXT, 'start-value') ?: 1),
                    ];
                }
            }

            $this->listStyles[$style->getAttributeNS(self::STYLE, 'name')] = $levels;
        }
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function blocks(DOMElement $container, int $depth): array
    {
        $blocks = [];

        foreach ($container->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $name = $child->namespaceURI.' '.$child->localName;

            $more = match ($name) {
                self::TEXT.' h' => $this->heading($child),
                self::TEXT.' p' => $this->paragraph($child),
                self::TEXT.' list' => $this->listBlocks($child, 0, null),
                self::TEXT.' numbered-paragraph' => $this->numberedParagraph($child),
                self::TABLE.' table' => $this->table($child),
                default => match (true) {
                    $child->namespaceURI === self::TEXT && in_array($child->localName, [...self::INDEXES, ...self::HIDDEN], true),
                    $child->namespaceURI === self::OFFICE => [],
                    // Sections, text boxes and other wrappers: read what they hold.
                    $depth < 30 => $this->blocks($child, $depth + 1),
                    default => [],
                },
            };

            array_push($blocks, ...$more);
        }

        return $blocks;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function heading(DOMElement $heading): array
    {
        $text = MarkdownText::inline($this->inline($heading));
        $level = (int) ($heading->getAttributeNS(self::TEXT, 'outline-level') ?: 1);

        if ($text === '') {
            return [];
        }

        return [[MarkdownText::BLOCK, $this->tableDepth > 0 ? $text : MarkdownText::heading($level, $text)]];
    }

    /**
     * A paragraph; a text box anchored in it is read as part of it.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function paragraph(DOMElement $paragraph): array
    {
        $text = MarkdownText::inline($this->inline($paragraph));

        return $text === '' ? [] : [[MarkdownText::BLOCK, MarkdownText::paragraph($text)]];
    }

    /**
     * A list and the lists inside it. Numbering restarts with every list
     * unless the list says it continues the one before.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function listBlocks(DOMElement $list, int $level, ?string $style): array
    {
        $style = $list->getAttributeNS(self::TEXT, 'style-name') ?: $style ?? '';
        $format = $this->listStyles[$style][$level + 1] ?? ['bullet' => true, 'start' => 1];
        $continues = $list->getAttributeNS(self::TEXT, 'continue-numbering') === 'true' || $list->hasAttributeNS(self::TEXT, 'continue-list');
        $number = $level === 0 && $continues ? ($this->continued[$style] ?? $format['start'] - 1) : $format['start'] - 1;
        $blocks = [];

        foreach ($list->childNodes as $item) {
            if (! $item instanceof DOMElement || $item->namespaceURI !== self::TEXT || ! in_array($item->localName, ['list-item', 'list-header'], true)) {
                continue;
            }

            $marked = $item->localName === 'list-item';

            if ($marked) {
                $number = $item->hasAttributeNS(self::TEXT, 'start-value') ? (int) $item->getAttributeNS(self::TEXT, 'start-value') : $number + 1;
            }

            $first = true;

            foreach ($item->childNodes as $child) {
                if (! $child instanceof DOMElement) {
                    continue;
                }

                if ($child->namespaceURI === self::TEXT && $child->localName === 'list') {
                    array_push($blocks, ...$this->listBlocks($child, $level + 1, $style));

                    continue;
                }

                if ($child->namespaceURI === self::TABLE) {
                    array_push($blocks, ...$this->table($child));

                    continue;
                }

                if ($child->namespaceURI === self::TEXT && $child->localName === 'number') {
                    continue;
                }

                $text = MarkdownText::inline($this->inline($child));

                if ($text === '') {
                    continue;
                }

                // The first paragraph carries the marker, later ones line up under it.
                $marker = $first && $marked ? ($format['bullet'] ? '-' : $number.'.') : ' ';
                $blocks[] = [$level === 0 && $blocks === [] ? MarkdownText::LIST_START : MarkdownText::ITEM, MarkdownText::item($level, $marker, $text)];
                $first = false;
            }
        }

        if ($level === 0) {
            $this->continued[$style] = $number;
        }

        return $blocks;
    }

    /**
     * An ODF 1.2 numbered paragraph: a list item standing on its own.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function numberedParagraph(DOMElement $paragraph): array
    {
        $text = MarkdownText::inline($this->inline($paragraph));
        $level = max(1, (int) ($paragraph->getAttributeNS(self::TEXT, 'level') ?: 1));

        return $text === '' ? [] : [[MarkdownText::ITEM, MarkdownText::item($level - 1, '-', $text)]];
    }

    /**
     * A table as a pipe table; a table in a table cell is flattened to text.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function table(DOMElement $table): array
    {
        $this->tableDepth++;

        try {
            $rows = [];

            foreach ($this->rows($table) as $row) {
                $cells = [];

                foreach ($row->childNodes as $cell) {
                    if (! $cell instanceof DOMElement || $cell->namespaceURI !== self::TABLE) {
                        continue;
                    }

                    // A cell under a merged cell is covered: it stays empty.
                    $text = $cell->localName === 'table-cell'
                        ? implode("\n", array_column($this->blocks($cell, 0), 1))
                        : ($cell->localName === 'covered-table-cell' ? '' : null);

                    if ($text === null) {
                        continue;
                    }

                    $repeat = max(1, (int) ($cell->getAttributeNS(self::TABLE, 'number-columns-repeated') ?: 1));
                    array_push($cells, ...array_fill(0, min($repeat, self::MAX_COLUMNS - count($cells)), $text));

                    if (count($cells) >= self::MAX_COLUMNS) {
                        break;
                    }
                }

                $rows[] = $cells;
            }
        } finally {
            $this->tableDepth--;
        }

        if ($this->tableDepth > 0) {
            return [[MarkdownText::BLOCK, implode(' ', array_filter(array_merge(...$rows), static fn (string $cell): bool => $cell !== ''))]];
        }

        return [[MarkdownText::BLOCK, MarkdownText::table($rows)]];
    }

    /**
     * The rows of a table, header rows first, through row groups.
     *
     * @return list<DOMElement>
     */
    private function rows(DOMElement $parent): array
    {
        $rows = [];

        foreach ($parent->childNodes as $child) {
            if (! $child instanceof DOMElement || $child->namespaceURI !== self::TABLE) {
                continue;
            }

            if ($child->localName === 'table-row') {
                $rows[] = $child;
            } elseif (in_array($child->localName, ['table-header-rows', 'table-rows', 'table-row-group'], true)) {
                array_push($rows, ...$this->rows($child));
            }
        }

        return $rows;
    }

    /** The text of a paragraph or of anything inside one. */
    private function inline(DOMNode $node): string
    {
        $text = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                // Spaces in OpenDocument text collapse, as in HTML; text:s is a real space.
                $text .= (string) preg_replace('/[ \t\r\n]+/', ' ', $child->data);

                continue;
            }

            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($child->namespaceURI === self::OFFICE) {
                // Comments (office:annotation) do not show in the text.
                continue;
            }

            if ($child->namespaceURI === self::DRAW && $child->localName === 'text-box') {
                $text .= "\n".implode("\n", array_column($this->blocks($child, 0), 1))."\n";

                continue;
            }

            if ($child->namespaceURI !== self::TEXT) {
                // Frames and links around text; images and their titles give nothing.
                $text .= in_array($child->localName, ['title', 'desc', 'image'], true) ? '' : $this->inline($child);

                continue;
            }

            $text .= match ($child->localName) {
                's' => str_repeat(' ', max(1, (int) ($child->getAttributeNS(self::TEXT, 'c') ?: 1))),
                'tab' => "\t",
                'line-break' => "\n",
                'note' => $this->note($child),
                'a' => $this->link($child),
                default => in_array($child->localName, self::HIDDEN, true) ? '' : $this->inline($child),
            };
        }

        return $text;
    }

    private function note(DOMElement $note): string
    {
        $body = $this->first($note, self::TEXT, 'note-body');

        return $body instanceof DOMElement ? $this->notes->add(implode(' ', array_column($this->blocks($body, 0), 1))) : '';
    }

    private function link(DOMElement $link): string
    {
        $text = $this->inline($link);
        $url = $link->getAttributeNS(self::XLINK, 'href');

        if (preg_match('#^(https?|mailto):#i', $url) !== 1 || blank($text) || preg_match('/^(\s*)(.*?)(\s*)$/su', $text, $parts) !== 1) {
            return $text;
        }

        return $parts[1].'['.str_replace(']', '\\]', $parts[2]).']('.str_replace([' ', '(', ')'], ['%20', '%28', '%29'], $url).')'.$parts[3];
    }

    private function first(?DOMElement $parent, string $namespace, string $name): ?DOMElement
    {
        foreach ($parent?->childNodes ?? [] as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === $namespace && $child->localName === $name) {
                return $child;
            }
        }

        return null;
    }
}
