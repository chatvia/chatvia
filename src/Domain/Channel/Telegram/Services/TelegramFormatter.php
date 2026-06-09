<?php

namespace Domain\Channel\Telegram\Services;

use DOMNode;
use DOMText;
use Illuminate\Support\Str;

/**
 * Renders the model's CommonMark reply into the small HTML subset Telegram
 * accepts with `parse_mode=HTML` (`<b> <i> <s> <code> <pre> <a> <blockquote>`).
 *
 * The model emits standard CommonMark (`**bold**`, `- bullets`, `# headings`),
 * which doesn't match Telegram's own MarkdownV2 flavour and whose rendered HTML
 * (`<p> <ul> <li> <h1>`) Telegram rejects. So we parse with the real CommonMark
 * parser already in the app (league/commonmark, via {@see Str::markdown}) and
 * walk the resulting DOM, mapping each node to a Telegram-supported tag — lists
 * become `•`/numbered lines, headings become bold, unknown tags are unwrapped.
 * {@see toPlain} is the stripped fallback used if Telegram ever rejects the HTML.
 */
class TelegramFormatter
{
    public function toHtml(string $markdown): string
    {
        $markdown = trim($markdown);

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
        $out = preg_replace("/[ \t]+\n/", "\n", $out);
        $out = preg_replace("/\n{3,}/", "\n\n", $out);

        return trim($out);
    }

    /**
     * Stripped plain text (bullets kept as •) — the fallback so a formatting
     * failure never shows raw `**asterisks**`.
     */
    public function toPlain(string $markdown): string
    {
        return trim(html_entity_decode(strip_tags($this->toHtml($markdown)), ENT_QUOTES | ENT_HTML5));
    }

    private function renderChildren(DOMNode $node): string
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
            return $this->esc($node->textContent);
        }

        if ($node->nodeType !== XML_ELEMENT_NODE) {
            return '';
        }

        $tag = strtolower($node->nodeName);

        return match ($tag) {
            'strong', 'b' => '<b>'.$this->renderChildren($node).'</b>',
            'em', 'i' => '<i>'.$this->renderChildren($node).'</i>',
            'del', 's', 'strike' => '<s>'.$this->renderChildren($node).'</s>',
            'code' => '<code>'.$this->esc($node->textContent).'</code>',
            'pre' => '<pre>'.$this->esc($node->textContent)."</pre>\n",
            'a' => $this->renderLink($node),
            'br' => "\n",
            'hr' => "\n",
            'p' => $this->renderChildren($node)."\n\n",
            'blockquote' => '<blockquote>'.trim($this->renderChildren($node))."</blockquote>\n",
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => '<b>'.trim($this->renderChildren($node))."</b>\n\n",
            'ul', 'ol' => $this->renderList($node, $tag === 'ol'),
            default => $this->renderChildren($node), // unwrap anything else
        };
    }

    private function renderList(DOMNode $node, bool $ordered): string
    {
        $out = '';
        $index = 1;

        foreach ($node->childNodes as $child) {
            if ($child->nodeType === XML_ELEMENT_NODE && strtolower($child->nodeName) === 'li') {
                $marker = $ordered ? $index++.'. ' : '• ';
                $out .= $marker.trim($this->renderChildren($child))."\n";
            }
        }

        return $out."\n";
    }

    private function renderLink(DOMNode $node): string
    {
        $inner = $this->renderChildren($node);
        $href = $node->attributes?->getNamedItem('href')?->nodeValue ?? '';

        // Only real web links; otherwise keep the text alone.
        if ($href === '' || preg_match('#^https?://#i', $href) !== 1) {
            return $inner;
        }

        return '<a href="'.str_replace(['&', '"'], ['&amp;', '&quot;'], $href).'">'.$inner.'</a>';
    }

    private function esc(string $text): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    }
}
