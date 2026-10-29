<?php

use Domain\Knowledge\Document\Conversion\Markdown\DocxReader;
use Illuminate\Support\Facades\File;
use Tests\Support\Knowledge\Document\DocxFile;

/**
 * The built-in Word reader: documents are built here from WordprocessingML
 * strings (DocxFile), one case per feature pandoc handles and a simple reader
 * loses.
 */
function docxrRead(string $path): string
{
    try {
        return new DocxReader()->toMarkdown($path);
    } finally {
        File::delete($path);
    }
}

it('makes headings from style names and outline levels, so localised style ids count', function (): void {
    $styles = '<w:styles '.DocxFile::NAMESPACES.'>'
        .'<w:style w:type="paragraph" w:styleId="berschrift1"><w:name w:val="heading 1"/></w:style>'
        .'<w:style w:type="paragraph" w:styleId="Section"><w:name w:val="Section"/><w:pPr><w:outlineLvl w:val="1"/></w:pPr></w:style>'
        .'<w:style w:type="paragraph" w:styleId="SubSection"><w:name w:val="Sub section"/><w:basedOn w:val="Section"/></w:style>'
        .'</w:styles>';

    $body = DocxFile::paragraph('Overview', '<w:pStyle w:val="berschrift1"/>')
        .DocxFile::paragraph('Prices', '<w:pStyle w:val="Section"/>')
        .DocxFile::paragraph('Inherited', '<w:pStyle w:val="SubSection"/>')
        .DocxFile::paragraph('Direct', '<w:outlineLvl w:val="2"/>')
        .DocxFile::paragraph('# not a heading');

    expect(docxrRead(new DocxFile($body, ['styles.xml' => $styles], '<Relationship Id="s" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>')->save()))
        ->toBe("# Overview\n\n## Prices\n\n## Inherited\n\n### Direct\n\n\\# not a heading");
});

it('accepts tracked changes and hides field codes, comments and deleted rows', function (): void {
    $body = '<w:p><w:r><w:t xml:space="preserve">We approved </w:t></w:r>'
        .'<w:del w:id="1" w:author="A"><w:r><w:delText>the old plan</w:delText></w:r></w:del>'
        .'<w:ins w:id="2" w:author="A"><w:r><w:t>the new plan</w:t></w:r></w:ins>'
        .'<w:moveFrom w:id="3" w:author="A"><w:r><w:t> moved away</w:t></w:r></w:moveFrom>'
        .'<w:r><w:t xml:space="preserve"> on page </w:t></w:r>'
        .'<w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText> PAGE </w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:t>4</w:t></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r>'
        .'<w:commentRangeStart w:id="5"/><w:r><w:t>.</w:t></w:r><w:commentRangeEnd w:id="5"/><w:r><w:commentReference w:id="5"/></w:r></w:p>';

    expect(docxrRead(new DocxFile($body)->save()))->toBe('We approved the new plan on page 4.');
});
