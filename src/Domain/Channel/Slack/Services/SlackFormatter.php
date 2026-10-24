<?php

declare(strict_types=1);

namespace Domain\Channel\Slack\Services;

use Domain\Channel\Services\MarkdownDomFormatter;
use DOMNode;
use Illuminate\Support\Str;

/**
 * Renders the model's CommonMark reply as Slack mrkdwn (`*bold*`, `_italic_`,
 * `~strike~`, `` `code` ``, ```` ```pre``` ````, `<url|text>` links).
 *
 * Slack has its own mrkdwn flavour with no heading or table syntax, so the
 * shared DOM walk ({@see MarkdownDomFormatter}) maps each node to its mrkdwn
 * equivalent — lists become `•`/numbered lines, headings become bold lines,
 * unknown tags are unwrapped. Slack requires `&`, `<`, `>` escaped as HTML
 * entities in message text.
 */
class SlackFormatter extends MarkdownDomFormatter
{
    public function toMrkdwn(string $markdown): string
    {
        return $this->render($markdown);
    }

    protected function mapNode(string $tag, DOMNode $node, string $renderedChildren): string
    {
        return match ($tag) {
            'strong', 'b' => '*'.Str::trim($renderedChildren).'*',
            'em', 'i' => '_'.Str::trim($renderedChildren).'_',
            'del', 's', 'strike' => '~'.Str::trim($renderedChildren).'~',
            'code' => '`'.$this->renderText($node->textContent).'`',
            'pre' => "```\n".Str::rtrim($this->renderText($node->textContent))."\n```\n",
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

    protected function renderText(string $text): string
    {
        return Str::replace(['&', '<', '>'], ['&amp;', '&lt;', '&gt;'], $text);
    }

    protected function formatLink(string $href, string $inner): string
    {
        // `|` and `>` would terminate the label / the link token.
        $href = Str::replace(['&', '|', '>'], ['&amp;', '%7C', '%3E'], $href);

        return '<'.$href.'|'.$inner.'>';
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
