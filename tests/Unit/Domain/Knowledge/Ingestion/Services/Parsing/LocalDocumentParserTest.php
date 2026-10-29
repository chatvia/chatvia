<?php

use Domain\Knowledge\Ingestion\Exceptions\DocumentParsingException;
use Domain\Knowledge\Ingestion\Services\Parsing\LocalDocumentParser;

/**
 * The local document parser reads PDFs on the server with poppler's
 * pdftotext, so an install without an OCR service can still ingest them: one
 * page per PDF page, and a PDF the tool cannot read reported as a parsing
 * failure. The tests need the pdftotext binary and skip cleanly when it is
 * not installed.
 */

/**
 * Write a minimal, valid PDF with one line of text per page and return its path.
 *
 * @param  list<string>  $pages
 */
function ldpPdf(array $pages, string $extension = 'pdf'): string
{
    $objects = [];
    $pageCount = count($pages);
    $fontId = 3 + ($pageCount * 2);
    $kids = [];

    foreach ($pages as $i => $text) {
        $pageId = 3 + ($i * 2);
        $contentId = $pageId + 1;
        $kids[] = "{$pageId} 0 R";
        $stream = 'BT /F1 18 Tf 72 700 Td ('.addcslashes($text, '()\\').') Tj ET';
        $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 {$fontId} 0 R >> >> /Contents {$contentId} 0 R >>";
        $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
    }

    $objects[1] = '<< /Type /Catalog /Pages 2 0 R >>';
    $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids)."] /Count {$pageCount} >>";
    $objects[$fontId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>';
    ksort($objects);

    $pdf = "%PDF-1.4\n";
    $offsets = [];

    foreach ($objects as $id => $body) {
        $offsets[$id] = strlen($pdf);
        $pdf .= "{$id} 0 obj\n{$body}\nendobj\n";
    }

    $xref = strlen($pdf);
    $pdf .= 'xref'."\n".'0 '.(count($objects) + 1)."\n".'0000000000 65535 f '."\n";

    foreach ($offsets as $offset) {
        $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
    }

    $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";

    return tempTestFile('document.'.$extension, $pdf);
}

it('reads each PDF page into its own PageData', function (): void {
    if (! programInstalled('pdftotext')) {
        $this->markTestSkipped('pdftotext is not installed.');
    }

    $path = ldpPdf(['Refunds take five days.', 'Shipping is free in the EU.']);

    $pages = app(LocalDocumentParser::class)->parse($path, 'users/ws/agents/agent/tmp');

    expect($pages)->toHaveCount(2)
        ->and($pages[0]->pageNumber)->toBe(1)
        ->and($pages[0]->content)->toBe('Refunds take five days.')
        ->and($pages[0]->checksum)->toBe(hash('xxh128', 'Refunds take five days.'))
        ->and($pages[1]->pageNumber)->toBe(2)
        ->and($pages[1]->content)->toBe('Shipping is free in the EU.')
        ->and($pages[0]->imagesBase64)->toBe([])
        ->and($pages[0]->tables)->toBe([]);
});

it('reports a PDF the text tool cannot read as a parsing failure', function (): void {
    if (! programInstalled('pdftotext')) {
        $this->markTestSkipped('pdftotext is not installed.');
    }

    $broken = tempTestFile('broken.pdf', 'this is not a pdf');

    expect(fn () => app(LocalDocumentParser::class)->parse($broken, 'users/ws/agents/agent/tmp'))
        ->toThrow(DocumentParsingException::class, 'The document could not be read');
});
