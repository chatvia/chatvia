<?php

use Domain\Knowledge\Document\Conversion\OfficeText;
use Illuminate\Support\Facades\Process;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\OldOffice\CompoundFileWriter;
use Tests\Support\OldOffice\PowerPointBinaryFile;
use Tests\Support\OldOffice\WordBinaryFile;

/**
 * Office and plain-text files are read without an office suite or any other
 * program: slides and sheets become their own pages, other text is cut into
 * pages of about OfficeText::CHARS_PER_PAGE characters. pandoc reads DOCX and
 * ODT when it is installed (that test skips without it); catdoc, catppt and
 * pandoc are a backup for the old formats and RTF, faked here.
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

it('reads old Word, PowerPoint and RTF files itself, with no program installed', function (): void {
    config(['services.bin.pandoc' => '/nowhere/pandoc', 'services.bin.catdoc' => '/nowhere/catdoc', 'services.bin.catppt' => '/nowhere/catppt']);
    $doc = new WordBinaryFile()->paragraph('Refunds', 1)->paragraph('We refund within 14 days.')->save();
    $ppt = new PowerPointBinaryFile()->slide(['Welcome'], ['Agenda'])->slide(['Prices'])->save();
    $rtf = create_temp_file('letter.rtf', '{\\rtf1\\ansi\\pard Dear visitor,\\par\\pard thank you.\\par}');

    expect(app(OfficeText::class)->pages($doc, 'doc'))->toBe(["# Refunds\n\nWe refund within 14 days."])
        ->and(app(OfficeText::class)->pages($ppt, 'PPT'))->toBe(["Welcome\nAgenda", 'Prices'])
        ->and(app(OfficeText::class)->pages($rtf, 'rtf'))->toBe(["Dear visitor,\n\nthank you."]);

    @unlink($doc);
    @unlink($ppt);
    @unlink($rtf);
});

it('reads a Word (.docx) file itself when pandoc is not installed', function (): void {
    config(['services.bin.pandoc' => '/nowhere/pandoc']);
    $docx = create_temp_file('policy.docx', '');
    $zip = new ZipArchive;
    $zip->open($docx, ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Refunds take 14 days.</w:t></w:r></w:p></w:body></w:document>');
    $zip->close();

    expect(app(OfficeText::class)->pages($docx, 'docx'))->toBe(['Refunds take 14 days.']);

    @unlink($docx);
});

it('reads a file its built-in reader cannot read with the installed program, as a backup', function (): void {
    config(['services.bin.catdoc' => '/bin/sh', 'services.bin.catppt' => '/bin/sh']);
    Process::fake(['*' => Process::result(output: "Text the program found.\n\nSecond paragraph.")]);
    $unreadable = create_temp_file('old.doc', CompoundFileWriter::write(['Unknown' => 'no Word or PowerPoint stream']));

    expect(array_map(trim(...), app(OfficeText::class)->pages($unreadable, 'doc')))->toBe(["Text the program found.\n\nSecond paragraph."])
        ->and(array_map(trim(...), app(OfficeText::class)->pages($unreadable, 'ppt')))->toBe(["Text the program found.\n\nSecond paragraph."]);

    @unlink($unreadable);
});

it('gives the built-in reader\'s error when the backup program cannot read the file either', function (): void {
    config(['services.bin.catdoc' => '/bin/sh']);
    Process::fake(['*' => Process::result(errorOutput: 'File is encrypted', exitCode: 1)]);
    $locked = new WordBinaryFile()->paragraph('Secret.')->encrypted()->save();

    expect(fn () => app(OfficeText::class)->pages($locked, 'doc'))->toThrow(RuntimeException::class, 'password protected');

    @unlink($locked);
});

it('needs no program for any format', function (string $extension): void {
    expect(app(OfficeText::class)->programFor($extension))->toBeNull();
})->with(['docx', 'odt', 'rtf', 'doc', 'ppt', 'pptx', 'xlsx', 'txt']);

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
