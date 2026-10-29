<?php

use Domain\Knowledge\Document\Conversion\Markdown\EpubReader;
use Illuminate\Support\Facades\File;
use Tests\Support\Knowledge\Document\EpubFile;

/**
 * The built-in EPUB reader: books are built here from an OPF package file
 * and XHTML chapters (EpubFile).
 */
function epubrRead(string $path): string
{
    try {
        return new EpubReader()->toMarkdown($path);
    } finally {
        File::delete($path);
    }
}

it('reads the chapters in spine order and skips the contents page', function (): void {
    $book = new EpubFile()
        ->chapter('nav.xhtml', '<nav epub:type="toc"><ol><li><a href="two.xhtml">Two</a></li></ol></nav>')
        ->chapter('Text/two.xhtml', '<h1>Chapter Two</h1><p>Second.</p>')
        ->chapter('Text/chapter one.xhtml', '<h1>Chapter One</h1><p>First&nbsp;words&mdash;and&#160;more.</p>')
        ->nav('nav.xhtml')
        ->spine('c0', 'c2', 'c1')
        ->save();

    expect(epubrRead($book))->toBe("# Chapter One\n\nFirst\u{00A0}words—and\u{00A0}more.\n\n# Chapter Two\n\nSecond.");
});

it('turns marked footnotes into notes at the end, without their back-links', function (): void {
    $book = new EpubFile()->chapter('one.xhtml', '<p>Bees<a epub:type="noteref" href="#fn1">1</a> make honey<sup><a href="#n2" epub:type="noteref">2</a></sup>.</p>'
        .'<aside epub:type="footnote" id="fn1"><p><a href="#back">1</a> About sixty thousand.</p></aside>'
        .'<section epub:type="footnotes"><hr/><ol><li id="n2"><p>When capped.<a href="#ref2" role="doc-backlink">↩︎</a></p></li></ol></section>')->save();

    expect(epubrRead($book))->toBe("Bees[^1] make honey[^2].\n\n[^1]: About sixty thousand.\n\n[^2]: When capped.");
});
