<?php

use App\Admin\Settings\ServerProgram;
use App\Admin\Settings\ServerProgramFinder;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

/**
 * ServerProgramFinder tells the Programs card whether this server finds a
 * program (file checks only: a file that cannot be run is no program), and
 * detects a program's full path with `which`, also in the folders the PATH
 * of the web server may miss.
 */
beforeEach(function (): void {
    $this->folder = sys_get_temp_dir().'/chatvia-finder-'.uniqid();
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
    $finder = new ServerProgramFinder;

    expect($finder->find("{$this->folder}/pdfinfo"))->toBe("{$this->folder}/pdfinfo")
        ->and($finder->find("{$this->folder}/pandoc"))->toBeNull()
        ->and($finder->find("{$this->folder}/ffmpeg"))->toBeNull();
});

it('finds a program name on the PATH', function (): void {
    putenv("PATH=/nowhere:{$this->folder}");
    $finder = new ServerProgramFinder;

    expect($finder->find('pdfinfo'))->toBe("{$this->folder}/pdfinfo")
        ->and($finder->find('pandoc'))->toBeNull();
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
