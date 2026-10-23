<?php

use Domain\Knowledge\Document\Conversion\EbookText;
use Domain\Knowledge\Document\Conversion\Pandoc;
use Mockery\MockInterface;

/**
 * EbookText reads EPUB with pandoc when it is installed and with the
 * built-in reader when it is not, and FB2 always with the built-in reader
 * (pandoc garbles windows-1251 books). Pandoc is mocked; the built-in
 * readers read small books made here.
 */
function ebtEpub(string $body): string
{
    $path = create_temp_file('book.epub', '');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('mimetype', 'application/epub+zip');
    $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OEBPS/content.opf"/></rootfiles></container>');
    $zip->addFromString('OEBPS/content.opf', '<?xml version="1.0"?><package xmlns="http://www.idpf.org/2007/opf"><manifest><item id="c1" href="one.xhtml" media-type="application/xhtml+xml"/></manifest><spine><itemref idref="c1"/></spine></package>');
    $zip->addFromString('OEBPS/one.xhtml', '<?xml version="1.0" encoding="utf-8"?><html xmlns="http://www.w3.org/1999/xhtml"><head><title>t</title></head><body>'.$body.'</body></html>');
    $zip->close();

    return $path;
}

function ebtFb2(string $section): string
{
    $xml = '<?xml version="1.0" encoding="windows-1251"?><FictionBook xmlns="http://www.gribuser.ru/xml/fictionbook/2.0"><body><section>'.$section.'</section></body></FictionBook>';

    return create_temp_file('book.fb2', mb_convert_encoding($xml, 'Windows-1251', 'UTF-8'));
}

it('reads EPUB with pandoc when it is installed', function (): void {
    config(['services.bin.pandoc' => '/bin/sh']);
    $path = ebtEpub('<h1>Chapter One</h1><p>First.</p>');
    $this->mock(Pandoc::class, fn (MockInterface $mock) => $mock->shouldReceive('toMarkdown')->once()->with($path, 'epub')->andReturn('Read by pandoc.'));

    expect(app(EbookText::class)->toMarkdown($path, 'EPUB'))->toBe('Read by pandoc.');

    @unlink($path);
});

it('reads EPUB itself when pandoc is not installed', function (): void {
    config(['services.bin.pandoc' => '/nowhere/pandoc']);
    $path = ebtEpub('<h1>Chapter One</h1><p>First.</p>');
    $this->mock(Pandoc::class, fn (MockInterface $mock) => $mock->shouldNotReceive('toMarkdown'));

    expect(app(EbookText::class)->toMarkdown($path, 'epub'))->toBe("# Chapter One\n\nFirst.");

    @unlink($path);
});

it('reads FB2 itself even when pandoc is installed', function (): void {
    config(['services.bin.pandoc' => '/bin/sh']);
    $path = ebtFb2('<title><p>Глава 1</p></title><p>Весной мы сажаем картофель.</p>');
    $this->mock(Pandoc::class, fn (MockInterface $mock) => $mock->shouldNotReceive('toMarkdown'));

    expect(app(EbookText::class)->toMarkdown($path, 'fb2'))->toBe("## Глава 1\n\nВесной мы сажаем картофель.");

    @unlink($path);
});

it('reads only ebooks', function (): void {
    $ebookText = app(EbookText::class);

    expect($ebookText->supports('EPUB'))->toBeTrue()
        ->and($ebookText->supports('fb2'))->toBeTrue()
        ->and($ebookText->supports('rtf'))->toBeFalse()
        ->and(fn (): string => $ebookText->toMarkdown(__FILE__, 'rtf'))->toThrow(InvalidArgumentException::class);
});
