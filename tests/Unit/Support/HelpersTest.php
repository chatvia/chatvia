<?php

/**
 * The small global helpers in src/Support/helpers.php. prompt() has its own
 * test (PromptHelperTest).
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
    $path = create_temp_file('notes.md', '# hello');

    try {
        expect($path)->toEndWith('.md')
            ->and(file_get_contents($path))->toBe('# hello');
    } finally {
        @unlink($path);
    }
});

it('creates a missing temp subdirectory', function (): void {
    $suffix = '/helpers-test-'.uniqid();
    $dir = create_temp_dir($suffix);

    try {
        expect($dir)->toBe(sys_get_temp_dir().$suffix)
            ->and(is_dir($dir))->toBeTrue();
    } finally {
        @rmdir($dir);
    }
});
