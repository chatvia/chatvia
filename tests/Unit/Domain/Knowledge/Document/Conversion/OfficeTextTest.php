<?php

use Domain\Knowledge\Document\Conversion\OfficeText;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Office and plain-text files are read without an office suite or any other
 * program: each sheet becomes its own page with its rows as labelled lines,
 * and other text is cut into pages of about OfficeText::CHARS_PER_PAGE
 * characters at paragraph breaks.
 */
it('reads each sheet row as a labelled line, with formulas calculated', function (): void {
    $spreadsheet = new Spreadsheet;
    $prices = $spreadsheet->getActiveSheet()->setTitle('Prices');
    $prices->fromArray([['Price list 2026'], ['Plan', 'Price', ''], ['Starter', 49, ''], ['Pro | Team', 199, 'popular'], ['Total', '=SUM(B3:B4)']]);
    $spreadsheet->createSheet()->setTitle('Empty');
    $path = tempTestFile('book.xlsx', '');
    new Xlsx($spreadsheet)->save($path);

    expect(app(OfficeText::class)->pages($path, 'xlsx'))->toBe([
        "## Prices\n\nPrice list 2026\nPlan: Starter · Price: 49\nPlan: Pro | Team · Price: 199 · Column 3: popular\nPlan: Total · Price: 248",
        '## Empty',
    ]);
});

it('reads plain text as-is and cuts long text into pages at paragraph breaks', function (): void {
    $paragraph = Str::repeat('word ', 400);
    $path = tempTestFile('long.txt', implode("\n\n", [$paragraph, $paragraph, $paragraph]));

    $pages = app(OfficeText::class)->pages($path, 'txt');

    expect($pages)->toHaveCount(2)
        ->and($pages[0])->toBe($paragraph."\n\n".$paragraph)
        ->and($pages[1])->toBe($paragraph);
});
