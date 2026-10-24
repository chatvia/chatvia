<?php

declare(strict_types=1);

namespace Domain\Channel\Whatsapp\Services;

use Domain\Channel\Services\MarkdownDomFormatter;
use DOMNode;
use Illuminate\Support\Str;

/**
 * Renders the model's CommonMark reply as WhatsApp markup (`*bold*`,
 * `_italic_`, `~strike~`, `` `inline code` ``, ```` ```blocks``` ````,
 * `- `/`1. ` lists and `> ` quotes — all rendered natively by WhatsApp
 * clients).
 *
 * WhatsApp doesn't render CommonMark — double-asterisk bold shows as literal
 * asterisks, and there is no heading or `[text](url)` link syntax (URLs
 * auto-link bare, so links become `text (url)`). So the shared DOM walk
 * ({@see MarkdownDomFormatter}) maps each node to WhatsApp's markup, like the
 * other channel formatters. The body is plain UTF-8 — no character escaping.
 */
class WhatsappFormatter extends MarkdownDomFormatter
{
    public function toWhatsapp(string $markdown): string
    {
        return $this->render($markdown);
    }

    protected function mapNode(string $tag, DOMNode $node, string $renderedChildren): string
    {
        return match ($tag) {
            // WhatsApp markers must hug the text — trim inner whitespace.
            'strong', 'b' => '*'.Str::trim($renderedChildren).'*',
            'em', 'i' => '_'.Str::trim($renderedChildren).'_',
            'del', 's', 'strike' => '~'.Str::trim($renderedChildren).'~',
            'code' => '`'.$node->textContent.'`',
            // textContent skips the fence's language tag, which would render
            // as literal text inside the monospace block.
            'pre' => "```\n".Str::rtrim($node->textContent)."\n```\n",
            'a' => $this->renderLink($node),
            'br' => "\n",
            'hr' => "\n",
            'p' => $renderedChildren."\n\n",
            'blockquote' => $this->renderBlockquote($node),
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => '*'.Str::trim($renderedChildren)."*\n\n",
            'ul', 'ol' => $this->renderList($node, $tag === 'ol'),
            default => $renderedChildren, // unwrap anything else
        };
    }

    protected function bulletMarker(): string
    {
        return '- ';
    }

    private function renderBlockquote(DOMNode $node): string
    {
        $quoted = Str::of($this->renderChildren($node))
            ->trim()
            ->explode("\n")
            ->map(fn (string $line): string => '> '.$line)
            ->implode("\n");

        return $quoted."\n\n";
    }
}
