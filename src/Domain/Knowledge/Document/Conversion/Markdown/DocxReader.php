<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion\Markdown;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Reads a Word document (.docx) as GitHub Markdown with no pandoc on the
 * server. A prototype: the pandoc comparison eval measures it before it is
 * used for uploads.
 *
 * It reads the body part (`word/document.xml`) the way pandoc does:
 *
 *  - headings from the paragraph style ("heading 1"…, "Title") or its
 *    outline level, so localised style ids still count;
 *  - bulleted and numbered lists from `numbering.xml`, nested by level;
 *  - tables as pipe tables (the first row is the header; merged cells
 *    leave empty cells; a one-column layout table is read as paragraphs);
 *  - tracked changes accepted: deletions and moved-away text are skipped,
 *    insertions kept;
 *  - footnotes and endnotes as `[^1]` marks, listed at the end;
 *  - field codes, comments, headers and footers left out.
 */
final class DocxReader
{
    private const string W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private const string R = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private const string MC = 'http://schemas.openxmlformats.org/markup-compatibility/2006';

    /** Elements whose content never shows in the text. */
    private const array HIDDEN = [
        'del', 'moveFrom', 'delText', 'instrText', 'delInstrText', 'pPr', 'rPr', 'sdtPr', 'sdtEndPr',
        'tblPr', 'trPr', 'tcPr', 'sectPr', 'commentReference', 'annotationRef', 'footnoteRef',
        'endnoteRef', 'separator', 'continuationSeparator', 'continuationNotice',
    ];

    /** Wrappers whose children are read as if they stood in the wrapper's place. */
    private const array WRAPPERS = ['sdtContent', 'customXml', 'smartTag', 'ins', 'moveTo'];

    /** @var array<string, array{name: string, basedOn: ?string, outline: ?int, numId: ?string, ilvl: ?int}> */
    private array $styles = [];

    /** @var array<string, array{abstract: string, starts: array<int, int>}> */
    private array $nums = [];

    /** @var array<string, array{link: ?string, levels: array<int, array{format: string, start: int}>}> */
    private array $abstracts = [];

    /** @var array<string, string> relationship id → external URL */
    private array $links = [];

    /** @var array<string, array<string, DOMElement>> note kind → note id → note */
    private array $noteParts = [];

    /**
     * Word numbers a list per abstract numbering, across every numId that
     * shares it; a numId with a start override restarts it once.
     *
     * @var array<string, array<int, int>> abstract numbering → level → the last number given
     */
    private array $counters = [];

    /** @var array<string, true> "numId:level" restarts already applied */
    private array $restarted = [];

    /** @var list<bool> the open complex fields; true while in the field code */
    private array $fields = [];

    private int $tableDepth = 0;

    /** The abstract numbering of the last list item, to tell where a new list starts. */
    private ?string $lastList = null;

    private bool $newList = false;

    private NoteList $notes;

    public function toMarkdown(string $path): string
    {
        $package = ZipPackage::open($path);

        try {
            $this->reset();

            $main = $this->mainPart($package);
            $body = $this->child($package->xml($main)?->documentElement, 'body');

            if (! $body instanceof DOMElement) {
                throw new RuntimeException('The Word document has no readable body.');
            }

            $this->readRelatedParts($package, $main);

            return Str::trim(MarkdownText::join([
                ...$this->blocks($body),
                [MarkdownText::BLOCK, $this->notes->toMarkdown()],
            ]));
        } finally {
            $package->close();
        }
    }

    private function reset(): void
    {
        $this->styles = $this->nums = $this->abstracts = $this->links = $this->noteParts = $this->counters = $this->restarted = $this->fields = [];
        $this->tableDepth = 0;
        $this->lastList = null;
        $this->notes = new NoteList;
    }

    /** The body part named by the package relationships; Word itself always uses word/document.xml. */
    private function mainPart(ZipPackage $package): string
    {
        foreach ($this->relationships($package->xml('_rels/.rels')) as $relationship) {
            if (Str::endsWith($relationship['type'], '/officeDocument')) {
                return ZipPackage::resolve('', $relationship['target']);
            }
        }

        return 'word/document.xml';
    }

