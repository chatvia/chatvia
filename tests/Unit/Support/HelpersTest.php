<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * The small global helpers in src/Support/helpers.php: file extensions,
 * `{placeholder}` templates, temp files and folders, and prompt(), which
 * renders a prompt from resources/prompts as raw text.
 */
it('reads the lowercased extension from a path or filename', function (): void {
    expect(file_extension('/tmp/Report.PDF'))->toBe('pdf')
        ->and(file_extension('deck.PPTX'))->toBe('pptx')
        ->and(file_extension('/var/x/book.EPUB'))->toBe('epub')
        ->and(file_extension('noextension'))->toBe('');
});

it('fills {placeholders} in a template', function (): void {
    expect(template('credits:{id}:{kind}', ['id' => 42, 'kind' => 'chat']))->toBe('credits:42:chat')
        ->and(template('no placeholders', ['id' => 1]))->toBe('no placeholders');
});

it('writes a temp file that keeps the extension of the given name', function (): void {
    $path = deleteAfterTest(create_temp_file('notes.md', '# hello'));

    expect($path)->toEndWith('.md')
        ->and(File::get($path))->toBe('# hello');
});

it('creates a missing temp subdirectory', function (): void {
    $suffix = '/helpers-test-'.Str::lower(Str::random(10));
    $dir = deleteAfterTest(create_temp_dir($suffix));

    expect($dir)->toBe(sys_get_temp_dir().$suffix)
        ->and(File::isDirectory($dir))->toBeTrue();
});

it('renders a prompt by its slash-style name', function (string $name, array $data, string $expected): void {
    expect(prompt($name, $data))->toContain($expected);
})->with([
    'a prompt' => ['knowledge/system', [], '### Role'],
    'a prompt in a nested folder' => ['business/profile', ['email' => 'hi@example.com', 'phone' => null, 'website' => null, 'address' => null, 'opening_hours' => null], 'hi@example.com'],
]);

it('puts variables into a prompt without HTML-escaping them', function (): void {
    $rendered = prompt('knowledge/rewrite-query-user', ['conversation' => 'Visitor: <b>hi</b> & co', 'message' => 'and the price?']);

    expect($rendered)->toContain('<b>hi</b> & co');
});

it('has a file for every prompt the code renders', function (): void {
    $used = collect(File::allFiles(base_path('src')))
        ->flatMap(fn (SplFileInfo $file): array => Str::matchAll("/\\bprompt\\('([a-z0-9\\/._-]+)'/", File::get($file->getPathname()))->all())
        ->map(fn (string $name): string => Str::replace('.', '/', $name))
        ->unique()
        ->values();

    expect($used)->not->toBeEmpty();

    foreach ($used as $name) {
        expect(resource_path("prompts/{$name}.blade.php"))->toBeFile("prompt('{$name}') has no file");
    }
});
