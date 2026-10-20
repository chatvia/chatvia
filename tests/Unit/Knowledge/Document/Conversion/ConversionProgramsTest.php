<?php

use Domain\Knowledge\Document\Conversion\ConversionPrograms;
use Domain\Llm\Enums\AiProvider;

/**
 * ConversionPrograms names the program a document format needs on this
 * server (pandoc, catdoc, catppt), unless the document reader in use reads
 * the format itself, and says when it is missing. The ebooks need none.
 */
beforeEach(function (): void {
    config([
        'services.bin.pandoc' => '/nowhere/pandoc',
        'services.bin.catdoc' => '/nowhere/catdoc',
        'services.bin.catppt' => '/bin/sh',
    ]);
});

it('names the missing program of each format the app reads with one', function (): void {
    config(['chatvia.ai.document_parser' => 'none']);
    $programs = app(ConversionPrograms::class);

    expect($programs->missingFor('rtf'))->toBe('pandoc')
        ->and($programs->missingFor('docx'))->toBe('pandoc')
        ->and($programs->missingFor('doc'))->toBe('catdoc')
        // catppt is found.
        ->and($programs->missingFor('ppt'))->toBeNull()
        ->and($programs->programFor('ppt'))->toBe('catppt');
});

it('needs no program for a format the app reads in PHP', function (string $extension): void {
    expect(app(ConversionPrograms::class)->programFor($extension))->toBeNull();
})->with(['pptx', 'xlsx', 'csv', 'txt', 'md', 'pdf', 'png', 'EPUB', 'fb2']);

it('needs no program for a format the document reader reads itself', function (): void {
    connectAiProviders(AiProvider::Mistral);
    config(['chatvia.ai.document_parser' => 'mistral']);
    $programs = app(ConversionPrograms::class);

    expect($programs->missingFor('docx'))->toBeNull()
        ->and($programs->missingFor('rtf'))->toBe('pandoc');
});
