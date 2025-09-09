<?php

namespace App\Support;

use App\Data\PdfInfoData;
use Exception;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

final class PdfInfo
{
    private string $pdfInfoPath;

    private string $tempDir;

    private int $timeout;

    public function __construct()
    {
        $this->pdfInfoPath = config('services.bin.pdfinfo');
        $this->tempDir = temp_dir();
        $this->timeout = 3600;

    }

    public function getInfo(string $pdfPath): PdfInfoData
    {
        $info = $this->getRawInfo($pdfPath);

        return new PdfInfoData(
            title: $info['title'] ?? '',
            subject: $info['subject'] ?? '',
            keywords: $info['keywords'] ?? '',
            author: $info['author'] ?? '',
            creator: $info['creator'] ?? '',
            producer: $info['producer'] ?? '',
            creation_date: $info['creationdate'] ?? '',
            modification_date: $info['moddate'] ?? '',
            tagged: isset($info['tagged']) && $info['tagged'] === 'yes',
            form: $info['form'] ?? '',
            javascript: isset($info['javascript']) && $info['javascript'] === 'yes',
            length: (int) $info['pages'],
            encrypted: $info['encrypted'] === 'yes',
            page_size_width: $info['page_size_width'],
            page_size_height: $info['page_size_height'],
            file_size: $info['file_size'],
            pdf_version: $info['pdf_version'],
        );
    }

    private function getRawInfo(string $pdfPath): array
    {
        $this->validatePdfFile($pdfPath);

        $command = [$this->pdfInfoPath, $pdfPath];

        Log::info('PDFInfo extraction started', [
            'input' => $pdfPath,
            'command' => $command,
        ]);

        $result = Process::timeout($this->timeout)->run($command);

        if (! $result->successful()) {
            throw new RuntimeException(
                "PDFInfo extraction failed: {$result->errorOutput()}"
            );
        }

        return $this->parseOutput($result->output());
    }

    private function validatePdfFile(string $path): void
    {
        if (! File::exists($path)) {
            throw new InvalidArgumentException("PDF file does not exist: {$path}");
        }

        if (! File::isReadable($path)) {
            throw new InvalidArgumentException("PDF file is not readable: {$path}");
        }

        if (File::size($path) === 0) {
            throw new InvalidArgumentException("PDF file is empty: {$path}");
        }

        $mimeType = File::mimeType($path);

        if ($mimeType !== 'application/pdf') {
            throw new InvalidArgumentException("File is not a PDF: {$path} (detected: {$mimeType})");
        }
    }

    private function parseOutput(string $output): array
    {
        $output = mb_convert_encoding($output, 'UTF-8', 'UTF-8');

        $info = [];
        $lines = explode("\n", trim($output));

        foreach ($lines as $line) {
            if (empty(trim($line))) {
                continue;
            }

            if (str_contains($line, ':')) {
                [$key, $value] = explode(':', $line, 2);
                $key = strtolower(trim($key));
                $key = str_replace(' ', '_', $key);
                $value = trim($value);

                // Parse page size
                if ($key === 'page_size' && preg_match('/(\d+(?:\.\d+)?)\s*x\s*(\d+(?:\.\d+)?)\s*pts/', $value, $matches)) {
                    $info['page_size_width'] = (float) $matches[1];
                    $info['page_size_height'] = (float) $matches[2];
                } else {
                    $info[$key] = $value;
                }
            }
        }

        return $info;
    }

    public static function make(): self
    {
        return new self;
    }
}
