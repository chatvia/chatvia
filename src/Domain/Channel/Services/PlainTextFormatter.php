<?php

declare(strict_types=1);

namespace Domain\Channel\Services;

use DOMNode;
use Illuminate\Support\Str;

/**
 * Renders the model's CommonMark reply as readable plain text, for channels
 * with no formatting syntax at all (Messenger, Instagram — no markdown, no
 * HTML). Markers are dropped rather than translated: bold/italic become bare
 * text, lists become `•`/numbered lines, headings become their own line, and
 * links keep their URL as `text (url)` so the platform's auto-linking makes
 * them tappable.
 *
 * Uses the shared CommonMark → DOM walk ({@see MarkdownDomFormatter}), like the
 * Telegram/Slack formatters.
 */
class PlainTextFormatter extends MarkdownDomFormatter
{
    public function toPlain(string $markdown): string
    {
        return $this->render($markdown);
    }

    protected function mapNode(string $tag, DOMNode $node, string $renderedChildren): string
    {
        return match ($tag) {
            'code' => $node->textContent,
            'pre' => Str::rtrim($node->textContent)."\n\n",
            'a' => $this->renderLink($node),
            'br' => "\n",
            'hr' => "\n",
            'p' => $renderedChildren."\n\n",
            'blockquote' => Str::trim($renderedChildren)."\n\n",
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6' => Str::trim($renderedChildren)."\n\n",
            'ul', 'ol' => $this->renderList($node, $tag === 'ol'),
            default => $renderedChildren, // unwrap formatting + anything else
        };
    }
}
