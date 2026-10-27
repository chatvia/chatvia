<?php

use Domain\Knowledge\Document\Conversion\Markdown\OdtReader;
use Illuminate\Support\Facades\File;

/**
 * The built-in OpenDocument reader: files are built here from content.xml
 * strings.
 */
function odtrFile(string $text, string $styles = ''): string
{
    $ns = 'xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:table="urn:oasis:names:tc:opendocument:xmlns:table:1.0" xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:dc="http://purl.org/dc/elements/1.1/"';

    $path = create_temp_file('document.odt', '');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.text');
    $zip->addFromString('content.xml', '<?xml version="1.0"?><office:document-content '.$ns.'><office:automatic-styles>'.$styles.'</office:automatic-styles><office:body><office:text>'.$text.'</office:text></office:body></office:document-content>');
    $zip->close();

    return $path;
}

function odtrRead(string $path): string
{
    try {
        return new OdtReader()->toMarkdown($path);
    } finally {
        File::delete($path);
    }
}

it('reads headings and collapses spaces the way OpenDocument does', function (): void {
    $text = '<text:h text:outline-level="2"><text:number>1.</text:number>Prices</text:h>'
        .'<text:p>Revenue
            grew by <text:span>12</text:span> percent<text:s text:c="3"/>in Ber<text:bookmark text:name="x"/>lin.</text:p>'
        .'<text:p>Name:<text:tab/>Alice<text:line-break/>Role: lead</text:p>';

    expect(odtrRead(odtrFile($text)))->toBe("## Prices\n\nRevenue grew by 12 percent in Berlin.\n\nName: Alice\nRole: lead");
});

it('leaves out tracked deletions, comments and the table of contents, and keeps field values', function (): void {
    $text = '<text:tracked-changes><text:changed-region text:id="c1"><text:deletion><text:p>the old plan</text:p></text:deletion></text:changed-region></text:tracked-changes>'
        .'<text:table-of-content><text:index-body><text:p>Summary<text:tab/>1</text:p></text:index-body></text:table-of-content>'
        .'<text:p>We approved <text:change text:change-id="c1"/><text:change-start text:change-id="c2"/>the new plan<text:change-end text:change-id="c2"/>.<office:annotation><text:p>Check this.</text:p></office:annotation></text:p>'
        .'<text:p>Dated <text:date text:date-value="2026-10-20">20 October 2026</text:date> by <text:author-name>Dana</text:author-name> for <text:placeholder>&lt;Customer&gt;</text:placeholder>, page <text:page-number>2</text:page-number>, <text:ruby><text:ruby-base>川辺</text:ruby-base><text:ruby-text>かわべ</text:ruby-text></text:ruby>.</text:p>';

    expect(odtrRead(odtrFile($text)))->toBe("We approved the new plan.\n\nDated 20 October 2026 by Dana for \\<Customer>, page 2, 川辺.");
});

it('reads lists with their bullet or number style, continuing numbering when asked', function (): void {
    $styles = '<text:list-style style:name="L1"><text:list-level-style-number text:level="1" style:num-format="1"/><text:list-level-style-bullet text:level="2" text:bullet-char="•"/></text:list-style>';
    $item = fn (string $text, string $inner = ''): string => '<text:list-item><text:number>9.</text:number><text:p>'.$text.'</text:p>'.$inner.'</text:list-item>';

    $text = '<text:list text:style-name="L1">'.$item('Close the books', '<text:list>'.$item('By hand').'</text:list>').$item('Publish').'</text:list>'
        .'<text:p>Then:</text:p>'
        .'<text:list text:style-name="L1" text:continue-numbering="true">'.$item('Present').'</text:list>'
        .'<text:list text:style-name="L1">'.$item('Start again').'</text:list>';

    expect(odtrRead(odtrFile($text, $styles)))->toBe("1. Close the books\n    - By hand\n2. Publish\n\nThen:\n\n3. Present\n\n1. Start again");
});

it('reads tables with covered cells and flattens a table inside a cell', function (): void {
    $cell = fn (string $text, string $attributes = ''): string => "<table:table-cell {$attributes}><text:p>{$text}</text:p></table:table-cell>";

    $text = '<table:table>'
        .'<table:table-header-rows><table:table-row>'.$cell('Region').$cell('Q3').$cell('Q4').'</table:table-row></table:table-header-rows>'
        .'<table:table-row>'.$cell('North').$cell('Estimate', 'table:number-columns-spanned="2"').'<table:covered-table-cell/></table:table-row>'
        .'<table:table-row>'.$cell('South').'<table:table-cell><table:table><table:table-row>'.$cell('Inner A').$cell('Inner B').'</table:table-row></table:table></table:table-cell>'.'<table:table-cell table:number-columns-repeated="1000"/></table:table-row>'
        .'</table:table>';

    expect(odtrRead(odtrFile($text)))->toBe("| Region | Q3 | Q4 |\n| --- | --- | --- |\n| North | Estimate |  |\n| South | Inner A Inner B |  |");
});

it('lists notes at the end and reads text boxes and links', function (): void {
    $text = '<text:p>Margins improved<text:note text:note-class="footnote"><text:note-citation>1</text:note-citation><text:note-body><text:p>Unaudited.</text:p></text:note-body></text:note> and costs fell<text:note text:note-class="endnote"><text:note-citation>i</text:note-citation><text:note-body><text:p>Ledger.</text:p></text:note-body></text:note>.</text:p>'
        .'<text:p>See the box.<draw:frame><draw:text-box><text:p>Key figure</text:p></draw:text-box></draw:frame><draw:frame><draw:image xlink:href="logo.png"/></draw:frame></text:p>'
        .'<text:p>Visit <text:a xlink:href="https://example.com/">our site</text:a> or <text:a xlink:href="#intro">the start</text:a>.</text:p>';

    expect(odtrRead(odtrFile($text)))->toBe("Margins improved[^1] and costs fell[^2].\n\nSee the box.\nKey figure\n\nVisit [our site](https://example.com/) or the start.\n\n[^1]: Unaudited.\n\n[^2]: Ledger.");
});

it('refuses a file without content', function (): void {
    $path = tempTestFile('empty.odt');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.text');
    $zip->close();

    expect(fn (): string => odtrRead($path))->toThrow(RuntimeException::class);
});
