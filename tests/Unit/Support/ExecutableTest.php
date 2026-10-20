<?php

use Illuminate\Support\Facades\File;
use Support\Executable;

/**
 * Executable finds the program a configured path leads to, by file checks
 * alone: a full path must be a file that can run; a bare name is looked up
 * on the PATH.
 */
beforeEach(function (): void {
    $this->folder = sys_get_temp_dir().'/chatvia-executable-'.uniqid();
    File::ensureDirectoryExists($this->folder);

    file_put_contents("{$this->folder}/pdfinfo", "#!/bin/sh\nexit 0\n");
    chmod("{$this->folder}/pdfinfo", 0755);
    file_put_contents("{$this->folder}/pandoc", 'not a program');
    chmod("{$this->folder}/pandoc", 0644);

    $this->path = getenv('PATH');
});

afterEach(function (): void {
    putenv("PATH={$this->path}");
    File::deleteDirectory($this->folder);
});

it('finds a full path only when it leads to a program', function (): void {
    expect(Executable::find("{$this->folder}/pdfinfo"))->toBe("{$this->folder}/pdfinfo")
        ->and(Executable::find("{$this->folder}/pandoc"))->toBeNull()
        ->and(Executable::find("{$this->folder}/ffmpeg"))->toBeNull()
        ->and(Executable::exists("{$this->folder}/pdfinfo"))->toBeTrue();
});

it('looks up a bare name on the PATH', function (): void {
    putenv("PATH=/nowhere:{$this->folder}");

    expect(Executable::find('pdfinfo'))->toBe("{$this->folder}/pdfinfo")
        ->and(Executable::find('pandoc'))->toBeNull()
        ->and(Executable::exists('ffmpeg'))->toBeFalse();
});

it('finds nothing for an empty path', function (): void {
    expect(Executable::find(''))->toBeNull();
});
