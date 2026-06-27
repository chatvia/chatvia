<?php

namespace Domain\Knowledge\Document;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class LibreOffice
{
    private string $libreOfficeExecPath;

    private string $cacheDir;

    private int $timeout = 3600;

    private array $allowedFormats;

    private string $profileDir;

    public function __construct()
    {
        $this->libreOfficeExecPath = config('services.bin.libreoffice');
        $this->cacheDir = cache_dir();

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

        $profilePath = rtrim($this->cacheDir, '/').'/libreoffice/profiles/'.Str::uuid()->toString();

        File::makeDirectory($profilePath, recursive: true);

        $this->profileDir = $profilePath;

        // Skip validation during package discovery to avoid issues
        if (! $this->isPackageDiscovery()) {
            $this->validateInstallation();
        }
    }

    public function convert(string $filepathToConvert, string $format): string
    {
        $this->validateInputFile($filepathToConvert);

        $this->validateFormat($format);

        $fileInfo = pathinfo($filepathToConvert);

        $command = $this->buildConvertCommand($filepathToConvert, $format);

        $result = Process::timeout($this->timeout)
            ->env($this->getLibreOfficeEnv())
            ->run($command);

        if (! $result->successful()) {
            throw new RuntimeException(
                "LibreOffice conversion failed: {$result->errorOutput()}"
            );
        }

        $convertedFilePath = $this->cacheDir.'/libreoffice/files/'.$fileInfo['filename'].'.'.$format;

        if (! File::exists($convertedFilePath)) {
            // soffice exits 0 even when it fails to load/convert a file (corrupt,
            // password-protected, mislabeled extension, or under resource/concurrency
            // pressure). The exit code is useless here — surface its actual output so
            // the failure is diagnosable instead of a bare "file not found".
            throw new RuntimeException(sprintf(
                'Could not find converted file: %s. soffice stdout: %s | stderr: %s',
                $convertedFilePath,
                trim($result->output()) ?: '(empty)',
                trim($result->errorOutput()) ?: '(empty)',
            ));
        }

        File::deleteDirectory($this->profileDir);

        return $convertedFilePath;
    }

    public function toPdf(string|UploadedFile $inputPath): string
    {
        if ($inputPath instanceof UploadedFile) {
            $inputPath = $inputPath->getPathname();
        }

        return $this->convert($inputPath, 'pdf');
    }

    public function text2Pdf(string $text): string
    {
        $filePath = create_temp_file('file.txt', $text);

        return $this->convert($filePath, 'pdf');
    }

    public function supportsFormat(string $format): bool
    {
        return in_array(strtolower($format), $this->allowedFormats, true);
    }

    private function validateInstallation(): void
    {
        $result = Process::timeout(30)
            ->env($this->getLibreOfficeEnv())
            ->run([
                $this->libreOfficeExecPath,
                '-env:UserInstallation=file://'.$this->profileDir,
                '--headless',
                '--version',
            ]);

        if (! $result->successful()) {
            throw new RuntimeException("LibreOffice test run failed: {$result->errorOutput()}");
        }
    }

    private function buildConvertCommand(
        string $inputPath,
        string $outputFormat,
    ): array {
        return [
            $this->libreOfficeExecPath,
            '-env:UserInstallation=file://'.$this->profileDir,
            '--headless',
            '--invisible',
            '--nodefault',
            '--nolockcheck',
            '--nologo',
            '--norestore',
            '--convert-to', $outputFormat,
            '--outdir', $this->cacheDir.'/libreoffice/files',
            $inputPath,
        ];

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

    private function validateFormat(string $format): void
    {
        if (! $this->supportsFormat($format)) {
            throw new InvalidArgumentException(
                "Unsupported format: {$format}. Supported formats: ".
                implode(', ', $this->allowedFormats)
            );
        }
    }

    public function getVersion(): string
    {
        $result = Process::timeout(30)
            ->env($this->getLibreOfficeEnv())
            ->run([
                $this->libreOfficeExecPath,
                '-env:UserInstallation=file://'.$this->profileDir,
                '--headless',
                '--version',
            ]);

        if (! $result->successful()) {
            throw new RuntimeException("Could not get LibreOffice version: {$result->errorOutput()}");
        }

        return trim($result->output());
    }

    public static function make(): self
    {
        return new self;
    }

    private function isPackageDiscovery(): bool
    {
        if (! app()->runningInConsole()) {
            return false;
        }

        $argv = $_SERVER['argv'] ?? [];

        return in_array('package:discover', $argv, true);
    }

    private function getLibreOfficeEnv(): array
    {
        $temp = rtrim($this->cacheDir, '/');

        return [
            'HOME' => $temp,
            'TMPDIR' => $temp,
            'XDG_CONFIG_HOME' => $temp.'/xdg/config',
            'XDG_CACHE_HOME' => $temp.'/xdg/cache',
            'XDG_RUNTIME_DIR' => $temp.'/xdg/runtime',
            'SAL_USE_VCLPLUGIN' => 'gen',
            'LANG' => 'C',
            'LC_ALL' => 'C',
        ];
    }
}
