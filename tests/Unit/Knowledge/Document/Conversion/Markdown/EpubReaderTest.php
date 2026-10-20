<?php

use Domain\Knowledge\Document\Conversion\Markdown\EpubReader;

/**
 * The built-in EPUB reader: books are built here from an OPF package file
 * and XHTML chapters.
 *
 * @param  array<string, string>  $chapters  path under OEBPS/ → XHTML body
 * @param  list<string>  $spine  manifest ids in reading order
 */
function epubrFile(array $chapters, array $spine, string $nav = ''): string
{
    $manifest = '';

    foreach (array_keys($chapters) as $index => $href) {
        $manifest .= '<item id="c'.$index.'" href="'.str_replace(' ', '%20', $href).'" media-type="application/xhtml+xml"'.($href === $nav ? ' properties="nav"' : '').'/>';
    }

    $path = create_temp_file('book.epub', '');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('mimetype', 'application/epub+zip');
    $zip->addFromString('META-INF/container.xml', '<?xml version="1.0"?><container xmlns="urn:oasis:names:tc:opendocument:xmlns:container"><rootfiles><rootfile full-path="OEBPS/content.opf"/></rootfiles></container>');
    $zip->addFromString('OEBPS/content.opf', '<?xml version="1.0"?><package xmlns="http://www.idpf.org/2007/opf"><manifest>'.$manifest.'</manifest><spine>'
        .implode('', array_map(static fn (string $id): string => '<itemref idref="'.$id.'"/>', $spine)).'</spine></package>');

    foreach ($chapters as $href => $body) {
        $zip->addFromString('OEBPS/'.$href, '<?xml version="1.0" encoding="utf-8"?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.1//EN" "http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd">'
            .'<html xmlns="http://www.w3.org/1999/xhtml" xmlns:epub="http://www.idpf.org/2007/ops"><head><title>t</title><style>p { margin: 0 }</style></head><body>'.$body.'</body></html>');
    }

    $zip->close();

    return $path;
}

function epubrRead(string $path): string
{
    try {
        return new EpubReader()->toMarkdown($path);
    } finally {
        @unlink($path);
    }
}

it('reads the chapters in spine order and skips the contents page', function (): void {
    $book = epubrFile([
        'nav.xhtml' => '<nav epub:type="toc"><ol><li><a href="two.xhtml">Two</a></li></ol></nav>',
        'Text/two.xhtml' => '<h1>Chapter Two</h1><p>Second.</p>',
        'Text/chapter one.xhtml' => '<h1>Chapter One</h1><p>First&nbsp;words&mdash;and&#160;more.</p>',
    ], ['c0', 'c2', 'c1'], nav: 'nav.xhtml');

    expect(epubrRead($book))->toBe("# Chapter One\n\nFirst\u{00A0}words—and\u{00A0}more.\n\n# Chapter Two\n\nSecond.");
});

it('turns marked footnotes into notes at the end, without their back-links', function (): void {
    $book = epubrFile([
        'one.xhtml' => '<p>Bees<a epub:type="noteref" href="#fn1">1</a> make honey<sup><a href="#n2" epub:type="noteref">2</a></sup>.</p>'
            .'<aside epub:type="footnote" id="fn1"><p><a href="#back">1</a> About sixty thousand.</p></aside>'
            .'<section epub:type="footnotes"><hr/><ol><li id="n2"><p>When capped.<a href="#ref2" role="doc-backlink">↩︎</a></p></li></ol></section>',
    ], ['c0']);

    expect(epubrRead($book))->toBe("Bees[^1] make honey[^2].\n\n[^1]: About sixty thousand.\n\n[^2]: When capped.");
});

it('drops images and in-book links, keeps web links, tables and separate definition terms', function (): void {
    $book = epubrFile([
        'one.xhtml' => '<p><img src="a.png" alt="A bee"/>See <a href="two.xhtml#x">part two</a> or <a href="https://bees.example/">the site</a>.</p>'
            .'<dl><dt>Smoker</dt><dd>Calms the bees.</dd></dl>'
            .'<table><tr><th>Honey</th><th>Season</th></tr><tr><td>Heather</td><td>August</td></tr></table>',
    ], ['c0']);

    $markdown = epubrRead($book);

    expect($markdown)->toContain('See part two or [the site](https://bees.example/).')
        ->toContain("Smoker\n")
        ->toContain("| Honey | Season |\n|---|---|\n| Heather | August |")
        ->not->toContain('a.png')
        ->not->toContain('SmokerCalms');
});

it('refuses a book without a package file', function (): void {
    $path = create_temp_file('broken.epub', '');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('mimetype', 'application/epub+zip');
    $zip->close();

    expect(fn (): string => epubrRead($path))->toThrow(RuntimeException::class);
});