    private function readRelatedParts(ZipPackage $package, string $main): void
    {
        $parts = ['styles' => 'word/styles.xml', 'numbering' => 'word/numbering.xml', 'footnotes' => 'word/footnotes.xml', 'endnotes' => 'word/endnotes.xml'];
        $relations = $package->xml(ZipPackage::resolve($main, '_rels/'.basename($main).'.rels'));

        foreach ($this->relationships($relations) as $relationship) {
            $kind = Str::afterLast($relationship['type'], '/');

            if ($kind === 'hyperlink' && $relationship['external']) {
                $this->links[$relationship['id']] = $relationship['target'];
            } elseif (isset($parts[$kind]) && ! $relationship['external']) {
                $parts[$kind] = ZipPackage::resolve($main, $relationship['target']);
            }
        }

        $this->readStyles($package->xml($parts['styles']));
        $this->readNumbering($package->xml($parts['numbering']));

        foreach (['footnote' => $parts['footnotes'], 'endnote' => $parts['endnotes']] as $kind => $part) {
            foreach ($this->elements($package->xml($part)?->documentElement, $kind) as $note) {
                $this->noteParts[$kind][$this->attribute($note, 'id') ?? ''] = $note;
            }
        }
    }

    /**
     * @return list<array{id: string, type: string, target: string, external: bool}>
     */
    private function relationships(?DOMDocument $relations): array
    {
        $list = [];

        foreach ($relations?->getElementsByTagName('Relationship') ?? [] as $relationship) {
            $list[] = [
                'id' => $relationship->getAttribute('Id'),
                'type' => $relationship->getAttribute('Type'),
                'target' => $relationship->getAttribute('Target'),
                'external' => strcasecmp($relationship->getAttribute('TargetMode'), 'External') === 0,
            ];
        }

        return $list;
    }

    private function readStyles(?DOMDocument $styles): void
    {
        foreach ($this->elements($styles?->documentElement, 'style') as $style) {
            $properties = $this->child($style, 'pPr');
            $numbering = $this->child($properties, 'numPr');
            $outline = $this->attribute($this->child($properties, 'outlineLvl'), 'val');
            $ilvl = $this->attribute($this->child($numbering, 'ilvl'), 'val');

            $this->styles[$this->attribute($style, 'styleId') ?? ''] = [
                'name' => $this->attribute($this->child($style, 'name'), 'val') ?? '',
                'basedOn' => $this->attribute($this->child($style, 'basedOn'), 'val'),
                'outline' => $outline === null ? null : (int) $outline,
                'numId' => $this->attribute($this->child($numbering, 'numId'), 'val'),
                'ilvl' => $ilvl === null ? null : (int) $ilvl,
            ];
        }
    }

    private function readNumbering(?DOMDocument $numbering): void
    {
        foreach ($this->elements($numbering?->documentElement, 'abstractNum') as $abstract) {
            $levels = [];

            foreach ($this->elements($abstract, 'lvl') as $level) {
                $levels[(int) $this->attribute($level, 'ilvl')] = [
                    'format' => $this->attribute($this->child($level, 'numFmt'), 'val') ?? 'decimal',
                    'start' => (int) ($this->attribute($this->child($level, 'start'), 'val') ?? 1),
                ];
            }

            $this->abstracts[$this->attribute($abstract, 'abstractNumId') ?? ''] = [
                'link' => $this->attribute($this->child($abstract, 'numStyleLink'), 'val'),
                'levels' => $levels,
            ];
        }

        foreach ($this->elements($numbering?->documentElement, 'num') as $num) {
            $starts = [];

            foreach ($this->elements($num, 'lvlOverride') as $override) {
                $start = $this->attribute($this->child($override, 'startOverride'), 'val');

                if ($start !== null) {
                    $starts[(int) $this->attribute($override, 'ilvl')] = (int) $start;
                }
            }

            $this->nums[$this->attribute($num, 'numId') ?? ''] = [
                'abstract' => $this->attribute($this->child($num, 'abstractNumId'), 'val') ?? '',
                'starts' => $starts,
            ];
        }
    }

