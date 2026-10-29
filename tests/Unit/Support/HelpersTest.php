<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * prompt() renders a file from resources/prompts, so every name the code
 * passes it must have a file; a missing one would only fail at chat time.
 */
it('has a file for every prompt the code renders', function (): void {
    $used = collect(File::allFiles(base_path('src')))
        ->flatMap(fn (SplFileInfo $file): array => Str::matchAll("/\\bprompt\\('([A-Za-z0-9\\/._-]+)'/", File::get($file->getPathname()))->all())
        ->map(fn (string $name): string => Str::replace('.', '/', $name))
        ->unique()
        ->values();

    expect($used)->not->toBeEmpty();

    foreach ($used as $name) {
        expect(resource_path("prompts/{$name}.blade.php"))->toBeFile("prompt('{$name}') has no file");
    }
});
