<?php

namespace Domain\Channel\Slack\Services;

use DOMNode;
use DOMText;
use Illuminate\Support\Str;

/**
 * Renders the model's CommonMark reply as Slack mrkdwn (`*bold*`, `_italic_`,
 * `~strike~`, `` `code` ``, ```` ```pre``` ````, `<url|text>` links).
 *
 * The model emits standard CommonMark (`**bold**`, `- bullets`, `# headings`),
 * which is NOT what Slack renders — Slack has its own mrkdwn flavour with no
 * heading or table syntax. So we parse with the real CommonMark parser already
 * in the app (league/commonmark, via {@see Str::markdown}) and walk the
 * resulting DOM, mapping each node to its mrkdwn equivalent — lists become
 * `•`/numbered lines, headings become bold lines, unknown tags are unwrapped.
 * Slack requires `&`, `<`, `>` escaped as HTML entities in message text.
 */
class SlackFormatter
{
    public function toMrkdwn(string $markdown): string
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
            'strong', 'b' => '*'.trim($this->renderChildren($node)).'*',
            'em', 'i' => '_'.trim($this->renderChildren($node)).'_',
            'del', 's', 'strike' => '~'.trim($this->renderChildren($node)).'~',
            'code' => '`'.$this->esc($node->textContent).'`',
            'pre' => "```\n".rtrim($this->esc($node->textContent))."\n```\n",
            'a' => $this->renderLink($node),
            'br' => "\n",
            'hr' => "\n",
            'p' => $this->renderChildren($node)."\n\n",
            'blockquote' => $this->renderBlockquote($node),
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => '*'.trim($this->renderChildren($node))."*\n\n",
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

    private function renderBlockquote(DOMNode $node): string
    {
        $inner = trim($this->renderChildren($node));
        $quoted = implode("\n", array_map(fn (string $line): string => '> '.$line, explode("\n", $inner)));

        return $quoted."\n\n";
    }

    private function renderLink(DOMNode $node): string
    {
        $inner = trim($this->renderChildren($node));
        $href = $node->attributes?->getNamedItem('href')?->nodeValue ?? '';

        // Only real web links; otherwise keep the text alone.
        if ($href === '' || preg_match('#^https?://#i', $href) !== 1) {
            return $inner;
        }

        // `|` and `>` would terminate the label / the link token.
        $href = str_replace(['&', '|', '>'], ['&amp;', '%7C', '%3E'], $href);

        return '<'.$href.'|'.$inner.'>';
    }

    private function esc(string $text): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    }
}
