<?php

declare(strict_types=1);

use Domain\Knowledge\Document\Conversion\Markdown\CodePage;

/**
 * 8-bit text in Windows code pages, as RTF and Word 6 files store it.
 */
it('decodes single-byte and double-byte code pages', function (string $utf8, string $encoding, int $codePage): void {
    expect(CodePage::toUtf8((string) iconv('UTF-8', $encoding, $utf8), $codePage))->toBe($utf8);
})->with([
    'single-byte' => ['Привет, мир', 'CP1251', 1251],
    'double-byte' => ['日本語のテキスト', 'CP932', 932],
]);

it('keeps ASCII as it is and reads an unknown code page as Windows-1252', function (): void {
    expect(CodePage::toUtf8('Plain text', 1251))->toBe('Plain text')
        ->and(CodePage::toUtf8("Caf\xE9", 12345))->toBe('Café');
});
