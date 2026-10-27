<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion\Markdown;

use Domain\Knowledge\Document\Conversion\EbookText;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use League\HTMLToMarkdown\Converter\TableConverter;
use League\HTMLToMarkdown\HtmlConverter;
use RuntimeException;

/**
 * Reads an EPUB ebook as GitHub Markdown with no pandoc on the server.
 * Uploads use it when pandoc is not installed ({@see EbookText}).
 *
 * It follows the book's spine (the reading order in its OPF package file)
 * and converts each chapter's XHTML with league/html-to-markdown. Before
 * that, footnotes marked as such (`epub:type="noteref"` / `"footnote"`, or
 * the ARIA roles) become `[^1]` marks listed at the end of the book, images
 * are dropped, and links inside the book keep only their text. The table of
 * contents page is skipped, as pandoc does.
 */
final class EpubReader
{
    private const string OPS = 'http://www.idpf.org/2007/ops';

    /** Block elements the converter has no rule for: they still end a line. */
    private const array BLOCKS = ['section', 'article', 'aside', 'header', 'footer', 'main', 'figure', 'figcaption', 'dl', 'dt', 'dd', 'address', 'details', 'summary', 'center'];

    /** Stands in for a note mark while the chapter is converted. */
    private const string NOTE_MARK = 'zqxnotezqx';

    private NoteList $notes;

    public function toMarkdown(string $path): string
    {
        $package = ZipPackage::open($path);

        try {
            $this->notes = new NoteList;
            $chapters = [];

            foreach ($this->spine($package) as $chapter) {
                $html = $package->read($chapter);
                $markdown = $html === null ? '' : $this->chapter($html);

                if ($markdown !== '') {
                    $chapters[] = [MarkdownText::BLOCK, $markdown];
                }
            }

            return Str::trim(MarkdownText::join([...$chapters, [MarkdownText::BLOCK, $this->notes->toMarkdown()]]));
        } finally {
            $package->close();
        }
    }

    /**
     * The chapter files in reading order, without the table of contents.
     *
     * @return list<string>
     */
    private function spine(ZipPackage $package): array
    {
        $container = $package->xml('META-INF/container.xml');
        $rootfile = $container?->getElementsByTagName('rootfile')->item(0);
        $opfPath = $rootfile instanceof DOMElement ? ZipPackage::resolve('', $rootfile->getAttribute('full-path')) : '';
        $opf = $opfPath === '' ? null : $package->xml($opfPath);

        if (! $opf instanceof DOMDocument) {
            throw new RuntimeException('The EPUB has no readable package file.');
        }

        $items = [];

        foreach ($opf->getElementsByTagName('item') as $item) {
            $items[$item->getAttribute('id')] = $item;
        }

        $chapters = [];

        foreach ($opf->getElementsByTagName('itemref') as $reference) {
            $item = $items[$reference->getAttribute('idref')] ?? null;

            if ($item === null || ! collect(['application/xhtml+xml', 'text/html'])->containsStrict($item->getAttribute('media-type'))) {
                continue;
            }

            if (Str::of($item->getAttribute('properties'))->explode(' ')->containsStrict('nav')) {
                continue;
            }

            $chapters[] = ZipPackage::resolve($opfPath, $item->getAttribute('href'));
        }

        return array_values(array_unique($chapters));
    }

    private function chapter(string $html): string
    {
        $dom = $this->parse($html);
        $body = $dom?->getElementsByTagName('body')->item(0);

        if (! $body instanceof DOMElement) {
            return '';
        }

        $this->prepare($dom, $body);

        $converter = new HtmlConverter([
            'header_style' => 'atx',
            'strip_tags' => true,
            'remove_nodes' => 'head script style nav',
            'hard_break' => true,
            'use_autolinks' => false,
        ]);
        $converter->getEnvironment()->addConverter(new TableConverter);

        $inner = '';

        foreach ($body->childNodes as $child) {
            // HTML serialisation: the converter parses HTML, where <div/> would not close.
            $inner .= $dom->saveHTML($child);
        }

        return Str::of($converter->convert($inner))
            ->replaceMatches("/\n{3,}/", "\n\n")
            ->replaceMatches('/'.self::NOTE_MARK.'(\d+)'.self::NOTE_MARK.'/', '[^$1]')
            ->trim()
            ->toString();
    }

    /**
     * Parses a chapter as XML (EPUB chapters are XHTML), falling back to the
     * forgiving HTML parser. Named HTML entities are spelled out first: the
     * XHTML DTD that defines them is never fetched.
     */
    private function parse(string $html): ?DOMDocument
    {
        $xml = Str::replaceMatches('/&([a-zA-Z][a-zA-Z0-9]*);/', static function (array $match): string {
            if (collect(['amp', 'lt', 'gt', 'quot', 'apos'])->containsStrict($match[1])) {
                return $match[0];
            }

            $character = html_entity_decode($match[0], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return $character === $match[0] ? '' : $character;
        }, $html) ?? '';

        $dom = ZipPackage::parseXml($xml);

        if ($dom instanceof DOMDocument) {
            return $dom;
        }

        $dom = new DOMDocument;

        return @$dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NONET | LIBXML_COMPACT) ? $dom : null;
    }

