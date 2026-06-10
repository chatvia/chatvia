<?php

namespace Domain\Channel\Teams\Services;

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
        $markdown = trim($markdown);

        if ($markdown === '') {
            return '';
        }

        $out = [];
        $inFence = false;

        foreach (explode("\n", $markdown) as $line) {
            if (preg_match('/^\s*(```|~~~)/', $line) === 1) {
                $inFence = ! $inFence;
                $out[] = $line;

                continue;
            }

            if ($inFence) {
                $out[] = $line;

                continue;
            }

            // `# Heading` (with optional closing hashes) → bold line.
            if (preg_match('/^#{1,6}\s+(.+?)\s*#*\s*$/', $line, $matches) === 1) {
                $out[] = '**'.$matches[1].'**';

                continue;
            }

            // Horizontal rules (---, ***, ___) render as literal characters — drop them.
            if (preg_match('/^\s*([-*_])\s*(?:\1\s*){2,}$/', $line) === 1) {
                continue;
            }

            $out[] = $line;
        }

        return trim(implode("\n", $out));
    }
}
