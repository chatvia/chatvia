<?php

use Domain\Knowledge\Document\Conversion\PdfEncryption;
use Domain\Knowledge\Document\Conversion\PdfInfo;

/**
 * PdfEncryption decides "locked" the way pdfinfo does: an encrypted PDF whose
 * user password is empty opens without one, so it is not locked; one with a
 * user password is. The fixtures were made with qpdf, one per revision of the
 * Standard security handler (2: RC4 40-bit, 3: RC4 128-bit, 4: AES-128,
 * 5 and 6: AES-256), with and without object streams.
 */
function pencFixture(string $name): string
{
    return base_path("tests/Fixtures/pdf/{$name}.pdf");
}

it('finds no encryption in a plain PDF', function (): void {
    $encryption = new PdfEncryption;

    expect($encryption->isEncrypted(pencFixture('plain')))->toBeFalse()
        ->and($encryption->needsPassword(pencFixture('plain')))->toBeFalse();
});

it('opens an encrypted PDF whose user password is empty', function (string $fixture): void {
    $encryption = new PdfEncryption;

    expect($encryption->isEncrypted(pencFixture($fixture)))->toBeTrue()
        ->and($encryption->needsPassword(pencFixture($fixture)))->toBeFalse();
})->with(['owner-r2', 'owner-r3', 'owner-r4', 'owner-r5', 'owner-r6', 'owner-r3-objstm', 'owner-r4-objstm', 'owner-r5-objstm', 'owner-r6-objstm']);

it('needs the password of a PDF with a user password', function (string $fixture): void {
    expect(new PdfEncryption()->needsPassword(pencFixture($fixture)))->toBeTrue();
})->with(['user-r2', 'user-r3', 'user-r4', 'user-r5', 'user-r6', 'user-r6-objstm']);

it('counts a PDF with another security handler as locked', function (): void {
    $path = create_temp_file('pubsec.pdf', "%PDF-1.7\n1 0 obj\n<< /Filter /Adobe.PubSec /V 4 /R 4 >>\nendobj\ntrailer << /Root 2 0 R /Encrypt 1 0 R >>\n%%EOF\n");

    expect(new PdfEncryption()->needsPassword($path))->toBeTrue();
});

it('reads the page tree out of encrypted object streams', function (string $fixture): void {
    $pageTrees = array_filter(
        new PdfEncryption()->dictionaries(pencFixture($fixture)),
        fn (array $dictionary): bool => ($dictionary['Type'] ?? null) === '/Pages',
    );

    expect(array_column($pageTrees, 'Count'))->toBe([3]);
})->with(['owner-r3-objstm', 'owner-r4-objstm', 'owner-r5-objstm', 'owner-r6-objstm']);

it('leaves out the object streams of a PDF it cannot open', function (): void {
    $pageTrees = array_filter(
        new PdfEncryption()->dictionaries(pencFixture('user-r6-objstm')),
        fn (array $dictionary): bool => ($dictionary['Type'] ?? null) === '/Pages',
    );

    expect($pageTrees)->toBe([]);
});

it('agrees with pdfinfo on every fixture', function (): void {
    if (! programInstalled('pdfinfo')) {
        $this->markTestSkipped('pdfinfo is not installed.');
    }

    foreach (glob(base_path('tests/Fixtures/pdf/*.pdf')) ?: [] as $path) {
        expect(new PdfEncryption()->needsPassword($path))->toBe(new PdfInfo()->isLocked($path), basename($path));
    }
});