    /**
     * Makes the chapter ready to convert: notes out of the text and into the
     * note list, images and in-book link targets gone, block elements the
     * converter does not know kept apart.
     */
    private function prepare(DOMDocument $dom, DOMElement $body): void
    {
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ops', self::OPS);

        foreach ($this->all($xpath, $body, ['img', 'image', 'svg', 'math', 'video', 'audio', 'object', 'iframe']) as $media) {
            $media->parentNode?->removeChild($media);
        }

        foreach ($this->all($xpath, $body, ['a']) as $link) {
            $this->noteReference($xpath, $body, $link);
        }

        // Note blocks nothing pointed to, and the emptied note sections.
        foreach ($this->all($xpath, $body, ['aside', 'section', 'div', 'ol', 'ul', 'li', 'p']) as $element) {
            if ($this->isNote($element) && $element->parentNode !== null) {
                if (filled($element->textContent) && ! $this->isNoteList($element)) {
                    $this->notes->add($this->noteText($element));
                }

                $element->parentNode->removeChild($element);
            }
        }

        foreach ($this->all($xpath, $body, ['a']) as $link) {
            if (! Str::isMatch('#^(https?|mailto):#i', $link->getAttribute('href'))) {
                $this->unwrap($link);
            }
        }

        foreach ($this->all($xpath, $body, self::BLOCKS) as $block) {
            $block->parentNode?->insertBefore($dom->createElement('br'), $block);
            $block->appendChild($dom->createElement('br'));
        }
    }

    /** Turns a link to a note in this chapter into a `[^1]` mark, and takes the note out. */
    private function noteReference(DOMXPath $xpath, DOMElement $body, DOMElement $link): void
    {
        $href = $link->getAttribute('href');
        $typed = $this->hasType($link, ['noteref']) || $link->getAttribute('role') === 'doc-noteref';

        if (! Str::startsWith($href, '#') || ! $link->parentNode instanceof DOMNode) {
            return;
        }

        $target = $xpath->query('.//*[@id="'.Str::replace('"', '', Str::chopStart($href, '#')).'"]', $body)?->item(0);

        if (! $target instanceof DOMElement || (! $typed && ! $this->isNote($target))) {
            return;
        }

        // The converter would escape "[^1]", so the mark goes in as plain letters.
        $number = Str::trim($this->notes->add($this->noteText($target)), '[^]');
        $mark = $link->ownerDocument?->createTextNode(self::NOTE_MARK.$number.self::NOTE_MARK);
        $target->parentNode?->removeChild($target);

        // A <sup> around the link goes with it.
        $outer = $link->parentNode instanceof DOMElement && Str::lower($link->parentNode->localName) === 'sup' && Str::trim($link->parentNode->textContent) === Str::trim($link->textContent)
            ? $link->parentNode
            : $link;

        if ($mark !== null) {
            $outer->parentNode?->replaceChild($mark, $outer);
        }
    }

    /** The text of a note, without its back-link and number. */
    private function noteText(DOMElement $note): string
    {
        $copy = $note->cloneNode(true);

        if ($copy instanceof DOMElement) {
            foreach (iterator_to_array($copy->getElementsByTagName('a')) as $link) {
                if (Str::startsWith($link->getAttribute('href'), '#') || $link->getAttribute('role') === 'doc-backlink') {
                    $link->parentNode?->removeChild($link);
                }
            }
        }

        return Str::of($copy->textContent)
            ->replaceMatches(['/\s+/u', '/^[\s\x{21A9}\x{FE0E}\x{FE0F}]+|[\s\x{21A9}\x{FE0E}\x{FE0F}]+$/u'], [' ', ''])
            ->trim()
            ->toString();
    }

    private function isNote(DOMElement $element): bool
    {
        return $this->hasType($element, ['footnote', 'endnote', 'rearnote', 'note', 'footnotes', 'endnotes', 'rearnotes'])
            || collect(['doc-footnote', 'doc-endnote', 'doc-endnotes'])->containsStrict($element->getAttribute('role'));
    }

    /** A container of notes (pandoc's footnotes section), as opposed to one note. */
    private function isNoteList(DOMElement $element): bool
    {
        return $this->hasType($element, ['footnotes', 'endnotes', 'rearnotes']) || $element->getAttribute('role') === 'doc-endnotes';
    }

    /**
     * @param  list<string>  $types
     */
    private function hasType(DOMElement $element, array $types): bool
    {
        $type = $element->getAttributeNS(self::OPS, 'type') ?: $element->getAttribute('epub:type');

        return Str::of($type)->lower()->split('/\s+/')->intersect($types)->isNotEmpty();
    }

    /** Replaces an element with its children. */
    private function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (! $parent instanceof DOMNode) {
            return;
        }

        while ($element->firstChild instanceof DOMNode) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    /**
     * The elements with these local names under a node, in document order,
     * as a list that stays valid while they are removed.
     *
     * @param  list<string>  $names
     * @return list<DOMElement>
     */
    private function all(DOMXPath $xpath, DOMNode $context, array $names): array
    {
        $tests = Arr::join(array_map(static fn (string $name): string => 'local-name()="'.$name.'"', $names), ' or ');
        $elements = [];

        foreach ($xpath->query(".//*[{$tests}]", $context) ?: [] as $element) {
            if ($element instanceof DOMElement) {
                $elements[] = $element;
            }
        }

        return $elements;
    }
}
