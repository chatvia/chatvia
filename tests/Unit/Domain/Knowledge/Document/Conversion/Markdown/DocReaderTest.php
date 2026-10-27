<?php

use Domain\Knowledge\Document\Conversion\Markdown\DocReader;
use Illuminate\Support\Facades\File;
use Tests\Support\Knowledge\Document\OldOffice\CompoundFileWriter;
use Tests\Support\Knowledge\Document\OldOffice\WordBinaryFile;

/**
 * The built-in reader of old Word files (.doc). The files are built by
 * WordBinaryFile, which stores the text as a fast-saved file does: out of
 * order, with stale text between the pieces.
 */
function docrRead(string $path): string
{
    try {
        return new DocReader()->toMarkdown($path);
    } finally {
        File::delete($path);
    }
}

it('makes headings from the built-in heading styles, styles based on them, Title and outline levels', function (): void {
    $file = new WordBinaryFile()
        ->style(3, 0x0FFE, 1, 'Chapter')
        ->style(4, 62, 0, 'Title')
        ->paragraph('Visitor Policy', 4)
        ->paragraph('Scope', 1)
        ->paragraph('It covers every visitor.')
        ->paragraph('Rules', 3)
        ->paragraph('Parking', 0, WordBinaryFile::sprm(0x2640, 2))
        ->paragraph('Body text with an outline level of 9.', 2, WordBinaryFile::sprm(0x2640, 9));

    expect(docrRead($file->save()))->toBe(implode("\n\n", [
        '# Visitor Policy',
        '# Scope',
        'It covers every visitor.',
        '# Rules',
        '### Parking',
        'Body text with an outline level of 9.',
    ]));
});

it('numbers lists across their overrides, restarts them once, and nests bullets', function (): void {
    $file = new WordBinaryFile()
        ->listDefinition(7, [['bullet' => false, 'start' => 1], ['bullet' => true, 'start' => 1]])
        ->listDefinition(8, [['bullet' => true, 'start' => 1]]);
    $steps = $file->listOverride(7);
    $moreSteps = $file->listOverride(7);
    $restart = $file->listOverride(7, [0 => 5]);
    $bullets = $file->listOverride(8);

    $file->item('Sign in.', $steps)
        ->item('Show your badge.', $steps, 1)
        ->item('Wear the badge.', $moreSteps)
        ->paragraph('Later:')
        ->item('Hand it back.', $restart)
        ->item('Bring a pen.', $bullets);

    expect(docrRead($file->save()))->toBe(implode("\n", [
        '1. Sign in.',
        '    - Show your badge.',
        '2. Wear the badge.',
        '',
        'Later:',
        '',
        '5. Hand it back.',
        '',
        '- Bring a pen.',
    ]));
});

it('reads tables as pipe tables and a one-column table as paragraphs', function (): void {
    $file = new WordBinaryFile()
        ->row(['Role', 'Phone'])
        ->row(['Gate house', '2200'])
        ->paragraph('Between the tables.')
        ->row(['A box drawn around a note.']);

    expect(docrRead($file->save()))->toBe(implode("\n\n", [
        "| Role | Phone |\n| --- | --- |\n| Gate house | 2200 |",
        'Between the tables.',
        'A box drawn around a note.',
    ]));
});

it('reads a table inside a table cell into the cell', function (): void {
    $inner = WordBinaryFile::sprm(0x2416, 1).WordBinaryFile::sprm(0x6649, 2);
    $file = new WordBinaryFile()
        ->paragraph('Contacts', 0, WordBinaryFile::sprm(0x2416, 1), "\x07")
        ->paragraph('Day', 0, $inner, "\r")
        ->paragraph('Night', 0, $inner, "\r")
        ->paragraph('', 0, $inner.WordBinaryFile::sprm(0x244C, 1), "\r")
        ->paragraph('', 0, WordBinaryFile::sprm(0x2416, 1), "\x07")
        ->paragraph('', 0, WordBinaryFile::sprm(0x2416, 1).WordBinaryFile::sprm(0x2417, 1), "\x07");

    expect(docrRead($file->save()))->toBe("| Contacts | Day<br>Night |\n| --- | --- |");
});

it('keeps field results and footnotes, and leaves out field codes, deletions and stale text', function (): void {
    $file = new WordBinaryFile()
        ->paragraph(['Call 112 first.', ['note' => 'Then the gate house.'], ' Report ', ['deleted' => 'never '], 'every fire.'])
        ->paragraph('See page '.WordBinaryFile::FIELD_BEGIN.' PAGEREF _Toc1 \h '.WordBinaryFile::FIELD_SEPARATOR.'4'.WordBinaryFile::FIELD_END.'.')
        ->paragraph('Saved on '.WordBinaryFile::FIELD_BEGIN.' DATE '.WordBinaryFile::FIELD_END.'.');

    $markdown = docrRead($file->save());

    expect($markdown)->toBe(implode("\n\n", [
        'Call 112 first.[^1] Report every fire.',
        'See page 4.',
        'Saved on .',
        '[^1]: Then the gate house.',
    ]))->not->toContain('STALE');
});

it('reads Unicode text and 8-bit text in one document', function (): void {
    $file = new WordBinaryFile()
        ->paragraph('Plain ASCII in the 8-bit piece.')
        ->paragraph('Café, Привет, 日本語 in the Unicode piece.');

    expect(docrRead($file->save()))->toBe("Plain ASCII in the 8-bit piece.\n\nCafé, Привет, 日本語 in the Unicode piece.");
});

it('reads Word 6 and 95 files in the code page of their language', function (): void {
    $path = WordBinaryFile::word6("Привет\rСчёт № 5\r", 0x0419, 'CP1251');

    expect(docrRead($path))->toBe("Привет\n\nСчёт № 5");
});

it('refuses a password-protected file and a file that is not a Word document', function (): void {
    $locked = new WordBinaryFile()->paragraph('Secret.')->encrypted()->save();
    $other = tempTestFile('other.doc', CompoundFileWriter::write(['Workbook' => 'not a document']));

    expect(fn (): string => docrRead($locked))->toThrow(RuntimeException::class, 'password protected')
        ->and(fn (): string => docrRead($other))->toThrow(RuntimeException::class, 'not a Word document');
});
