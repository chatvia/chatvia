<?php

use Domain\Knowledge\Document\Conversion\ConversionPrograms;
use Domain\Llm\Enums\AiProvider;

/**
 * ConversionPrograms names the program a document format needs on this
 * server and says when it is missing, unless the document reader in use
 * reads the format itself. None needs one: the app reads every format
 * itself, and pandoc, catdoc and catppt only help when they are installed.
 */
beforeEach(function (): void {
    config([
        'services.bin.pandoc' => '/nowhere/pandoc',
        'services.bin.catdoc' => '/nowhere/catdoc',
        'services.bin.catppt' => '/nowhere/catppt',
    ]);
});

it('needs no program for any format, with or without a document reader', function (string $extension): void {
    config(['chatvia.ai.document_parser' => 'none']);
    $programs = app(ConversionPrograms::class);

    expect($programs->programFor($extension))->toBeNull()
        ->and($programs->missingFor($extension))->toBeNull();

    connectAiProviders(AiProvider::Mistral);
    config(['chatvia.ai.document_parser' => 'mistral']);

    expect(app(ConversionPrograms::class)->missingFor($extension))->toBeNull();
})->with(['rtf', 'doc', 'ppt', 'docx', 'odt', 'pptx', 'xlsx', 'csv', 'txt', 'md', 'pdf', 'png', 'EPUB', 'fb2']);