    /**
     * The Markdown blocks of a body, table cell, note or text box.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function blocks(DOMElement $container): array
    {
        $blocks = [];

        foreach ($this->content($container) as $element) {
            if ($element->namespaceURI !== self::W) {
                continue;
            }

            if ($element->localName === 'p') {
                $blocks = [...$blocks, ...$this->paragraph($element)];
            } elseif ($element->localName === 'tbl') {
                $blocks = [...$blocks, ...$this->table($element)];
            }
        }

        return $blocks;
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function paragraph(DOMElement $paragraph): array
    {
        $text = MarkdownText::inline($this->inline($paragraph));

        if ($text === '') {
            return [];
        }

        $properties = $this->child($paragraph, 'pPr');
        $style = $this->attribute($this->child($properties, 'pStyle'), 'val');
        $level = $this->headingLevel($properties, $style);

        if ($level !== null && $this->tableDepth === 0) {
            return [[MarkdownText::BLOCK, MarkdownText::heading($level, $text)]];
        }

        $list = $this->tableDepth === 0 ? $this->listLevel($properties, $style) : null;

        if ($list !== null) {
            $item = MarkdownText::item($list[1], $this->marker($list[0], $list[1]), $text);

            return [[$this->newList ? MarkdownText::LIST_START : MarkdownText::ITEM, $item]];
        }

        return [[MarkdownText::BLOCK, MarkdownText::paragraph($text)]];
    }

    /** The text of a paragraph or of anything inside one. */
    private function inline(DOMNode $node): string
    {
        $text = '';

        foreach ($node->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($child->namespaceURI === self::MC) {
                $choice = $child->localName === 'AlternateContent' ? $this->alternative($child) : null;
                $text .= $choice instanceof DOMElement ? $this->inline($choice) : '';

                continue;
            }

            if ($child->namespaceURI !== self::W) {
                // Drawing and VML wrappers: a text box inside them is still read.
                $text .= $this->inline($child);

                continue;
            }

            $text .= match ($child->localName) {
                't' => $this->inFieldCode() ? '' : $child->textContent,
                'tab', 'ptab' => "\t",
                'br' => collect(['page', 'column'])->containsStrict($this->attribute($child, 'type')) ? ' ' : "\n",
                'cr' => "\n",
                'noBreakHyphen' => '-',
                'softHyphen' => '',
                'sym' => $this->symbol($child),
                'fldChar' => $this->field($child),
                'footnoteReference' => $this->note('footnote', $child),
                'endnoteReference' => $this->note('endnote', $child),
                'hyperlink' => $this->hyperlink($child),
                'txbxContent' => "\n".Arr::join(array_column($this->blocks($child), 1), "\n")."\n",
                default => collect(self::HIDDEN)->containsStrict($child->localName) ? '' : $this->inline($child),
            };
        }

        return $text;
    }

    /** Tracks complex fields: their code (between begin and separate) never shows. */
    private function field(DOMElement $mark): string
    {
        $type = $this->attribute($mark, 'fldCharType');

        if ($type === 'begin') {
            $this->fields[] = true;
        } elseif ($type === 'separate' && $this->fields !== []) {
            $this->fields[array_key_last($this->fields)] = false;
        } elseif ($type === 'end') {
            array_pop($this->fields);
        }

        return '';
    }

    private function inFieldCode(): bool
    {
        return $this->fields !== [] && array_last($this->fields);
    }

    /** A symbol-font character; the private-use ones (Wingdings, Symbol) carry no text. */
    private function symbol(DOMElement $symbol): string
    {
        $code = hexdec($this->attribute($symbol, 'char') ?? '');

        return is_int($code) && $code > 31 && ($code < 0xE000 || $code > 0xF8FF) ? mb_chr($code, 'UTF-8') : '';
    }

    private function note(string $kind, DOMElement $reference): string
    {
        $note = $this->noteParts[$kind][$this->attribute($reference, 'id') ?? ''] ?? null;

        if ($note === null) {
            return '';
        }

        $fields = $this->fields;
        $this->fields = [];
        $text = Arr::join(array_column($this->blocks($note), 1), ' ');
        $this->fields = $fields;

        return $this->notes->add($text);
    }

    private function hyperlink(DOMElement $link): string
    {
        $text = $this->inline($link);
        $url = $this->links[$link->getAttributeNS(self::R, 'id')] ?? null;

        if ($url === null || blank($text) || preg_match('/^(\s*)(.*?)(\s*)$/su', $text, $parts) !== 1) {
            return $text;
        }

        return "{$parts[1]}[".Str::replace(']', '\\]', $parts[2]).']('.Str::replace([' ', '(', ')'], ['%20', '%28', '%29'], $url)."){$parts[3]}";
    }

