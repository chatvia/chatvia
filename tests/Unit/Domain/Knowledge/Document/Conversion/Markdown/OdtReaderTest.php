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
