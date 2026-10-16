<?php

use Domain\Knowledge\Document\Conversion\OfficeText;
use Illuminate\Support\Facades\Process;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Office and plain-text files are read without an office suite: slides and
 * sheets become their own pages, other text is cut into pages of about
 * OfficeText::CHARS_PER_PAGE characters. The pandoc and catdoc tests need
 * those programs and skip cleanly when they are not installed.
 */

/**
 * A minimal .pptx with one slide per entry (each a list of paragraphs).
 *
 * @param  array<int, list<string>>  $slides  slide number → paragraphs
 */
function officePptx(array $slides): string
{
    $path = create_temp_file('deck.pptx', '');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);

    foreach ($slides as $number => $paragraphs) {
        $body = implode('', array_map(
            fn (string $text): string => '<a:p><a:r><a:t>'.htmlspecialchars($text, ENT_XML1).'</a:t></a:r></a:p>',
            $paragraphs,
        ));

        $zip->addFromString(
            "ppt/slides/slide{$number}.xml",
            '<?xml version="1.0" encoding="UTF-8"?>'
            .'<p:sld xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:p="http://schemas.openxmlformats.org/presentationml/2006/main">'
            .'<p:cSld><p:spTree><p:sp><p:txBody>'.$body.'</p:txBody></p:sp></p:spTree></p:cSld></p:sld>',
        );
    }

    $zip->close();

    return $path;
}

it('reads one page per slide, in slide order, even past slide 9', function (): void {
    $deck = officePptx([10 => ['Last slide'], 2 => ['Second slide'], 1 => ['Welcome', 'Agenda for today']]);

    expect(app(OfficeText::class)->pages($deck, 'pptx'))
        ->toBe(["Welcome\nAgenda for today", 'Second slide', 'Last slide']);

    @unlink($deck);
});

it('reads one page per sheet, as a Markdown table with formulas calculated', function (): void {
    $spreadsheet = new Spreadsheet;
    $prices = $spreadsheet->getActiveSheet()->setTitle('Prices');
    $prices->fromArray([['Plan', 'Price'], ['Starter', 49], ['Pro | Team', 199], ['Total', '=SUM(B2:B3)']]);
    $spreadsheet->createSheet()->setTitle('Empty');

    $path = create_temp_file('book.xlsx', '');
    new Xlsx($spreadsheet)->save($path);

    expect(app(OfficeText::class)->pages($path, 'xlsx'))->toBe([
        "## Prices\n\n| Plan | Price |\n| --- | --- |\n| Starter | 49 |\n| Pro \\| Team | 199 |\n| Total | 248 |",
        '## Empty',
    ]);

    @unlink($path);
});

it('reads plain text as-is and cuts long text into pages at paragraph breaks', function (): void {
    $paragraph = str_repeat('word ', 400); // 2000 characters
    $path = create_temp_file('long.txt', implode("\n\n", [$paragraph, $paragraph, $paragraph]));

    $pages = app(OfficeText::class)->pages($path, 'txt');

    expect($pages)->toHaveCount(2)
        ->and($pages[0])->toBe($paragraph."\n\n".$paragraph)
        ->and($pages[1])->toBe($paragraph);

    @unlink($path);
});

it('reads an old Windows text file as UTF-8', function (): void {
    $path = create_temp_file('legacy.txt', mb_convert_encoding('Café prices: 5€', 'Windows-1252', 'UTF-8'));

    expect(app(OfficeText::class)->pages($path, 'txt'))->toBe(['Café prices: 5€']);

    @unlink($path);
});

it('gives one empty page for a file without text', function (): void {
    $path = create_temp_file('empty.csv', "\n\n");

    expect(app(OfficeText::class)->pages($path, 'csv'))->toBe(['']);

    @unlink($path);
});

it('reads a Word document as Markdown without image references', function (): void {
    if (! programInstalled('pandoc')) {
        $this->markTestSkipped('pandoc is not installed.');
    }

    $markdown = create_temp_file('source.md', "# Refunds\n\nWe refund within **14 days**.\n\n![logo](logo.png)\n\n| Plan | Days |\n| --- | --- |\n| Pro | 30 |");
    $docx = create_temp_file('policy.docx', '');
    Process::run([(string) config('services.bin.pandoc'), $markdown, '-o', $docx])->throw();

    $text = implode("\n\n", app(OfficeText::class)->pages($docx, 'docx'));

    expect($text)->toContain('# Refunds')
        ->toContain('**14 days**')
        ->toContain('| Pro')
        ->not->toContain('logo.png');

    @unlink($markdown);
    @unlink($docx);
});

it('knows which formats it reads', function (): void {
    $text = app(OfficeText::class);

    expect($text->supports('DOCX'))->toBeTrue()
        ->and($text->supports('ppt'))->toBeTrue()
        ->and($text->supports('pdf'))->toBeFalse()
        ->and($text->supports('epub'))->toBeFalse();
});

it('refuses a format it does not read', function (): void {
    $path = create_temp_file('archive.zip', 'bytes');

    expect(fn () => app(OfficeText::class)->pages($path, 'zip'))->toThrow(InvalidArgumentException::class, 'zip');

    @unlink($path);
});
