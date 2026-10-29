<?php

declare(strict_types=1);

use Domain\Knowledge\Document\Conversion\Markdown\MarkdownText;

/**
 * The Markdown blocks the built-in readers share.
 */
it('tidies paragraph text and escapes what would read as markup', function (): void {
    expect(MarkdownText::inline("  super\u{00AD}cali \t fragile \n\n  <date> and a < b  "))->toBe("supercali fragile\n\\<date> and a < b")
        ->and(MarkdownText::paragraph("# tag\n1. one\n2) two\n- dash\n3.5 litres"))->toBe("\\# tag\n1\\. one\n2\\) two\n\\- dash\n3.5 litres")
        ->and(MarkdownText::heading(9, "  Long\n title "))->toBe('###### Long title')
        ->and(MarkdownText::heading(1, ' '))->toBe('');
});

it('writes pipe tables with the first row as the header', function (): void {
    expect(MarkdownText::table([['Plan', '', 'Price'], ['Pro | Team', '', "49\n/ month"], ['', '', ''], ['Starter']]))
        ->toBe("| Plan | Price |\n| --- | --- |\n| Pro \\| Team | 49<br>/ month |\n| Starter |  |")
        ->and(MarkdownText::table([['', '']]))->toBe('');
});
