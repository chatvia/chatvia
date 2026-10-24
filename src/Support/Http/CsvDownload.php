<?php

declare(strict_types=1);

namespace Support\Http;

use Illuminate\Support\Str;
use SplFileObject;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams rows to the browser as a CSV file, one row at a time, so a long log
 * never sits in memory. Every cell is made safe for spreadsheets: a value
 * that would run as a formula (a leading =, +, -, @, tab or CR) gets a quote
 * in front.
 */
final class CsvDownload
{
    /**
     * @param  iterable<int, array<int, string|int|float|bool|null>>  $rows
     */
    public function response(iterable $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $output = new SplFileObject('php://output', 'w');

            foreach ($rows as $row) {
                $output->fputcsv(array_map($this->cell(...), $row), escape: '\\');
            }
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** One cell as text, with a quote in front when a spreadsheet would run it. */
    public function cell(string|int|float|bool|null $value): string
    {
        $text = match (true) {
            $value === null => '',
            $value === true => 'yes',
            $value === false => 'no',
            default => (string) $value,
        };

        return Str::isMatch('/^[=+\-@\t\r]/', $text) ? "'".$text : $text;
    }
}
