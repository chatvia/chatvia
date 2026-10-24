<?php

use Domain\Knowledge\Document\Conversion\Markdown\DocxReader;
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
        @unlink($path);
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

it('keeps words apart at tabs and breaks, and joins them at soft hyphens', function (): void {
    $body = '<w:p><w:r><w:t>Name:</w:t><w:tab/><w:t>Alice</w:t><w:br/><w:t>Role:</w:t><w:cr/><w:t>long</w:t></w:r><w:r><w:softHyphen/><w:t>standing,</w:t><w:noBreakHyphen/><w:t>term</w:t></w:r></w:p>';

    expect(docxrRead(new DocxFile($body)->save()))->toBe("Name: Alice\nRole:\nlongstanding,-term");
});

it('numbers lists the way Word does: per abstract list, restarted by a start override, apart from the next list', function (): void {
    $numbering = '<w:numbering '.DocxFile::NAMESPACES.'>'
        .'<w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/></w:lvl><w:lvl w:ilvl="1"><w:start w:val="1"/><w:numFmt w:val="bullet"/></w:lvl></w:abstractNum>'
        .'<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>'
        .'<w:num w:numId="2"><w:abstractNumId w:val="0"/><w:lvlOverride w:ilvl="0"><w:startOverride w:val="1"/></w:lvlOverride></w:num>'
        .'<w:num w:numId="3"><w:abstractNumId w:val="0"/><w:lvlOverride w:ilvl="0"><w:startOverride w:val="1"/></w:lvlOverride></w:num>'
        .'<w:abstractNum w:abstractNumId="1"><w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl></w:abstractNum>'
        .'<w:num w:numId="4"><w:abstractNumId w:val="1"/></w:num>'
        .'</w:numbering>';
    $item = fn (string $text, int $numId, int $level = 0): string => DocxFile::paragraph($text, "<w:numPr><w:ilvl w:val=\"{$level}\"/><w:numId w:val=\"{$numId}\"/></w:numPr>");

    $body = $item('Sign in', 2).$item('Get a badge', 1).$item('Blue for visitors', 1, 1).$item('Wear it', 1)
        .DocxFile::paragraph('Then:')
        .$item('Stay on the path', 3).$item('Leave by six', 1).$item('Bring water', 4);

    expect(docxrRead(new DocxFile($body, ['numbering.xml' => $numbering], '<Relationship Id="n" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>')->save()))
        ->toBe("1. Sign in\n2. Get a badge\n    - Blue for visitors\n3. Wear it\n\nThen:\n\n1. Stay on the path\n2. Leave by six\n\n- Bring water");
});

it('reads tables as pipe tables, with merged, nested and one-column tables handled', function (): void {
    $cell = fn (string $content, string $properties = ''): string => '<w:tc><w:tcPr>'.$properties.'</w:tcPr>'.$content.'</w:tc>';
    $nested = '<w:tbl><w:tr>'.$cell(DocxFile::paragraph('Inner A')).$cell(DocxFile::paragraph('Inner B')).'</w:tr></w:tbl>';

    $table = '<w:tbl>'
        .'<w:tr>'.$cell(DocxFile::paragraph('Region')).$cell(DocxFile::paragraph('Q3')).$cell(DocxFile::paragraph('Q4')).'</w:tr>'
        .'<w:tr>'.$cell(DocxFile::paragraph('North'), '<w:vMerge w:val="restart"/>').$cell(DocxFile::paragraph('1 | 2')).$cell(DocxFile::paragraph('3')).'</w:tr>'
        .'<w:tr>'.$cell('<w:p/>', '<w:vMerge/>').$cell(DocxFile::paragraph('Estimate'), '<w:gridSpan w:val="2"/>').'</w:tr>'
        .'<w:tr><w:trPr><w:del w:id="1" w:author="A"/></w:trPr>'.$cell(DocxFile::paragraph('Gone')).$cell('<w:p/>').$cell('<w:p/>').'</w:tr>'
        .'<w:tr>'.$cell(DocxFile::paragraph('South').DocxFile::paragraph('incl. Spain')).$cell($nested.'<w:p/>').$cell('<w:p/>').'</w:tr>'
        .'</w:tbl>';
    $layout = '<w:tbl><w:tr>'.$cell(DocxFile::paragraph('A letter in a box.').DocxFile::paragraph('Second line.')).'</w:tr></w:tbl>';

    expect(docxrRead(new DocxFile($table.$layout)->save()))->toBe(
        "| Region | Q3 | Q4 |\n| --- | --- | --- |\n| North | 1 \\| 2 | 3 |\n|  | Estimate |  |\n| South<br>incl. Spain | Inner A Inner B |  |\n\nA letter in a box.\n\nSecond line.",
    );
});

