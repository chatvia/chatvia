<?php

declare(strict_types=1);

use Domain\Knowledge\Document\Conversion\Markdown\CodePage;

/**
 * 8-bit text in Windows code pages, as RTF and Word 6 files store it.
 */
it('decodes single-byte and double-byte code pages', function (string $utf8, string $encoding, int $codePage): void {
    expect(CodePage::toUtf8((string) iconv('UTF-8', $encoding, $utf8), $codePage))->toBe($utf8);
})->with([
    'Western' => ['Café “quoted” – 5€', 'CP1252', 1252],
    'Cyrillic' => ['Привет, мир', 'CP1251', 1251],
    'Central European' => ['Zażółć gęślą jaźń', 'CP1250', 1250],
    'Greek' => ['Καλημέρα', 'CP1253', 1253],
    'Hebrew' => ['שלום', 'CP1255', 1255],
    'Japanese' => ['日本語のテキスト', 'CP932', 932],
    'Simplified Chinese' => ['简体中文', 'CP936', 936],
]);

it('keeps ASCII as it is and reads an unknown code page as Windows-1252', function (): void {
    expect(CodePage::toUtf8('Plain text', 1251))->toBe('Plain text')
        ->and(CodePage::toUtf8("Caf\xE9", 12345))->toBe('Café');
});

it('knows the code page of a language', function (): void {
    expect(CodePage::forLanguage(0x0419))->toBe(1251)
        ->and(CodePage::forLanguage(0x0415))->toBe(1250)
        ->and(CodePage::forLanguage(0x0411))->toBe(932)
        ->and(CodePage::forLanguage(0x0804))->toBe(936)
        ->and(CodePage::forLanguage(0x0404))->toBe(950)
        ->and(CodePage::forLanguage(0x0409))->toBe(1252);
});
