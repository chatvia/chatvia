<?php

use Domain\Knowledge\Document\Conversion\Markdown\Fb2Reader;
use Illuminate\Support\Facades\File;

/**
 * The built-in FictionBook reader: books are built here as XML strings.
 */
function fb2rRead(string $xml): string
{
    $path = create_temp_file('book.fb2', $xml);

    try {
        return new Fb2Reader()->toMarkdown($path);
    } finally {
        File::delete($path);
    }
}

function fb2rBook(string $body, string $encoding = 'utf-8', string $notes = ''): string
{
    return '<?xml version="1.0" encoding="'.$encoding.'"?>'
        .'<FictionBook xmlns="http://www.gribuser.ru/xml/fictionbook/2.0" xmlns:l="http://www.w3.org/1999/xlink">'
        .'<description><title-info><book-title>Garden</book-title></title-info></description>'
        .'<body>'.$body.'</body>'.$notes
        .'<binary id="pic" content-type="image/png">iVBORw0KGgo=</binary></FictionBook>';
}

it('makes headings from the body and section titles by depth', function (): void {
    $body = '<title><p>The Garden</p><p>A year</p></title>'
        .'<section><title><p>Spring</p></title><p>Plant   potatoes.</p><empty-line/><subtitle>In April</subtitle><p>Sow peas.</p>'
        .'<section><title><p>The greenhouse</p></title><p>Warm.</p><image l:href="#pic"/></section></section>';

    expect(fb2rRead(fb2rBook($body)))->toBe("# The Garden A year\n\n## Spring\n\nPlant potatoes.\n\n### In April\n\nSow peas.\n\n### The greenhouse\n\nWarm.");
});

it('reads a windows-1251 book as the encoding it declares', function (): void {
    $xml = mb_convert_encoding(fb2rBook('<section><title><p>Глава 1. Весна</p></title><p>Весной мы сажаем картофель.</p></section>', 'windows-1251'), 'Windows-1251', 'UTF-8');

    expect(fb2rRead($xml))->toBe("## Глава 1. Весна\n\nВесной мы сажаем картофель.");
});

it('turns note links into notes at the end, and reads poems, citations and tables', function (): void {
    $body = '<section><p>We store apples cold.<a l:href="#n1" type="note">[1]</a></p>'
        .'<poem><title><p>Autumn</p></title><stanza><v>Leaves fall,</v><v>I walk.</v></stanza></poem>'
        .'<cite><p>Pick before the frost.</p><text-author>Grandmother</text-author></cite>'
        .'<table><tr><th>Crop</th><th>Sown</th></tr><tr><td>Tomatoes</td><td>March</td></tr></table></section>';
    $notes = '<body name="notes"><section id="n1"><title><p>1</p></title><p>Up to five months.</p></section></body>';

    expect(fb2rRead(fb2rBook($body, notes: $notes)))->toBe(
        "We store apples cold.[^1]\n\n### Autumn\n\nLeaves fall,\nI walk.\n\n> Pick before the frost.\n>\n> Grandmother\n\n| Crop | Sown |\n| --- | --- |\n| Tomatoes | March |\n\n[^1]: Up to five months.",
    );
});

it('refuses a file that is not XML', function (): void {
    expect(fn (): string => fb2rRead('not xml'))->toThrow(RuntimeException::class);
});
