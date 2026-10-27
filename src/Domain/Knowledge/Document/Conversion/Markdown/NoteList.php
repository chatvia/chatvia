<?php

declare(strict_types=1);

namespace Domain\Knowledge\Document\Conversion\Markdown;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * The footnotes and endnotes of one document, numbered in the order they
 * are referenced. The text keeps a `[^1]` mark where the note was and lists
 * the notes at the end, as pandoc does.
 */
final class NoteList
{
    /** @var list<string> */
    private array $notes = [];

    /** Adds a note and gives the mark to put in the text. */
    public function add(string $text): string
    {
        $this->notes[] = Str::of($text)->replaceMatches('/\s+/u', ' ')->trim()->toString();

        return '[^'.count($this->notes).']';
    }

    public function toMarkdown(): string
    {
        $lines = [];

        foreach ($this->notes as $index => $text) {
            $lines[] = '[^'.($index + 1)."]: {$text}";
        }

        return Arr::join($lines, "\n\n");
    }
}
