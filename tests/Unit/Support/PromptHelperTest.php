<?php

use Illuminate\Support\Facades\File;

it('renders a prompt blade view to a non-empty string', function (): void {
    expect(prompt('knowledge/system'))->toBeString()->not->toBe('');
});

it('interpolates variables without HTML-escaping the content', function (): void {
    $rendered = prompt('knowledge/rewrite-query-user', ['conversation' => 'Visitor: <b>hi</b> & co', 'message' => 'and the price?']);

    // {!! $conversation !!} keeps the raw text — no &lt;b&gt; / &amp; entities.
    expect($rendered)->toContain('<b>hi</b> & co');
});

it('accepts nested slash-style names', function (): void {
    $rendered = prompt('business/profile', [
        'email' => 'hi@example.com',
        'phone' => null,
        'website' => null,
        'address' => null,
        'opening_hours' => null,
    ]);

    expect($rendered)->toContain('hi@example.com');
});

it('has a file for every prompt the code renders', function (): void {
    $used = collect(File::allFiles(base_path('src')))
        ->flatMap(fn (SplFileInfo $file): array => preg_match_all("/\\bprompt\\('([a-z0-9\\/._-]+)'/", (string) file_get_contents($file->getPathname()), $m) ? $m[1] : [])
        ->map(fn (string $name): string => str_replace('.', '/', $name))
        ->unique()
        ->values();

    expect($used)->not->toBeEmpty();

    foreach ($used as $name) {
        expect(resource_path("prompts/{$name}.blade.php"))->toBeFile("prompt('{$name}') has no file");
    }
});
