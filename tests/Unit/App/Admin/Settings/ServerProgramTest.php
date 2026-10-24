<?php

declare(strict_types=1);

use App\Admin\Settings\ServerProgram;

/**
 * The programs of Settings → Platform → Programs: which path the field
 * takes for each (only its own name, or a full path that ends in it, so the
 * panel cannot make the server run another command), and what the app loses
 * while one is missing, with the settings in use.
 */
it('says what each missing program stops, with the settings in use', function (): void {
    config(['chatvia.ai.transcriber' => 'none', 'laravel-pdf.driver' => 'gotenberg']);

    expect(ServerProgram::Pdfinfo->lossWhenMissing())->toBeNull()
        ->and(ServerProgram::Ffprobe->lossWhenMissing())->toBeNull()
        // Speech to text is off; another maker makes PDFs.
        ->and(ServerProgram::Ffmpeg->lossWhenMissing())->toBeNull()
        ->and(ServerProgram::Weasyprint->lossWhenMissing())->toBeNull()
        // The app reads every document format itself.
        ->and(ServerProgram::Pandoc->lossWhenMissing())->toBeNull()
        ->and(ServerProgram::Catdoc->lossWhenMissing())->toBeNull()
        ->and(ServerProgram::Catppt->lossWhenMissing())->toBeNull();

    config(['chatvia.ai.transcriber' => 'scaleway', 'services.scalewayai.api_key' => 'key', 'laravel-pdf.driver' => 'weasyprint']);

    expect(ServerProgram::Ffmpeg->lossWhenMissing())->toContain('Recordings over 25 MB')
        ->and(ServerProgram::Weasyprint->lossWhenMissing())->toContain('Chat PDF exports');
});

it('accepts only the program name or a full path that ends in it', function (string $path, bool $accepted): void {
    expect(ServerProgram::Pdfinfo->acceptsPath($path))->toBe($accepted);
})->with([
    'its name' => ['pdfinfo', true],
    'a system path' => ['/usr/bin/pdfinfo', true],
    'a Homebrew path' => ['/opt/homebrew/bin/pdfinfo', true],
    'a path with a space' => ['/Applications/My Tools/pdfinfo', true],
    'another program' => ['/bin/sh', false],
    'a folder' => ['/usr/bin/pdfinfo/', false],
    'a relative path' => ['usr/bin/pdfinfo', false],
    'a dot path' => ['./pdfinfo', false],
    'another extension' => ['/usr/bin/pdfinfo.sh', false],
    'a line break' => ["/usr/bin\n/pdfinfo", false],
    'another case' => ['PDFINFO', false],
]);
