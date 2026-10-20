<?php

use Domain\Knowledge\Document\Conversion\Markdown\MarkdownReaders;

/**
 * The built-in readers by file extension.
 */
it('knows which formats it reads', function (): void {
    $readers = new MarkdownReaders;

    expect($readers->supports('DOCX'))->toBeTrue()
        ->and($readers->supports('odt'))->toBeTrue()
        ->and($readers->supports('epub'))->toBeTrue()
        ->and($readers->supports('fb2'))->toBeTrue()
        ->and($readers->supports('rtf'))->toBeFalse()
        ->and(fn (): string => $readers->toMarkdown(__FILE__, 'rtf'))->toThrow(InvalidArgumentException::class);
});

it('reads a file with the reader for its extension', function (): void {
    $path = create_temp_file('book.fb2', '<?xml version="1.0"?><FictionBook xmlns="http://www.gribuser.ru/xml/fictionbook/2.0"><body><section><p>Hello.</p></section></body></FictionBook>');

    expect(new MarkdownReaders()->toMarkdown($path, 'FB2'))->toBe('Hello.');

    @unlink($path);
});
