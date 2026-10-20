<?php

use Domain\Knowledge\Document\Conversion\Markdown\MarkdownReaders;
use Tests\Support\OldOffice\PowerPointBinaryFile;
use Tests\Support\OldOffice\WordBinaryFile;

/**
 * The built-in readers by file extension.
 */
it('knows which formats it reads', function (): void {
    $readers = new MarkdownReaders;

    expect($readers->supports('DOCX'))->toBeTrue()
        ->and($readers->supports('odt'))->toBeTrue()
        ->and($readers->supports('epub'))->toBeTrue()
        ->and($readers->supports('fb2'))->toBeTrue()
        ->and($readers->supports('RTF'))->toBeTrue()
        ->and($readers->supports('doc'))->toBeTrue()
        ->and($readers->supports('ppt'))->toBeTrue()
        ->and($readers->supports('pptx'))->toBeFalse()
        ->and(fn (): string => $readers->toMarkdown(__FILE__, 'pptx'))->toThrow(InvalidArgumentException::class);
});

it('reads a file with the reader for its extension', function (): void {
    $path = create_temp_file('book.fb2', '<?xml version="1.0"?><FictionBook xmlns="http://www.gribuser.ru/xml/fictionbook/2.0"><body><section><p>Hello.</p></section></body></FictionBook>');

    expect(new MarkdownReaders()->toMarkdown($path, 'FB2'))->toBe('Hello.');

    @unlink($path);
});

it('reads an old Word file and gives an old presentation slide by slide', function (): void {
    $document = new WordBinaryFile()->paragraph('Hello from Word 97.')->save();
    $deck = new PowerPointBinaryFile()->slide(['One'])->slide(['Two'])->save();

    expect(new MarkdownReaders()->toMarkdown($document, 'DOC'))->toBe('Hello from Word 97.')
        ->and(new MarkdownReaders()->slides($deck))->toBe(['One', 'Two']);

    @unlink($document);
    @unlink($deck);
});
