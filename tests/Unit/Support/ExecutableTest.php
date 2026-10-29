<?php

use Support\Executable;
use Tests\Support\Files\ProgramFolder;

/**
 * Executable finds the program a configured path leads to, by file checks
 * alone: a full path must be a file that can run; a bare name is looked up
 * on the PATH.
 */
beforeEach(function (): void {
    $this->folder = ProgramFolder::make(['pdfinfo'], notRunnable: ['pandoc']);
    $this->path = getenv('PATH');
});

afterEach(function (): void {
    putenv("PATH={$this->path}");
});

it('finds a full path only when it leads to a program, a bare name on the PATH, and nothing for an empty path', function (): void {
    putenv("PATH=/nowhere:{$this->folder}");

    expect(Executable::find("{$this->folder}/pdfinfo"))->toBe("{$this->folder}/pdfinfo")
        ->and(Executable::find("{$this->folder}/pandoc"))->toBeNull()
        ->and(Executable::find("{$this->folder}/ffmpeg"))->toBeNull()
        ->and(Executable::exists("{$this->folder}/pdfinfo"))->toBeTrue()
        ->and(Executable::find('pdfinfo'))->toBe("{$this->folder}/pdfinfo")
        ->and(Executable::find('pandoc'))->toBeNull()
        ->and(Executable::exists('ffmpeg'))->toBeFalse()
        ->and(Executable::find(''))->toBeNull();
});
