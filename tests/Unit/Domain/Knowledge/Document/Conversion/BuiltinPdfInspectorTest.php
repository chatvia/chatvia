<?php

use Domain\Knowledge\Document\Conversion\BuiltinPdfInspector;
use Domain\Knowledge\Document\Conversion\PdfInfo;

/**
 * BuiltinPdfInspector stands in for pdfinfo on a server without poppler: the
 * same page count and lock state, in PHP. Encrypted PDFs are counted from
 * their page tree, which may sit in an encrypted object stream.
 */
it('reads the page count and properties of a plain PDF', function (): void {
    $info = app(BuiltinPdfInspector::class)->getInfo(pdfFixture('plain'));

    expect($info->length)->toBe(3)
        ->and($info->metadata)->toMatchArray([
            'title' => 'Fixture',
            'author' => 'Chatvia',
            'pages' => '3',
            'encrypted' => 'no',
            'pdf_version' => '1.4',
        ]);
});

it('counts the pages of an encrypted PDF that opens without a password', function (string $fixture): void {
    $info = app(BuiltinPdfInspector::class)->getInfo(pdfFixture($fixture));

    // Its text entries are encrypted too, so none are kept.
    expect($info->length)->toBe(3)
        ->and($info->metadata)->not->toHaveKey('title')
        ->and($info->metadata['encrypted'])->toBe('yes');
})->with(['owner-r2', 'owner-r4', 'owner-r6', 'owner-r3-objstm', 'owner-r4-objstm', 'owner-r5-objstm', 'owner-r6-objstm']);

it('takes the page count from the root of a page tree an update rewrote', function (): void {
    // The second revision appends a new root /Pages (the same object) whose
    // /Count is 2 and drops the third page; the first root still says 3.
    $original = (string) file_get_contents(pdfFixture('plain'));
    $update = "2 0 obj\n<< /Type /Pages /Kids [3 0 R 5 0 R] /Count 2 >>\nendobj\n";
    $xref = strlen($original) + strlen($update);
    preg_match('/startxref\s+(\d+)/', $original, $previous);
    $pdf = $original.$update."xref\n2 1\n".sprintf('%010d 00000 n ', strlen($original))."\ntrailer << /Size 11 /Root 1 0 R /Prev {$previous[1]} >>\nstartxref\n{$xref}\n%%EOF\n";

    expect(app(BuiltinPdfInspector::class)->getInfo(tempTestFile('updated.pdf', $pdf))->length)->toBe(2);
});

it('tells a locked PDF from one that opens', function (): void {
    $inspector = app(BuiltinPdfInspector::class);

    expect($inspector->isLocked(pdfFixture('user-r6')))->toBeTrue()
        ->and($inspector->isLocked(pdfFixture('owner-r6')))->toBeFalse()
        ->and($inspector->isLocked(pdfFixture('plain')))->toBeFalse();
});

it('fails on a PDF whose pages it cannot read', function (): void {
    app(BuiltinPdfInspector::class)->getInfo(pdfFixture('user-r6-objstm'));
})->throws(RuntimeException::class, 'The PDF reader found no pages.');

it('refuses a file that is not a PDF', function (): void {
    app(BuiltinPdfInspector::class)->getInfo(tempTestFile('notes.pdf', 'just text'));
})->throws(InvalidArgumentException::class);

it('agrees with pdfinfo on every fixture it can open', function (): void {
    if (! programInstalled('pdfinfo')) {
        $this->markTestSkipped('pdfinfo is not installed.');
    }

    foreach (glob(base_path('tests/Fixtures/pdf/*.pdf')) ?: [] as $path) {
        if (new PdfInfo()->isLocked($path)) {
            continue;
        }

        expect(app(BuiltinPdfInspector::class)->getInfo($path)->length)->toBe(new PdfInfo()->getInfo($path)->length, basename($path));
    }
});
