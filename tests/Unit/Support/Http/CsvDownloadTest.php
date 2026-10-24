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
    'a number' => [42, '42'],
    'empty' => [null, ''],
    'true' => [true, 'yes'],
    'false' => [false, 'no'],
    'a formula' => ['=SUM(A1)', "'=SUM(A1)"],
    'a plus' => ['+1', "'+1"],
    'a minus' => ['-1', "'-1"],
    'an at sign' => ['@cmd', "'@cmd"],
]);

it('streams every row as CSV', function (): void {
    $response = app(CsvDownload::class)->response([['Name', 'Note'], ['Ahmad', '=1+1']], 'people.csv');

    ob_start();
    $response->sendContent();
    $body = (string) ob_get_clean();

    expect($body)->toBe("Name,Note\nAhmad,'=1+1\n")
        ->and($response->headers->get('Content-Disposition'))->toContain('people.csv');
});
