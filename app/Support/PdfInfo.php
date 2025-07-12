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
        $this->pdfInfoPath = config('services.pdfinfo.bin');
        $this->tempDir = temp_dir();
        $this->timeout = 3600;

    }

    public function getInfo(string $pdfPath): PdfInfoData
    {
        $info = $this->getRawInfo($pdfPath);

        return new PdfInfoData(
            path: $pdfPath,
            title: $info['title'] ?? null,
            author: $info['author'] ?? null,
            subject: $info['subject'] ?? null,
            creator: $info['creator'] ?? null,
            producer: $info['producer'] ?? null,
            creation_date: $this->parseDate($info['creationdate'] ?? null),
            modification_date: $this->parseDate($info['moddate'] ?? null),
            keywords: $info['keywords'] ?? null,
            pdf_version: $info['pdf_version'] ?? null,
            pages: (int) ($info['pages'] ?? 0),
            file_size: File::size($pdfPath),
            encrypted: isset($info['encrypted']) && $info['encrypted'] === 'yes',
            user_password: isset($info['user_password']) && $info['user_password'] === 'yes',
            owner_password: isset($info['owner_password']) && $info['owner_password'] === 'yes',
            printing: $info['printing'] ?? null,
            copying: $info['copying'] ?? null,
            changing: $info['changing'] ?? null,
            adding_notes: $info['adding_notes'] ?? null,
            form: $info['form'] ?? 'none',
            javascript: isset($info['javascript']) && $info['javascript'] === 'yes',
            suspects: isset($info['suspects']) && $info['suspects'] === 'yes',
            optimized: isset($info['optimized']) && $info['optimized'] === 'yes',
            tagged: isset($info['tagged']) && $info['tagged'] === 'yes',
            linearized: isset($info['linearized']) && $info['linearized'] === 'yes',
            page_size_width: $info['page_size_width'] ?? null,
            page_size_height: $info['page_size_height'] ?? null,
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

    public function isEncrypted(string $pdfPath): bool
    {
        return $this->getInfo($pdfPath)->encrypted;
    }

    public function hasForm(string $pdfPath): bool
    {
        $data = $this->getInfo($pdfPath);

        return $data->form !== 'none';
    }

    public function batchGetInfo(array $pdfPaths): array
    {
        $results = [];

        foreach ($pdfPaths as $pdfPath) {
            try {
                $results[$pdfPath] = $this->getInfo($pdfPath);
            } catch (Exception $e) {
                Log::error('Batch PDF info extraction failed for file', [
                    'file' => $pdfPath,
                    'error' => $e->getMessage(),
                ]);
                $results[$pdfPath] = null;
            }
        }

        return $results;
    }

    private function validateInstallation(): void
    {
        $result = Process::timeout(30)->run([
            $this->pdfInfoPath, '-v',
        ]);

        if (! $result->successful()) {
            throw new RuntimeException("PDFInfo test run failed: {$result->errorOutput()}");
        }
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

    private function parseDate(?string $date): ?string
    {
        if (! $date) {
            return null;
        }

        // PDFInfo dates are in format: D:20240101123000+00'00'
        if (preg_match('/D:(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})(\d{2})/', $date, $matches)) {
            return sprintf(
                '%s-%s-%s %s:%s:%s',
                $matches[1], $matches[2], $matches[3],
                $matches[4], $matches[5], $matches[6]
            );
        }

        return $date;
    }

    public static function make(): self
    {
        return new self;
    }
}
