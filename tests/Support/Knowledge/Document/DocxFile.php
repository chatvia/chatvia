<?php

declare(strict_types=1);

namespace Tests\Support\Knowledge\Document;

use ZipArchive;

/**
 * Builds a small Word (.docx) document for tests of the Word readers: a
 * WordprocessingML body, with extra parts under word/ (styles, numbering,
 * notes) and their relationships when a test needs them. {@see save()}
 * writes it to the temp folder; the caller deletes it.
 */
final readonly class DocxFile
{
    /** The namespaces a part's root element declares. */
    public const string NAMESPACES = 'xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006"';

    /**
     * @param  array<string, string>  $parts  file name under word/ → XML
     * @param  string  $relationships  the `<Relationship>` elements of word/document.xml
     */
    public function __construct(
        private string $body,
        private array $parts = [],
        private string $relationships = '',
    ) {}

    /** One paragraph of one run, with these paragraph properties (`<w:pPr>` content). */
    public static function paragraph(string $text, string $properties = ''): string
    {
        return '<w:p>'.($properties !== '' ? '<w:pPr>'.$properties.'</w:pPr>' : '').'<w:r><w:t xml:space="preserve">'.$text.'</w:t></w:r></w:p>';
    }

    public function save(): string
    {
        $path = create_temp_file('document.docx', '');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$this->relationships.'</Relationships>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0"?><w:document '.self::NAMESPACES.'><w:body>'.$this->body.'</w:body></w:document>');

        foreach ($this->parts as $name => $xml) {
            $zip->addFromString('word/'.$name, '<?xml version="1.0"?>'.$xml);
        }

        $zip->close();

        return $path;
    }
}
