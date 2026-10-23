<?php

use Domain\Knowledge\Document\Conversion\EbookText;
use Domain\Knowledge\Document\Conversion\OfficeText;
use Domain\Knowledge\Source\File\Data\FileAnalysisData;
use Domain\Knowledge\Source\File\Services\FileAnalyzer;
use Mockery\MockInterface;

/**
 * FileAnalyzer routes a file to a "length" (pages or minutes) at upload time:
 * images count as one page, office files and ebooks are counted
 * from their text (OfficeText and EbookText are mocked here), and a file that
 * cannot be read is estimated from its size so the upload still goes on. A
 * genuinely unsupported type is rejected.
 */

/** Write a real 1x1 PNG so File::mimeType() reports image/png. */
function faTempPng(): string
{
    $path = tempnam(sys_get_temp_dir(), 'fa_').'.png';
    file_put_contents($path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
    ));

    return $path;
}

it('counts an image as a single page', function (): void {
    $path = faTempPng();

    $info = app(FileAnalyzer::class)->analyze($path);

    expect($info->length)->toBe(1);

    @unlink($path);
});

it('rejects a file type that is neither OCR-supported nor media', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'fa_').'.xyz';
    file_put_contents($path, 'just some bytes');

    expect(fn (): FileAnalysisData => app(FileAnalyzer::class)->analyze($path))->toThrow(LogicException::class);

    @unlink($path);
});

it('counts the pages of an office file, or estimates them from its size when it cannot be read', function (bool $readable, int $pages): void {
    // 7,000 bytes: three pages by size.
    $path = create_temp_file('policy.docx', str_repeat('x', 7000));
    $this->mock(OfficeText::class, function (MockInterface $mock) use ($path, $readable): void {
        $expectation = $mock->shouldReceive('pages')->once()->with($path, 'docx');
        $readable ? $expectation->andReturn(['One.', 'Two.', 'Three.', 'Four.', 'Five.']) : $expectation->andThrow(new RuntimeException('password protected'));
    });

    expect(app(FileAnalyzer::class)->analyze($path)->length)->toBe($pages);

    @unlink($path);
})->with([
    'readable' => [true, 5],
    'unreadable' => [false, 3],
]);

it('counts an ebook in pages of 3,000 characters, or estimates it from its size when it cannot be read', function (bool $readable, int $pages): void {
    // 7,000 bytes: three pages by size.
    $path = create_temp_file('book.epub', str_repeat('x', 7000));
    $this->mock(EbookText::class, function (MockInterface $mock) use ($path, $readable): void {
        $expectation = $mock->shouldReceive('toMarkdown')->once()->with($path, 'epub');
        $readable ? $expectation->andReturn(str_repeat('word ', 2000)) : $expectation->andThrow(new RuntimeException('malformed ebook'));
    });

    expect(app(FileAnalyzer::class)->analyze($path)->length)->toBe($pages);

    @unlink($path);
})->with([
    'readable' => [true, 4],
    'unreadable' => [false, 3],
]);

it('counts a book in a script outside ASCII by its characters', function (): void {
    // 6,000 Cyrillic characters: about 12,000 bytes, but two pages of text.
    $path = create_temp_file('book.fb2', '<?xml version="1.0" encoding="utf-8"?><FictionBook xmlns="http://www.gribuser.ru/xml/fictionbook/2.0"><body><section><p>'
        .str_repeat('Весной ', 857).'</p></section></body></FictionBook>');

    expect(app(FileAnalyzer::class)->analyze($path)->length)->toBe(2);

    @unlink($path);
});
