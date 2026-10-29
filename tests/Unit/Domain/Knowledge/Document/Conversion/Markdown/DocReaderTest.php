<?php

use Domain\Knowledge\Document\Conversion\Markdown\DocReader;
use Illuminate\Support\Facades\File;
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
