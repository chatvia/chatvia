<?php

use Domain\Knowledge\Document\Conversion\Markdown\DocxReader;

/**
 * The built-in Word reader: documents are built here from WordprocessingML
 * strings, one case per feature pandoc handles and a simple reader loses.
 */
function docxrNs(): string
{
    return 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"';
}

/**
 * A .docx with this body and these extra word/ parts.
 *
 * @param  array<string, string>  $parts  file name under word/ → XML
 */
function docxrFile(string $body, array $parts = [], string $relationships = ''): string
{
    $path = create_temp_file('document.docx', '');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
    $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>');
    $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document '.docxrNs().'><w:body>'.$body.'</w:body></w:document>');

    foreach ($parts as $name => $xml) {
        $zip->addFromString('word/'.$name, '<?xml version="1.0"?>'.$xml);
    }

    $zip->close();

    return $path;
}

function docxrParagraph(string $text, string $properties = ''): string
{
    return '<w:p>'.($properties !== '' ? '<w:pPr>'.$properties.'</w:pPr>' : '').'<w:r><w:t xml:space="preserve">'.$text.'</w:t></w:r></w:p>';
}

function docxrRead(string $path): string
{
    try {
        return new DocxReader()->toMarkdown($path);
    } finally {
        @unlink($path);
    }
}

it('makes headings from style names and outline levels, so localised style ids count', function (): void {
    $styles = '<w:styles '.docxrNs().'>'
        .'<w:style w:type="paragraph" w:styleId="berschrift1"><w:name w:val="heading 1"/></w:style>'
        .'<w:style w:type="paragraph" w:styleId="Section"><w:name w:val="Section"/><w:pPr><w:outlineLvl w:val="1"/></w:pPr></w:style>'
        .'<w:style w:type="paragraph" w:styleId="SubSection"><w:name w:val="Sub section"/><w:basedOn w:val="Section"/></w:style>'
        .'</w:styles>';

    $body = docxrParagraph('Overview', '<w:pStyle w:val="berschrift1"/>')
        .docxrParagraph('Prices', '<w:pStyle w:val="Section"/>')
        .docxrParagraph('Inherited', '<w:pStyle w:val="SubSection"/>')
        .docxrParagraph('Direct', '<w:outlineLvl w:val="2"/>')
        .docxrParagraph('# not a heading');

    expect(docxrRead(docxrFile($body, ['styles.xml' => $styles], '<Relationship Id="s" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>')))
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

    expect(docxrRead(docxrFile($body)))->toBe('We approved the new plan on page 4.');
});

it('keeps words apart at tabs and breaks, and joins them at soft hyphens', function (): void {
    $body = '<w:p><w:r><w:t>Name:</w:t><w:tab/><w:t>Alice</w:t><w:br/><w:t>Role:</w:t><w:cr/><w:t>long</w:t></w:r><w:r><w:softHyphen/><w:t>standing,</w:t><w:noBreakHyphen/><w:t>term</w:t></w:r></w:p>';

    expect(docxrRead(docxrFile($body)))->toBe("Name: Alice\nRole:\nlongstanding,-term");
});

it('numbers lists the way Word does: per abstract list, restarted by a start override, apart from the next list', function (): void {
    $numbering = '<w:numbering '.docxrNs().'>'
        .'<w:abstractNum w:abstractNumId="0"><w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="decimal"/></w:lvl><w:lvl w:ilvl="1"><w:start w:val="1"/><w:numFmt w:val="bullet"/></w:lvl></w:abstractNum>'
        .'<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>'
        .'<w:num w:numId="2"><w:abstractNumId w:val="0"/><w:lvlOverride w:ilvl="0"><w:startOverride w:val="1"/></w:lvlOverride></w:num>'
        .'<w:num w:numId="3"><w:abstractNumId w:val="0"/><w:lvlOverride w:ilvl="0"><w:startOverride w:val="1"/></w:lvlOverride></w:num>'
        .'<w:abstractNum w:abstractNumId="1"><w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/></w:lvl></w:abstractNum>'
        .'<w:num w:numId="4"><w:abstractNumId w:val="1"/></w:num>'
        .'</w:numbering>';
    $item = fn (string $text, int $numId, int $level = 0): string => docxrParagraph($text, "<w:numPr><w:ilvl w:val=\"{$level}\"/><w:numId w:val=\"{$numId}\"/></w:numPr>");

    $body = $item('Sign in', 2).$item('Get a badge', 1).$item('Blue for visitors', 1, 1).$item('Wear it', 1)
        .docxrParagraph('Then:')
        .$item('Stay on the path', 3).$item('Leave by six', 1).$item('Bring water', 4);

    expect(docxrRead(docxrFile($body, ['numbering.xml' => $numbering], '<Relationship Id="n" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>')))
        ->toBe("1. Sign in\n2. Get a badge\n    - Blue for visitors\n3. Wear it\n\nThen:\n\n1. Stay on the path\n2. Leave by six\n\n- Bring water");
});

