<?php

namespace Domain\Knowledge\Document\Conversion;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

/**
 * Converts office documents (and plain text) to other formats, mostly PDF,
 * with a headless `soffice`. Each run gets its own throw-away LibreOffice
 * profile so parallel workers never share one, and the profile is removed
 * whether the run succeeds or fails.
 */
class LibreOffice
{
    private readonly string $libreOfficeExecPath;

    private readonly string $cacheDir;

    private int $timeout = 3600;

    private readonly array $allowedFormats;

    /** Set once `soffice --version` has succeeded in this PHP process. */
    private static bool $installationVerified = false;

    public function __construct()
    {
        $this->libreOfficeExecPath = (string) config('services.bin.libreoffice');
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
    }

    public function convert(string $filepathToConvert, string $format): string
    {
        $this->validateInputFile($filepathToConvert);

        $this->validateFormat($format);

        $this->ensureInstalled();

        $fileInfo = pathinfo($filepathToConvert);

        $result = $this->withProfile(fn (string $profileDir) => Process::timeout($this->timeout)
            ->env($this->getLibreOfficeEnv())
            ->run($this->buildConvertCommand($profileDir, $filepathToConvert, $format)));

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

        return $convertedFilePath;
    }

    /**
     * Convert a local file to PDF and return the PDF's path. The input's
     * extension picks LibreOffice's import filter, so it must be the real one.
     */
    public function toPdf(string $inputPath): string
    {
        return $this->convert($inputPath, 'pdf');
    }

    public function supportsFormat(string $format): bool
    {
        return in_array(strtolower($format), $this->allowedFormats, true);
    }

    /**
     * Check once per process that soffice runs, so a missing binary fails
     * with a clear message instead of a conversion error.
     */
    private function ensureInstalled(): void
    {
        if (self::$installationVerified) {
            return;
        }

        $this->getVersion();

        self::$installationVerified = true;
    }

    /**
     * Run `$callback` with a fresh LibreOffice profile directory and always
     * delete the directory afterwards.
     *
     * @template T
     *
     * @param  callable(string): T  $callback
     * @return T
     */
    private function withProfile(callable $callback): mixed
    {
        $profileDir = rtrim($this->cacheDir, '/').'/libreoffice/profiles/'.Str::uuid()->toString();

        File::ensureDirectoryExists($profileDir);

        try {
            return $callback($profileDir);
        } finally {
            File::deleteDirectory($profileDir);
        }
    }

    private function buildConvertCommand(
        string $profileDir,
        string $inputPath,
        string $outputFormat,
    ): array {
        return [
            $this->libreOfficeExecPath,
            '-env:UserInstallation=file://'.$profileDir,
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

    private function getVersion(): string
    {
        $result = $this->withProfile(fn (string $profileDir) => Process::timeout(30)
            ->env($this->getLibreOfficeEnv())
            ->run([
                $this->libreOfficeExecPath,
                '-env:UserInstallation=file://'.$profileDir,
                '--headless',
                '--version',
            ]));

        if (! $result->successful()) {
            throw new RuntimeException("Could not get LibreOffice version: {$result->errorOutput()}");
        }

        return trim($result->output());
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
