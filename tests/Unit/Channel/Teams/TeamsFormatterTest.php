<?php

use Domain\Channel\Teams\Services\TeamsFormatter;

/**
 * Teams renders Markdown natively in bot messages, so the formatter is a
 * light touch-up: headings (which Teams shows as literal `#`) become bold
 * lines, horizontal rules are dropped, and everything else — including the
 * inside of fenced code blocks — passes through verbatim.
 */
function teamsMd(string $markdown): string
{
    return (new TeamsFormatter)->toTeamsMarkdown($markdown);
}

it('turns headings into bold lines', function (): void {
    expect(teamsMd("# Opening hours\nWe open at 9."))->toBe("**Opening hours**\nWe open at 9.");
    expect(teamsMd('### Deep heading'))->toBe('**Deep heading**');
});

it('handles closing-hash headings', function (): void {
    expect(teamsMd('## Prices ##'))->toBe('**Prices**');
});

it('drops horizontal rules', function (): void {
    expect(teamsMd("Above\n\n---\n\nBelow"))->toBe("Above\n\n\nBelow");
    expect(teamsMd("Above\n* * *\nBelow"))->toBe("Above\nBelow");
});

it('passes regular Markdown through untouched', function (): void {
    $markdown = "**Bold**, _italic_, and a [link](https://example.com).\n\n- one\n- two\n\n1. first\n2. second";

    expect(teamsMd($markdown))->toBe($markdown);
});

it('leaves fenced code blocks verbatim, even heading-shaped lines', function (): void {
    $markdown = "Run this:\n```bash\n# not a heading\n---\necho hi\n```";

    expect(teamsMd($markdown))->toBe($markdown);
});

it('returns an empty string for blank input', function (): void {
    expect(teamsMd("   \n  "))->toBe('');
});
