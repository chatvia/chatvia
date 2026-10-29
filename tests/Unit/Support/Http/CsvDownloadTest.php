<?php

declare(strict_types=1);

use Support\Http\CsvDownload;

/**
 * CsvDownload turns cells into text and puts a quote in front of anything a
 * spreadsheet would run as a formula.
 */
it('writes a cell as text and makes a formula safe', function (string|int|float|bool|null $value, string $cell): void {
    expect(app(CsvDownload::class)->cell($value))->toBe($cell);
})->with([
    'plain text' => ['Ahmad', 'Ahmad'],
    'empty' => [null, ''],
    'true' => [true, 'yes'],
    'a formula' => ['=SUM(A1)', "'=SUM(A1)"],
    'a plus' => ['+1', "'+1"],
    'a minus' => ['-1', "'-1"],
    'an at sign' => ['@cmd', "'@cmd"],
]);
