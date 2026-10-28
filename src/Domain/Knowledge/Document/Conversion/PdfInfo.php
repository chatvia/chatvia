<?php

namespace Domain\Knowledge\Document\Conversion;

use Domain\Knowledge\Document\Contracts\PdfInspector;
use Domain\Knowledge\Source\File\Data\FileAnalysisData;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Reads a PDF's properties (page count, title, page size, …) with poppler's
 * `pdfinfo`. The page count is the PDF's length in the sources UI and feeds the
 * chat-attachment page cap (the `MaxPdfPages` rule).
 */
final readonly class PdfInfo implements PdfInspector
{
    private string $pdfInfoPath;

    private int $timeout;

    public function __construct()
    {
        $this->pdfInfoPath = config('services.bin.pdfinfo');
        // Reading a PDF's metadata takes seconds, even for the largest upload;
        // the probe runs inside the upload request, so it may not hang it.
        $this->timeout = 60;
    }

    public function getInfo(string $pdfPath): FileAnalysisData
    {
        $info = $this->getRawInfo($pdfPath);
        $pages = $info['pages'] ?? null;

        // pdfinfo prints every value as text. Without a page count the file
        // has no length to show or check, so it is rejected.
        if (! is_numeric($pages)) {
            throw new RuntimeException('PDFInfo reported no page count.');
        }

        return new FileAnalysisData(
            length: (int) $pages,
            metadata: $info
        );
    }

    /** Whether the PDF needs a password to open: pdfinfo refuses it without one. */
    public function isLocked(string $pdfPath): bool
    {
        $result = Process::timeout(60)->run([$this->pdfInfoPath, $pdfPath]);

        return ! $result->successful() && Str::contains($result->errorOutput(), 'Incorrect password');
    }

    /** @return array<string, string|float> pdfinfo fields keyed by snake_case name (`pages`, `title`, ...) */
    private function getRawInfo(string $pdfPath): array
    {
        $this->validatePdfFile($pdfPath);

        $command = [$this->pdfInfoPath, $pdfPath];

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

    /** @return array<string, string|float> */
    private function parseOutput(string $output): array
    {
        $output = mb_convert_encoding($output, 'UTF-8', 'UTF-8');

        $info = [];
        $lines = Str::of($output)->trim()->explode("\n");

        foreach ($lines as $line) {
            if (empty(Str::trim($line))) {
                continue;
            }

            if (Str::contains($line, ':')) {
                $key = Str::of($line)->before(':')->trim()->lower()->replace(' ', '_')->toString();
                $value = Str::trim(Str::after($line, ':'));

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
}
