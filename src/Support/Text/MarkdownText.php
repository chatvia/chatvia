<?php

declare(strict_types=1);

namespace Support\Text;

use Illuminate\Support\Str;

/**
 * Markdown as one line of readable text, for showing a passage to a person:
 * a heading becomes a sentence of its own, a link keeps its text, and image,
 * emphasis, code, quote, list and table marks go. Not for text the model
 * reads, which needs its structure.
 */
final class MarkdownText
{
    public static function plain(string $markdown): string
    {
        return Str::of($markdown)
            ->replaceMatches('/!\[[^\]]*\]\([^)]*\)/', '')
            ->replaceMatches('/\[([^\]]*)\]\([^)]*\)/', '$1')
            ->replaceMatches('/^[ \t]{0,3}#{1,6}[ \t]+(.+?)[ \t]*#*[ \t]*$/m', static fn (array $match): string => Str::of($match[1])->trim()->finish(Str::endsWith($match[1], ['.', '!', '?', ':']) ? '' : '.')->value())
            ->replaceMatches('/^[ \t]{0,3}>[ \t]?/m', '')
            ->replaceMatches('/^[ \t]*(?:[-*+]|\d{1,3}[.)])[ \t]+/m', '')
            ->replaceMatches('/^[ \t]*\|?(?:[ \t]*:?-{3,}:?[ \t]*\|?)+[ \t]*$/m', '')
            ->replaceMatches('/(\*\*|__|~~|`)/', '')
            ->replace('|', ' ')
            ->squish()
            ->value();
    }
}
