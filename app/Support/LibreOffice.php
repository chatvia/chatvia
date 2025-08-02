<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

class LibreOffice
{
    private string $libreOfficePath;

    private string $tempDir;

    private int $timeout;

    private array $allowedFormats;

    public function __construct(
    ) {
        $this->libreOfficePath = config('services.bin.libreoffice');
        $this->tempDir = temp_dir();
        $this->timeout = 3600;

        $this->allowedFormats = [
            // Writer formats
            'odt', 'doc', 'docx', 'rtf', 'txt', 'html', 'htm',
            // Calc formats
            'ods', 'xls', 'xlsx', 'csv',
            // Impress formats
            'odp', 'ppt', 'pptx',
            // PDF
            'pdf',
            // Images
            'png', 'jpg', 'jpeg', 'svg',
        ];

        $this->validateInstallation();
    }

    /**
     * Convert a document to another format
     */
    public function convert(
        string $inputPath,
        string $outputFormat,
        array $options = []
    ): string {
        $this->validateInputFile($inputPath);
        $this->validateFormat($outputFormat);

        $outputPath = $this->generateOutputPath($inputPath, $outputFormat);
        $outputDir = dirname($outputPath);

        if (! File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        $command = $this->buildConvertCommand($inputPath, $outputFormat, $outputDir, $options);

        $result = Process::timeout($this->timeout)->run($command);

        if (! $result->successful()) {
            throw new RuntimeException(
                "LibreOffice conversion failed: {$result->errorOutput()}"
            );
        }

        $actualOutputPath = $this->findConvertedFile($outputDir, $inputPath, $outputFormat);

        if ($actualOutputPath && $actualOutputPath !== $outputPath) {
            File::move($actualOutputPath, $outputPath);
        }

        if (! File::exists($outputPath)) {
            throw new RuntimeException("Conversion completed but output file not found: {$outputPath}");
        }

        Log::info('LibreOffice conversion completed', ['output' => $outputPath]);

        return $outputPath;
    }

    public function toPdf(string|UploadedFile $inputPath): string
    {
        if ($inputPath instanceof UploadedFile) {
            $inputPath = $inputPath->getPathname();
        }

        return $this->convert($inputPath, 'pdf');
    }

    public function extractText(string $inputPath): string
    {
        $this->validateInputFile($inputPath);

        $tempOutput = tempnam($this->tempDir, 'libreoffice_text_').'.txt';

        try {
            $this->convert($inputPath, 'txt', $tempOutput);
            $text = File::get($tempOutput);
            File::delete($tempOutput);

            return trim($text);
        } catch (\Exception $e) {
            if (File::exists($tempOutput)) {
                File::delete($tempOutput);
            }
            throw $e;
        }
    }

    public function getDocumentInfo(string $inputPath): array
    {
        $this->validateInputFile($inputPath);

        $command = [
            $this->libreOfficePath,
            '--headless',
            '--invisible',
            '--nodefault',
            '--nolockcheck',
            '--nologo',
            '--norestore',
            '--convert-to', 'txt',
            '--outdir', $this->tempDir,
            $inputPath,
        ];

        $result = Process::timeout($this->timeout)->run($command);

        $fileInfo = [
            'path' => $inputPath,
            'size' => File::size($inputPath),
            'extension' => File::extension($inputPath),
            'modified' => File::lastModified($inputPath),
            'readable' => File::isReadable($inputPath),
            'mime_type' => File::mimeType($inputPath),
        ];

        // Try to extract basic document properties
        try {
            $text = $this->extractText($inputPath);
            $fileInfo['word_count'] = str_word_count($text);
            $fileInfo['character_count'] = strlen($text);
            $fileInfo['line_count'] = substr_count($text, "\n") + 1;
        } catch (\Exception $e) {
            Log::warning('Could not extract text for document info', [
                'file' => $inputPath,
                'error' => $e->getMessage(),
            ]);
        }

        return $fileInfo;
    }

    public function batchConvert(
        array $inputPaths,
        string $outputFormat,
        ?string $outputDir = null
    ): array {
        $results = [];
        $outputDir = $outputDir ?? $this->tempDir;

        foreach ($inputPaths as $inputPath) {
            try {
                $outputPath = $this->generateOutputPath($inputPath, $outputFormat, $outputDir);
                $convertedPath = $this->convert($inputPath, $outputFormat, $outputPath);
                $results[$inputPath] = $convertedPath;
            } catch (\Exception $e) {
                Log::error('Batch conversion failed for file', [
                    'file' => $inputPath,
                    'error' => $e->getMessage(),
                ]);
                $results[$inputPath] = null;
            }
        }

        return $results;
    }

    public function supportsFormat(string $format): bool
    {
        return in_array(strtolower($format), $this->allowedFormats);
    }

    public function getSupportedFormats(): array
    {
        return $this->allowedFormats;
    }

    public function createFromTemplate(string $templatePath, array $replacements = []): string
    {
        $this->validateInputFile($templatePath);

        $tempOutput = tempnam($this->tempDir, 'libreoffice_template_').'.odt';
        File::copy($templatePath, $tempOutput);

        if (! empty($replacements)) {
            // For simple text replacements, convert to text and back
            $text = $this->extractText($tempOutput);

            foreach ($replacements as $search => $replace) {
                $text = str_replace($search, $replace, $text);
            }

            $textFile = tempnam($this->tempDir, 'libreoffice_text_').'.txt';
            File::put($textFile, $text);

            $finalOutput = tempnam($this->tempDir, 'libreoffice_final_').'.odt';
            $this->convert($textFile, 'odt', $finalOutput);

            File::delete($textFile);
            File::delete($tempOutput);

            return $finalOutput;
        }

        return $tempOutput;
    }

    private function validateInstallation(): void
    {
        $result = Process::timeout(30)->run([
            $this->libreOfficePath, '--headless', '--version',
        ]);

        if (! $result->successful()) {
            throw new RuntimeException("LibreOffice test run failed: {$result->errorOutput()}");
        }
    }

    private function buildConvertCommand(
        string $inputPath,
        string $outputFormat,
        string $outputDir,
        array $options = []
    ): array {
        $command = [
            $this->libreOfficePath,
            '--headless',
            '--invisible',
            '--nodefault',
            '--nolockcheck',
            '--nologo',
            '--norestore',
            '--convert-to', $outputFormat,
            '--outdir', $outputDir,
        ];

        // Add additional options
        foreach ($options as $option => $value) {
            if (is_string($option)) {
                $command[] = "--{$option}";
                if ($value !== true) {
                    $command[] = $value;
                }
            } else {
                $command[] = $value;
            }
        }

        $command[] = $inputPath;

        return $command;
    }

    /**
     * Find the actual converted file
     */
    private function findConvertedFile(string $outputDir, string $inputPath, string $outputFormat): ?string
    {
        $baseName = pathinfo($inputPath, PATHINFO_FILENAME);
        $expectedFile = "{$outputDir}/{$baseName}.{$outputFormat}";

        if (File::exists($expectedFile)) {
            return $expectedFile;
        }

        // Look for any file with the expected extension in the output directory
        $files = File::glob("{$outputDir}/*.{$outputFormat}");

        return ! empty($files) ? $files[0] : null;
    }

    private function generateOutputPath(
        string $inputPath,
        string $outputFormat,
    ): string {
        $baseName = pathinfo($inputPath, PATHINFO_FILENAME);

        return "{$this->tempDir}/{$baseName}.{$outputFormat}";
    }

    private function validateInputFile(string $path): void
    {
        if (! File::exists($path)) {
            throw new InvalidArgumentException("Input file does not exist: {$path}");
        }

        if (! File::isReadable($path)) {
            throw new InvalidArgumentException("Input file is not readable: {$path}");
        }

        if (File::size($path) === 0) {
            throw new InvalidArgumentException("Input file is empty: {$path}");
        }
    }

    /**
     * Validate output format
     */
    private function validateFormat(string $format): void
    {
        if (! $this->supportsFormat($format)) {
            throw new InvalidArgumentException(
                "Unsupported format: {$format}. Supported formats: ".
                implode(', ', $this->allowedFormats)
            );
        }
    }

    /**
     * Get LibreOffice version
     */
    public function getVersion(): string
    {
        $result = Process::timeout(30)->run([
            $this->libreOfficePath, '--headless', '--version',
        ]);

        if (! $result->successful()) {
            throw new RuntimeException("Could not get LibreOffice version: {$result->errorOutput()}");
        }

        return trim($result->output());
    }

    /**
     * Clean up temporary files
     */
    public function cleanup(): void
    {
        $tempFiles = File::glob($this->tempDir.'/libreoffice_*');

        foreach ($tempFiles as $file) {
            try {
                File::delete($file);
            } catch (\Exception $e) {
                Log::warning('Could not delete temporary file', [
                    'file' => $file,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public static function make(): self
    {
        return new self;
    }
}