it('lists footnotes and endnotes at the end, numbered in reading order', function (): void {
    $notes = fn (string $kind, string $text): string => "<w:{$kind}s ".DocxFile::NAMESPACES.'>'
        ."<w:{$kind} w:type=\"separator\" w:id=\"0\"><w:p><w:r><w:separator/></w:r></w:p></w:{$kind}>"
        ."<w:{$kind} w:id=\"2\"><w:p><w:r><w:{$kind}Ref/></w:r><w:r><w:t xml:space=\"preserve\"> {$text}</w:t></w:r></w:p></w:{$kind}>"
        ."</w:{$kind}s>";
    $relationships = '<Relationship Id="f" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes" Target="footnotes.xml"/>'
        .'<Relationship Id="e" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/endnotes" Target="endnotes.xml"/>';

    $body = '<w:p><w:r><w:t>Costs fell</w:t></w:r><w:r><w:endnoteReference w:id="2"/></w:r><w:r><w:t xml:space="preserve"> and margins rose</w:t></w:r><w:r><w:footnoteReference w:id="2"/></w:r><w:r><w:t>.</w:t></w:r></w:p>';

    expect(docxrRead(new DocxFile($body, ['footnotes.xml' => $notes('footnote', 'Unaudited.'), 'endnotes.xml' => $notes('endnote', 'Source: ledger.')], $relationships)->save()))
        ->toBe("Costs fell[^1] and margins rose[^2].\n\n[^1]: Source: ledger.\n\n[^2]: Unaudited.");
});

it('reads a text box once, links with their address, and escapes text that looks like markup', function (): void {
    $box = '<w:txbxContent>'.DocxFile::paragraph('Key figure: 4.2 million').'</w:txbxContent>';
    $body = '<w:p><w:r><w:t>See the box.</w:t></w:r><w:r><mc:AlternateContent><mc:Choice Requires="wps"><w:drawing><wps:txbx xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape">'.$box.'</wps:txbx></w:drawing></mc:Choice><mc:Fallback><w:pict>'.$box.'</w:pict></mc:Fallback></mc:AlternateContent></w:r></w:p>'
        .'<w:p><w:r><w:t xml:space="preserve">Visit </w:t></w:r><w:hyperlink r:id="link"><w:r><w:t>our site</w:t></w:r></w:hyperlink><w:r><w:t xml:space="preserve"> for &lt;Customer name&gt;.</w:t></w:r></w:p>'
        .DocxFile::paragraph('1. Scope 1');

    expect(docxrRead(new DocxFile($body, [], '<Relationship Id="link" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.com/a b" TargetMode="External"/>')->save()))
        ->toBe("See the box.\nKey figure: 4.2 million\n\nVisit [our site](https://example.com/a%20b) for \\<Customer name>.\n\n1\\. Scope 1");
});

it('refuses a file that is not a Word document', function (): void {
    $path = tempTestFile('fake.docx', 'not a zip');

    expect(fn (): string => docxrRead($path))->toThrow(RuntimeException::class);
});
