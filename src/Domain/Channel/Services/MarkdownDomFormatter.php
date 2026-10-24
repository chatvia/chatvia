<?php

namespace Domain\Channel\Services;

use DOMNode;
use DOMText;
use Illuminate\Support\Str;

/**
 * Shared CommonMark → DOM walk for the per-channel markdown formatters
 * (Slack mrkdwn, Telegram HTML, WhatsApp markup, plain text).
 *
 * The model emits standard CommonMark (`**bold**`, `- bullets`, `# headings`),
 * which none of the messaging channels render natively — each has its own
 * flavour (or none at all). So we parse with the real CommonMark parser already
 * in the app (league/commonmark, via {@see Str::markdown}) and walk the
 * resulting DOM, mapping each node to that channel's equivalent. Only the
 * per-tag mapping ({@see mapNode}), the list-marker glyph, the link rendering,
 * and whether text nodes are escaped differ between channels — everything else
 * (the parse, the walk, the block-spacing tidy) is shared here.
 */
abstract class MarkdownDomFormatter
{
    /**
     * Parse the CommonMark, walk the resulting DOM, and tidy block spacing.
     */
    protected function render(string $markdown): string
    {
        $markdown = Str::trim($markdown);

        if ($markdown === '') {
            return '';
        }

        $html = Str::markdown($markdown, [
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
        ]);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        // <meta charset> keeps multibyte text (emoji, accents) intact; the wrapper
        // gives a single root, and the flags stop libxml adding <html>/<body>/DTD.
        $dom->loadHTML(
            '<meta http-equiv="Content-Type" content="text/html; charset=utf-8"><div>'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD,
        );
        libxml_clear_errors();

        $root = $dom->getElementsByTagName('div')->item(0);
        $out = $root !== null ? $this->renderChildren($root) : '';

        // Tidy the block-level spacing the mapping introduces.
        return Str::of($out)
            ->replaceMatches("/[ \t]+\n/", "\n")
            ->replaceMatches("/\n{3,}/", "\n\n")
            ->trim()
            ->toString();
    }

    protected function renderChildren(DOMNode $node): string
    {
        $out = '';
        foreach ($node->childNodes as $child) {
            $out .= $this->renderNode($child);
        }

        return $out;
    }

    private function renderNode(DOMNode $node): string
    {
        if ($node instanceof DOMText) {
            return $this->renderText($node->textContent);
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }

        $tag = Str::lower($node->nodeName);

        return $this->mapNode($tag, $node, $this->renderChildren($node));
    }

    /**
     * Render a bullet/numbered list. The list-marker glyph is the only part
     * that varies between channels (see {@see bulletMarker}).
     */
    protected function renderList(DOMNode $node, bool $ordered): string
    {
        $out = '';
        $index = 1;

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && Str::lower($child->nodeName) === 'li') {
                $marker = $ordered ? $index++.'. ' : $this->bulletMarker();
                $out .= $marker.Str::trim($this->renderChildren($child))."\n";
            }
        }

        return $out."\n";
    }

    /**
     * Render an `<a>` as a real web link in the channel's style, or fall back
     * to the bare inner text for non-web hrefs.
     */
    protected function renderLink(DOMNode $node): string
    {
        $inner = $this->trimsLinkText() ? Str::trim($this->renderChildren($node)) : $this->renderChildren($node);
        $href = $node->attributes?->getNamedItem('href')?->nodeValue ?? '';

        // Only real web links; otherwise keep the text alone.
        if ($href === '' || ! Str::isMatch('#^https?://#i', $href)) {
            return $inner;
        }

        return $this->formatLink($href, $inner);
    }

    /**
     * Map a single element to the channel's markup. `$renderedChildren` is the
     * already-walked inner content (subclasses may instead read `$node`
     * directly — e.g. `textContent` for code/pre).
     */
    abstract protected function mapNode(string $tag, DOMNode $node, string $renderedChildren): string;

    /**
     * How a raw text node is emitted — escaped (Slack, Telegram) or verbatim
     * (WhatsApp, plain text). Defaults to verbatim.
     */
    protected function renderText(string $text): string
    {
        return $text;
    }

    /**
     * The unordered-list marker glyph (incl. trailing space). Defaults to `• `.
     */
    protected function bulletMarker(): string
    {
        return '• ';
    }

    /**
     * Whether the link's inner text is trimmed before rendering. Telegram keeps
     * the inner text verbatim; the others trim. Defaults to trimming.
     */
    protected function trimsLinkText(): bool
    {
        return true;
    }

    /**
     * Render a verified web link in the channel's style. `$inner` is the
     * (already-trimmed-or-not) link text. Defaults to `text (url)`, the form
     * channels without link markup use (the bare URL auto-links client-side).
     */
    protected function formatLink(string $href, string $inner): string
    {
        // Bare-URL link text would just duplicate the href.
        return $inner === $href ? $href : "{$inner} ({$href})";
    }
}
