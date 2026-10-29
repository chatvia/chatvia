<?php

use Domain\Knowledge\Document\Conversion\EbookText;
use Domain\Knowledge\Document\Conversion\Pandoc;
use Mockery\MockInterface;
use Tests\Support\Knowledge\Document\EpubFile;

/**
 * EbookText reads EPUB with the built-in reader when pandoc is not installed,
 * and FB2 always with the built-in reader (pandoc garbles windows-1251
 * books). Pandoc is mocked; the built-in readers read small books made here,
 * deleted after each test.
 */
function ebtFb2(string $section): string
{
    $xml = '<?xml version="1.0" encoding="windows-1251"?><FictionBook xmlns="http://www.gribuser.ru/xml/fictionbook/2.0"><body><section>'.$section.'</section></body></FictionBook>';

    return tempTestFile('book.fb2', mb_convert_encoding($xml, 'Windows-1251', 'UTF-8'));
}

it('reads EPUB itself when pandoc is not installed', function (): void {
    config(['services.bin.pandoc' => '/nowhere/pandoc']);
    $path = deleteAfterTest(new EpubFile()->chapter('one.xhtml', '<h1>Chapter One</h1><p>First.</p>')->save());
    $this->mock(Pandoc::class, fn (MockInterface $mock) => $mock->shouldNotReceive('toMarkdown'));

    expect(app(EbookText::class)->toMarkdown($path, 'epub'))->toBe("# Chapter One\n\nFirst.");
});

it('reads FB2 itself even when pandoc is installed', function (): void {
    config(['services.bin.pandoc' => '/bin/sh']);
    $path = ebtFb2('<title><p>Глава 1</p></title><p>Весной мы сажаем картофель.</p>');
    $this->mock(Pandoc::class, fn (MockInterface $mock) => $mock->shouldNotReceive('toMarkdown'));

    expect(app(EbookText::class)->toMarkdown($path, 'fb2'))->toBe("## Глава 1\n\nВесной мы сажаем картофель.");
});
