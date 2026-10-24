<?php

use App\Admin\Settings\ServerProgram;
use App\Admin\Settings\ServerProgramFinder;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Tests\Support\Files\ProgramFolder;

/**
 * ServerProgramFinder detects a program's full path with `which` for the
 * Programs card, also in the folders the PATH of the web server may miss,
 * and keeps only a path the field would take. Whether a path leads to a
 * program is Executable's file check (ExecutableTest).
 */
beforeEach(function (): void {
    $this->folder = ProgramFolder::make(['pdfinfo'], notRunnable: ['pandoc']);
});

it('detects a program with which, also in the usual folders', function (): void {
    Process::fake(['*which*pdfinfo*' => Process::result(output: "{$this->folder}/pdfinfo\n")]);

    expect(new ServerProgramFinder(['/nowhere', $this->folder])->detect(ServerProgram::Pdfinfo))->toBe("{$this->folder}/pdfinfo");

    Process::assertRan(fn (PendingProcess $process): bool => $process->command === ['which', 'pdfinfo']
        && Str::endsWith((string) $process->environment['PATH'], PATH_SEPARATOR.'/nowhere'.PATH_SEPARATOR.$this->folder));
});

it('detects nothing when which finds no program, or a path the field would refuse', function (string $output, int $exitCode): void {
    Process::fake(['*which*' => Process::result(output: Str::replace('{folder}', $this->folder, $output), exitCode: $exitCode)]);

    expect(new ServerProgramFinder([$this->folder])->detect(ServerProgram::Pandoc))->toBeNull();
})->with([
    'not found' => ['', 1],
    'a file that cannot be run' => ['{folder}/pandoc', 0],
    'a relative path' => ['bin/pandoc', 0],
    'another program' => ['/bin/sh', 0],
]);

it('runs the real which', function (): void {
    expect(new ServerProgramFinder([$this->folder])->detect(ServerProgram::Pdfinfo))->toBeString()->toEndWith('/pdfinfo');
});
