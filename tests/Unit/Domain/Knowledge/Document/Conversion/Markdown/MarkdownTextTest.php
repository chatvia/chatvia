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

it('indents list items four spaces a level and lines up their later lines', function (): void {
    expect(MarkdownText::item(0, '-', 'One'))->toBe('- One')
        ->and(MarkdownText::item(1, '10.', "Two\nlines"))->toBe("    10. Two\n        lines");
});

it('writes pipe tables with the first row as the header', function (): void {
    expect(MarkdownText::table([['Plan', '', 'Price'], ['Pro | Team', '', "49\n/ month"], ['', '', ''], ['Starter']]))
        ->toBe("| Plan | Price |\n| --- | --- |\n| Pro \\| Team | 49<br>/ month |\n| Starter |  |")
        ->and(MarkdownText::table([['', '']]))->toBe('');
});

it('reads a one-column table as paragraphs and a wider one as a pipe table', function (): void {
    expect(MarkdownText::tableBlocks([['A note'], ["in a box\n# not a heading"]]))->toBe([
        [MarkdownText::BLOCK, 'A note'],
        [MarkdownText::BLOCK, 'in a box'],
        [MarkdownText::BLOCK, '\\# not a heading'],
    ])->and(MarkdownText::tableBlocks([['Plan', 'Price'], ['Pro']]))
        ->toBe([[MarkdownText::BLOCK, "| Plan | Price |\n| --- | --- |\n| Pro |  |"]])
        ->and(MarkdownText::tableBlocks([]))->toBe([]);
});

it('joins list items by one line and other blocks by a blank line', function (): void {
    expect(MarkdownText::join([
        [MarkdownText::BLOCK, 'Intro'],
        [MarkdownText::ITEM, '- a'],
        [MarkdownText::ITEM, '- b'],
        [MarkdownText::BLOCK, ''],
        [MarkdownText::BLOCK, 'End'],
    ]))->toBe("Intro\n\n- a\n- b\n\nEnd");
});
