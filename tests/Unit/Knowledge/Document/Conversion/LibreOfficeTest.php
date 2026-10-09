<?php

use Domain\Knowledge\Document\Conversion\LibreOffice;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * LibreOffice used to run `soffice --version` and create a profile directory
 * every time the class was built, and leaked that directory when a conversion
 * failed. Now the check runs lazily (once per process) and every run's
 * profile directory is removed in a `finally`.
 */
beforeEach(function (): void {
    $this->cacheDir = sys_get_temp_dir().'/lo-test-'.uniqid();
    config(['cache.stores.file.path' => $this->cacheDir, 'services.bin.libreoffice' => '/usr/bin/soffice']);

    (new ReflectionProperty(LibreOffice::class, 'installationVerified'))->setValue(null, false);
});

afterEach(function (): void {
    File::deleteDirectory($this->cacheDir);
});

function profileDirs(string $cacheDir): array
{
    return File::isDirectory($cacheDir.'/libreoffice/profiles')
        ? File::directories($cacheDir.'/libreoffice/profiles')
        : [];
}

it('runs no process and creates no directory when constructed', function (): void {
    Process::fake();

    new LibreOffice;
    new LibreOffice;

    Process::assertNothingRan();
    expect(profileDirs($this->cacheDir))->toBe([]);
});

it('removes the profile directory when the conversion fails', function (): void {
    Process::fake(function (PendingProcess $process) {
        return in_array('--version', $process->command, true)
            ? Process::result('LibreOffice 24.8')
            : Process::result(output: '', errorOutput: 'boom', exitCode: 1);
    });

    $input = create_temp_file('doc.docx', 'not really a docx');

    expect(fn (): string => (new LibreOffice)->convert($input, 'pdf'))->toThrow(RuntimeException::class);

    expect(profileDirs($this->cacheDir))->toBe([]);
});

it('checks the installation only once per process', function (): void {
    Process::fake(function (PendingProcess $process) {
        return in_array('--version', $process->command, true)
            ? Process::result('LibreOffice 24.8')
            : Process::result(output: '', errorOutput: 'boom', exitCode: 1);
    });

    $input = create_temp_file('doc.docx', 'not really a docx');

    foreach ([1, 2] as $_) {
        try {
            (new LibreOffice)->convert($input, 'pdf');
        } catch (RuntimeException) {
            // Expected: the faked conversion fails.
        }
    }

    Process::assertRanTimes(fn (PendingProcess $process): bool => in_array('--version', $process->command, true), 1);
    expect(profileDirs($this->cacheDir))->toBe([]);
});
