<?php

use Domain\Knowledge\Document\Conversion\BuiltinPdfInspector;
use Illuminate\Support\Facades\File;

/**
 * BuiltinPdfInspector stands in for pdfinfo on a server without poppler: the
 * same page count and lock state, in PHP. Encrypted PDFs are counted from
 * their page tree, which may sit in an encrypted object stream, and the root
 * of a page tree an update rewrote wins.
 */
function bpiUpdatedPdf(): string
{
    $original = File::get(pdfFixture('plain'));
    $update = "2 0 obj\n<< /Type /Pages /Kids [3 0 R 5 0 R] /Count 2 >>\nendobj\n";
    $xref = strlen($original) + strlen($update);
    preg_match('/startxref\s+(\d+)/', $original, $previous);

    return tempTestFile('updated.pdf', $original.$update."xref\n2 1\n".sprintf('%010d 00000 n ', strlen($original))."\ntrailer << /Size 11 /Root 1 0 R /Prev {$previous[1]} >>\nstartxref\n{$xref}\n%%EOF\n");
}

it('counts the pages of a plain, encrypted or updated PDF', function (string $path, int $pages): void {
    expect(app(BuiltinPdfInspector::class)->getInfo($path)->length)->toBe($pages);
})->with([
    'plain' => [fn (): string => pdfFixture('plain'), 3],
    'encrypted, RC4' => [fn (): string => pdfFixture('owner-r2'), 3],
    'encrypted, AES-256' => [fn (): string => pdfFixture('owner-r6'), 3],
    'encrypted object stream' => [fn (): string => pdfFixture('owner-r6-objstm'), 3],
    'a page tree an update rewrote' => [fn (): string => bpiUpdatedPdf(), 2],
]);

it('tells a locked PDF from one that opens', function (): void {
    $inspector = app(BuiltinPdfInspector::class);

    expect($inspector->isLocked(pdfFixture('user-r6')))->toBeTrue()
        ->and($inspector->isLocked(pdfFixture('owner-r6')))->toBeFalse()
        ->and($inspector->isLocked(pdfFixture('plain')))->toBeFalse();
});
