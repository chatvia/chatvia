<?php

namespace Domain\Channel\Telegram\Services;

use Domain\Channel\Services\MarkdownDomFormatter;
use DOMNode;

/**
 * Renders the model's CommonMark reply into the small HTML subset Telegram
 * accepts with `parse_mode=HTML` (`<b> <i> <s> <code> <pre> <a> <blockquote>`).
 *
 * Telegram's own MarkdownV2 flavour (and the HTML CommonMark renders to —
 * `<p> <ul> <li> <h1>`) is rejected, so the shared DOM walk
 * ({@see MarkdownDomFormatter}) maps each node to a Telegram-supported tag —
 * lists become `•`/numbered lines, headings become bold, unknown tags are
 * unwrapped. {@see toPlain} is the stripped fallback used if Telegram ever
 * rejects the HTML.
 */
class TelegramFormatter extends MarkdownDomFormatter
{
    public function toHtml(string $markdown): string
    {
        return $this->render($markdown);
    }

    /**
     * Stripped plain text (bullets kept as •) — the fallback so a formatting
     * failure never shows raw `**asterisks**`.
     */
    public function toPlain(string $markdown): string
    {
        return trim(html_entity_decode(strip_tags($this->toHtml($markdown)), ENT_QUOTES | ENT_HTML5));
    }

    protected function mapNode(string $tag, DOMNode $node, string $renderedChildren): string
    {
        return match ($tag) {
            'strong', 'b' => '<b>'.$renderedChildren.'</b>',
            'em', 'i' => '<i>'.$renderedChildren.'</i>',
            'del', 's', 'strike' => '<s>'.$renderedChildren.'</s>',
            'code' => '<code>'.$this->renderText($node->textContent).'</code>',
            'pre' => '<pre>'.$this->renderText($node->textContent)."</pre>\n",
            'a' => $this->renderLink($node),
            'br' => "\n",
            'hr' => "\n",
            'p' => $renderedChildren."\n\n",
            'blockquote' => '<blockquote>'.trim($renderedChildren)."</blockquote>\n",
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => '<b>'.trim($renderedChildren)."</b>\n\n",
            'ul', 'ol' => $this->renderList($node, $tag === 'ol'),
            default => $renderedChildren, // unwrap anything else
        };
    }

    protected function renderText(string $text): string
    {
        return str_replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    }

    protected function trimsLinkText(): bool
    {
        return false;
    }

    protected function formatLink(string $href, string $inner): string
    {
        return '<a href="'.str_replace(['&', '"'], ['&amp;', '&quot;'], $href).'">'.$inner.'</a>';
    }
}
