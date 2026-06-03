<?php

namespace Domain\Knowledge\Document;

use Domain\Knowledge\Source\File\Data\FileAnalysisData;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

final class PdfInfo
{
    private string $pdfInfoPath;

    private int $timeout;

    public function __construct()
    {
        $this->pdfInfoPath = config('services.bin.pdfinfo');
        $this->timeout = 3600;
    }

    public function getInfo(string $pdfPath): FileAnalysisData
    {
        $info = $this->getRawInfo($pdfPath);

        return new FileAnalysisData(
            length: $info['pages'],
            metadata: $info
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
