<?php

declare(strict_types=1);

use App\Admin\Settings\ServerProgram;

/**
 * The programs of Settings → Platform → Programs: the path field takes only
 * the program's own name, or a full path that ends in it, so the panel
 * cannot make the server run another command.
 */
it('accepts only the program name or a full path that ends in it', function (string $path, bool $accepted): void {
    expect(ServerProgram::Pdfinfo->acceptsPath($path))->toBe($accepted);
})->with([
    'its name' => ['pdfinfo', true],
    'a system path' => ['/usr/bin/pdfinfo', true],
    'a path with a space' => ['/Applications/My Tools/pdfinfo', true],
    'another program' => ['/bin/sh', false],
    'a folder' => ['/usr/bin/pdfinfo/', false],
    'a relative path' => ['usr/bin/pdfinfo', false],
    'another extension' => ['/usr/bin/pdfinfo.sh', false],
    'a line break' => ["/usr/bin\n/pdfinfo", false],
    'another case' => ['PDFINFO', false],
]);
