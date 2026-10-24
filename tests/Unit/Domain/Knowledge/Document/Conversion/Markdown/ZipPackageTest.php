<?php

use Domain\Knowledge\Document\Conversion\Markdown\ZipPackage;

/**
 * Read-only, size-checked access to the parts of a zip-based document.
 */
it('reads parts by name, in any case, and gives null for a missing part', function (): void {
    $path = tempTestFile('package.zip');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('word/document.xml', '<?xml version="1.0"?><doc>text</doc>');
    $zip->addFromString('word/broken.xml', '<doc>');
    $zip->close();

    $package = ZipPackage::open($path);

    expect($package->read('/word/document.xml'))->toContain('<doc>text</doc>')
        ->and($package->read('Word/Document.XML'))->toContain('<doc>text</doc>')
        ->and($package->read('word/missing.xml'))->toBeNull()
        ->and($package->xml('word/document.xml')?->documentElement?->textContent)->toBe('text')
        ->and($package->xml('word/broken.xml'))->toBeNull();

    $package->close();
});

it('refuses a file that is not a zip', function (): void {
    $path = tempTestFile('fake.zip', 'plain text');

    expect(fn (): ZipPackage => ZipPackage::open($path))->toThrow(RuntimeException::class);
});

it('resolves a link against the folder of the part that holds it', function (): void {
    expect(ZipPackage::resolve('OEBPS/content.opf', 'Text/chapter%201.xhtml#top'))->toBe('OEBPS/Text/chapter 1.xhtml')
        ->and(ZipPackage::resolve('OEBPS/Text/one.xhtml', '../Images/a.png'))->toBe('OEBPS/Images/a.png')
        ->and(ZipPackage::resolve('word/document.xml', '/word/styles.xml'))->toBe('word/styles.xml')
        ->and(ZipPackage::resolve('', 'word/document.xml'))->toBe('word/document.xml');
});
