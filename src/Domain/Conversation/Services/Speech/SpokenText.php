<?php

declare(strict_types=1);

namespace Domain\Conversation\Services\Speech;

use Domain\Channel\Services\MarkdownDomFormatter;
use Domain\Conversation\Services\Turn\CitationMarkers;
use DOMNode;
use Illuminate\Support\Str;

/**
 * An agent reply as text to read aloud: the markdown walked like the plain
 * text of the messaging channels, but for the ear. Links read as their
 * words only, code blocks and pictures are skipped (a chart is a picture),
 * list items are read as lines without bullets, and citation markers go.
 */
class SpokenText extends MarkdownDomFormatter
{
    public function __construct(
        private readonly CitationMarkers $citations,
    ) {}

    public function from(string $markdown): string
    {
        return $this->render($this->citations->strip($markdown));
    }

    protected function mapNode(string $tag, DOMNode $node, string $renderedChildren): string
    {
        return match ($tag) {
            'pre', 'img' => '',
            'code' => $node->textContent,
            'br', 'hr' => "\n",
            'p', 'blockquote', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table' => Str::trim($renderedChildren)."\n\n",
            'ul', 'ol' => $this->renderList($node, ordered: false),
            'tr' => Str::trim($renderedChildren)."\n",
            'th', 'td' => Str::trim($renderedChildren).', ',
            default => $renderedChildren,
        };
    }

    protected function bulletMarker(): string
    {
        return '';
    }

    protected function formatLink(string $href, string $inner): string
    {
        return $inner;
    }
}
