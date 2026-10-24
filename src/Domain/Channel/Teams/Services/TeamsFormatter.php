<?php

declare(strict_types=1);

namespace Domain\Channel\Teams\Services;

use Illuminate\Support\Str;

/**
 * Adapts the model's CommonMark reply to the Markdown subset Teams renders in
 * bot messages (`textFormat: markdown`). Unlike Slack's mrkdwn this IS
 * Markdown, so most of the reply passes through untouched — bold, italic,
 * lists, links, and code blocks all render natively. Teams does not render
 * `#` headings or horizontal rules in bot messages, so headings become bold
 * lines and rules are dropped; the rewrite is line-based and skips fenced
 * code blocks so code samples survive verbatim.
 */
class TeamsFormatter
{
    public function toTeamsMarkdown(string $markdown): string
    {
        $markdown = Str::trim($markdown);

        if ($markdown === '') {
            return '';
        }

        $out = [];
        $inFence = false;

        foreach (explode("\n", $markdown) as $line) {
            if (Str::isMatch('/^\s*(```|~~~)/', $line)) {
                $inFence = ! $inFence;
                $out[] = $line;

                continue;
            }

            if ($inFence) {
                $out[] = $line;

                continue;
            }

            // `# Heading` (with optional closing hashes) → bold line.
            $heading = Str::match('/^#{1,6}\s+(.+?)\s*#*\s*$/', $line);
            if ($heading !== '') {
                $out[] = '**'.$heading.'**';

                continue;
            }

            // Horizontal rules (---, ***, ___) render as literal characters — drop them.
            if (Str::isMatch('/^\s*([-*_])\s*(?:\1\s*){2,}$/', $line)) {
                continue;
            }

            $out[] = $line;
        }

        return Str::trim(implode("\n", $out));
    }
}
