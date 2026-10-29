<?php

use Domain\Knowledge\Document\Conversion\PdfEncryption;

/**
 * PdfEncryption decides "locked" the way pdfinfo does: an encrypted PDF whose
 * user password is empty opens without one, so it is not locked; one with a
 * user password, or another security handler, is. The fixtures were made
 * with qpdf, one per revision of the Standard security handler (2: RC4
 * 40-bit, 3: RC4 128-bit, 4: AES-128, 5 and 6: AES-256), with and without
 * object streams.
 */
it('opens a plain PDF and an encrypted PDF whose user password is empty', function (string $fixture, bool $encrypted): void {
    $encryption = new PdfEncryption;

    expect($encryption->isEncrypted(pdfFixture($fixture)))->toBe($encrypted)
        ->and($encryption->needsPassword(pdfFixture($fixture)))->toBeFalse();
})->with([
    'plain' => ['plain', false],
    'revision 2' => ['owner-r2', true],
    'revision 3' => ['owner-r3', true],
    'revision 4' => ['owner-r4', true],
    'revision 5' => ['owner-r5', true],
    'revision 6' => ['owner-r6', true],
]);

it('needs the password of a PDF with a user password or another security handler', function (string $path): void {
    expect(new PdfEncryption()->needsPassword($path))->toBeTrue();
})->with([
    'revision 2' => [fn (): string => pdfFixture('user-r2')],
    'revision 3' => [fn (): string => pdfFixture('user-r3')],
    'revision 4' => [fn (): string => pdfFixture('user-r4')],
    'revision 5' => [fn (): string => pdfFixture('user-r5')],
    'revision 6' => [fn (): string => pdfFixture('user-r6')],
    'another security handler' => [fn (): string => tempTestFile('pubsec.pdf', "%PDF-1.7\n1 0 obj\n<< /Filter /Adobe.PubSec /V 4 /R 4 >>\nendobj\ntrailer << /Root 2 0 R /Encrypt 1 0 R >>\n%%EOF\n")],
]);

it('reads the page tree out of encrypted object streams only when the PDF opens', function (string $fixture, array $counts): void {
    $pageTrees = array_filter(
        new PdfEncryption()->dictionaries(pdfFixture($fixture)),
        fn (array $dictionary): bool => ($dictionary['Type'] ?? null) === '/Pages',
    );

    expect(array_values(array_column($pageTrees, 'Count')))->toBe($counts);
})->with([
    'RC4' => ['owner-r3-objstm', [3]],
    'AES-128' => ['owner-r4-objstm', [3]],
    'AES-256' => ['owner-r6-objstm', [3]],
    'locked' => ['user-r6-objstm', []],
]);