    /**
     * A table as a pipe table. A table inside a table cell is flattened to
     * its text; a one-column table is a layout box and is read as paragraphs.
     *
     * @return list<array{0: string, 1: string}>
     */
    private function table(DOMElement $table): array
    {
        $rows = array_values(array_filter(
            $this->elementsOf($this->content($table), 'tr'),
            fn (DOMElement $row): bool => ! $this->child($this->child($row, 'trPr'), 'del') instanceof DOMElement,
        ));

        $width = max([0, ...array_map(fn (DOMElement $row): int => array_sum(array_map($this->span(...), $this->elementsOf($this->content($row), 'tc'))), $rows)]);

        if ($width <= 1 && $this->tableDepth === 0) {
            $blocks = [];

            foreach ($rows as $row) {
                foreach ($this->elementsOf($this->content($row), 'tc') as $cell) {
                    $blocks = [...$blocks, ...$this->blocks($cell)];
                }
            }

            return $blocks;
        }

        $this->tableDepth++;

        try {
            $cells = [];

            foreach ($rows as $row) {
                $line = [];

                foreach ($this->elementsOf($this->content($row), 'tc') as $cell) {
                    $merge = $this->child($this->child($cell, 'tcPr'), 'vMerge');
                    $continued = $merge instanceof DOMElement && $this->attribute($merge, 'val') !== 'restart';

                    $line[] = $continued ? '' : Arr::join(array_column($this->blocks($cell), 1), "\n");
                    array_push($line, ...array_fill(0, $this->span($cell) - 1, ''));
                }

                $cells[] = $line;
            }
        } finally {
            $this->tableDepth--;
        }

        if ($this->tableDepth > 0) {
            return [[MarkdownText::BLOCK, Arr::join(array_filter(array_merge(...$cells), static fn (string $cell): bool => $cell !== ''), ' ')]];
        }

        return [[MarkdownText::BLOCK, MarkdownText::table($cells)]];
    }

    private function span(DOMElement $cell): int
    {
        return max(1, (int) ($this->attribute($this->child($this->child($cell, 'tcPr'), 'gridSpan'), 'val') ?? 1));
    }

    /** The heading level of a paragraph, from its style name or outline level. */
    private function headingLevel(?DOMElement $properties, ?string $style): ?int
    {
        $outline = $this->attribute($this->child($properties, 'outlineLvl'), 'val');

        if ($outline !== null) {
            return (int) $outline < 9 ? (int) $outline + 1 : null;
        }

        foreach ($this->styleChain($style) as $definition) {
            $level = Str::match('/^heading\s*([1-9])$/i', $definition['name']);

            if ($level !== '') {
                return (int) $level;
            }

            if (collect(['title', 'toc heading'])->containsStrict(Str::lower($definition['name']))) {
                return 1;
            }

            if ($definition['outline'] !== null) {
                return $definition['outline'] < 9 ? $definition['outline'] + 1 : null;
            }
        }

        if ($this->styles !== []) {
            return null;
        }

        // Without a styles part the id is all there is ("Heading1").
        $level = Str::match('/^heading([1-9])$/i', $style ?? '');

        return $level !== '' ? (int) $level : null;
    }

    /**
     * The list (numId) and level of a paragraph, from its own numbering or
     * its style's; null when it is not in a list.
     *
     * @return array{0: string, 1: int}|null
     */
    private function listLevel(?DOMElement $properties, ?string $style): ?array
    {
        $numbering = $this->child($properties, 'numPr');
        $numId = $this->attribute($this->child($numbering, 'numId'), 'val');
        $level = $this->attribute($this->child($numbering, 'ilvl'), 'val');
        $level = $level === null ? null : (int) $level;

        foreach ($this->styleChain($style) as $definition) {
            $numId ??= $definition['numId'];
            $level ??= $definition['ilvl'];
        }

        return $numId === null || $numId === '0' ? null : [$numId, max(0, min(8, $level ?? 0))];
    }