it('reads tables as pipe tables, with merged, nested and one-column tables handled', function (): void {
    $cell = fn (string $content, string $properties = ''): string => '<w:tc><w:tcPr>'.$properties.'</w:tcPr>'.$content.'</w:tc>';
    $nested = '<w:tbl><w:tr>'.$cell(docxrParagraph('Inner A')).$cell(docxrParagraph('Inner B')).'</w:tr></w:tbl>';

    $table = '<w:tbl>'
        .'<w:tr>'.$cell(docxrParagraph('Region')).$cell(docxrParagraph('Q3')).$cell(docxrParagraph('Q4')).'</w:tr>'
        .'<w:tr>'.$cell(docxrParagraph('North'), '<w:vMerge w:val="restart"/>').$cell(docxrParagraph('1 | 2')).$cell(docxrParagraph('3')).'</w:tr>'
        .'<w:tr>'.$cell('<w:p/>', '<w:vMerge/>').$cell(docxrParagraph('Estimate'), '<w:gridSpan w:val="2"/>').'</w:tr>'
        .'<w:tr><w:trPr><w:del w:id="1" w:author="A"/></w:trPr>'.$cell(docxrParagraph('Gone')).$cell('<w:p/>').$cell('<w:p/>').'</w:tr>'
        .'<w:tr>'.$cell(docxrParagraph('South').docxrParagraph('incl. Spain')).$cell($nested.'<w:p/>').$cell('<w:p/>').'</w:tr>'
        .'</w:tbl>';
    $layout = '<w:tbl><w:tr>'.$cell(docxrParagraph('A letter in a box.').docxrParagraph('Second line.')).'</w:tr></w:tbl>';

    expect(docxrRead(docxrFile($table.$layout)))->toBe(
        "| Region | Q3 | Q4 |\n| --- | --- | --- |\n| North | 1 \\| 2 | 3 |\n|  | Estimate |  |\n| South<br>incl. Spain | Inner A Inner B |  |\n\nA letter in a box.\n\nSecond line.",
    );
});

it('lists footnotes and endnotes at the end, numbered in reading order', function (): void {
    $notes = fn (string $kind, string $text): string => "<w:{$kind}s ".docxrNs().'>'
        ."<w:{$kind} w:type=\"separator\" w:id=\"0\"><w:p><w:r><w:separator/></w:r></w:p></w:{$kind}>"
        ."<w:{$kind} w:id=\"2\"><w:p><w:r><w:{$kind}Ref/></w:r><w:r><w:t xml:space=\"preserve\"> {$text}</w:t></w:r></w:p></w:{$kind}>"
        ."</w:{$kind}s>";
    $relationships = '<Relationship Id="f" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes" Target="footnotes.xml"/>'
        .'<Relationship Id="e" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/endnotes" Target="endnotes.xml"/>';

    $body = '<w:p><w:r><w:t>Costs fell</w:t></w:r><w:r><w:endnoteReference w:id="2"/></w:r><w:r><w:t xml:space="preserve"> and margins rose</w:t></w:r><w:r><w:footnoteReference w:id="2"/></w:r><w:r><w:t>.</w:t></w:r></w:p>';

    expect(docxrRead(docxrFile($body, ['footnotes.xml' => $notes('footnote', 'Unaudited.'), 'endnotes.xml' => $notes('endnote', 'Source: ledger.')], $relationships)))
        ->toBe("Costs fell[^1] and margins rose[^2].\n\n[^1]: Source: ledger.\n\n[^2]: Unaudited.");
});

it('reads a text box once, links with their address, and escapes text that looks like markup', function (): void {
    $box = '<w:txbxContent>'.docxrParagraph('Key figure: 4.2 million').'</w:txbxContent>';
    $body = '<w:p><w:r><w:t>See the box.</w:t></w:r><w:r><mc:AlternateContent><mc:Choice Requires="wps"><w:drawing><wps:txbx xmlns:wps="http://schemas.microsoft.com/office/word/2010/wordprocessingShape">'.$box.'</wps:txbx></w:drawing></mc:Choice><mc:Fallback><w:pict>'.$box.'</w:pict></mc:Fallback></mc:AlternateContent></w:r></w:p>'
        .'<w:p><w:r><w:t xml:space="preserve">Visit </w:t></w:r><w:hyperlink r:id="link"><w:r><w:t>our site</w:t></w:r></w:hyperlink><w:r><w:t xml:space="preserve"> for &lt;Customer name&gt;.</w:t></w:r></w:p>'
        .docxrParagraph('1. Scope 1');

    expect(docxrRead(docxrFile($body, [], '<Relationship Id="link" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.com/a b" TargetMode="External"/>')))
        ->toBe("See the box.\nKey figure: 4.2 million\n\nVisit [our site](https://example.com/a%20b) for \\<Customer name>.\n\n1\\. Scope 1");
});

it('refuses a file that is not a Word document', function (): void {
    $path = create_temp_file('fake.docx', 'not a zip');

    expect(fn (): string => docxrRead($path))->toThrow(RuntimeException::class);
});