    /** The list marker of the next item: "-" for bullets, its number for numbered lists. */
    private function marker(string $numId, int $level): string
    {
        $num = $this->nums[$numId] ?? ['abstract' => "num:{$numId}", 'starts' => []];
        $key = $num['abstract'];
        $abstract = $this->abstracts[$key] ?? ['link' => null, 'levels' => []];

        if ($abstract['levels'] === [] && $abstract['link'] !== null) {
            // A list style: its numbering lives with the style's own list.
            $key = $this->nums[$this->styles[$abstract['link']]['numId'] ?? '']['abstract'] ?? $key;
            $abstract = $this->abstracts[$key] ?? $abstract;
        }

        $format = $abstract['levels'][$level] ?? ['format' => 'bullet', 'start' => 1];
        $counters = array_filter($this->counters[$key] ?? [], static fn (int $deeper): bool => $deeper <= $level, ARRAY_FILTER_USE_KEY);

        $restart = isset($num['starts'][$level]) && ! isset($this->restarted["{$numId}:{$level}"]);

        if ($restart) {
            $this->restarted["{$numId}:{$level}"] = true;
            $counters[$level] = $num['starts'][$level];
        } else {
            $counters[$level] = isset($counters[$level]) ? $counters[$level] + 1 : $format['start'];
        }

        // Only a top-level item starts a list; a nested list stays inside its item.
        $this->newList = $level === 0 && ($key !== $this->lastList || $restart);
        $this->lastList = $level === 0 ? $key : $this->lastList;

        $this->counters[$key] = $counters;

        return collect(['bullet', 'none'])->containsStrict($format['format']) ? '-' : "{$counters[$level]}.";
    }

    /**
     * A style and the styles it is based on, nearest first.
     *
     * @return list<array{name: string, basedOn: ?string, outline: ?int, numId: ?string, ilvl: ?int}>
     */
    private function styleChain(?string $style): array
    {
        $chain = [];

        while ($style !== null && isset($this->styles[$style]) && count($chain) < 20) {
            $chain[] = $this->styles[$style];
            $style = $this->styles[$style]['basedOn'];
        }

        return $chain;
    }

    /**
     * The element children of a container, with content controls, custom
     * XML, smart tags and tracked insertions unwrapped, and deletions dropped.
     *
     * @return list<DOMElement>
     */
    private function content(DOMElement $container): array
    {
        $elements = [];

        foreach ($container->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            if ($child->namespaceURI === self::MC && $child->localName === 'AlternateContent') {
                $choice = $this->alternative($child);
                array_push($elements, ...($choice instanceof DOMElement ? $this->content($choice) : []));
            } elseif ($child->namespaceURI === self::W && $child->localName === 'sdt') {
                $inner = $this->child($child, 'sdtContent');
                array_push($elements, ...($inner instanceof DOMElement ? $this->content($inner) : []));
            } elseif ($child->namespaceURI === self::W && collect(self::WRAPPERS)->containsStrict($child->localName)) {
                array_push($elements, ...$this->content($child));
            } else {
                $elements[] = $child;
            }
        }

        return $elements;
    }

    /** The branch of a markup-compatibility block to read: the first choice, else the fallback. */
    private function alternative(DOMElement $block): ?DOMElement
    {
        foreach (['Choice', 'Fallback'] as $branch) {
            foreach ($block->childNodes as $child) {
                if ($child instanceof DOMElement && $child->namespaceURI === self::MC && $child->localName === $branch) {
                    return $child;
                }
            }
        }

        return null;
    }

    /**
     * @param  list<DOMElement>  $elements
     * @return list<DOMElement>
     */
    private function elementsOf(array $elements, string $name): array
    {
        return array_values(array_filter($elements, static fn (DOMElement $element): bool => $element->namespaceURI === self::W && $element->localName === $name));
    }

    /**
     * The direct W children with a name.
     *
     * @return list<DOMElement>
     */
    private function elements(?DOMElement $parent, string $name): array
    {
        $elements = [];

        foreach ($parent?->childNodes ?? [] as $child) {
            if ($child instanceof DOMElement && $child->namespaceURI === self::W && $child->localName === $name) {
                $elements[] = $child;
            }
        }

        return $elements;
    }

    private function child(?DOMElement $parent, string $name): ?DOMElement
    {
        return $this->elements($parent, $name)[0] ?? null;
    }

    private function attribute(?DOMElement $element, string $name): ?string
    {
        return $element?->hasAttributeNS(self::W, $name) ? $element->getAttributeNS(self::W, $name) : null;
    }
}
