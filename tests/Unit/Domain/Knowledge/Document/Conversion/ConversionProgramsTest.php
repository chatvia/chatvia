<?php

use Domain\Knowledge\Document\Conversion\ConversionPrograms;
use Domain\Llm\Enums\AiProvider;

/**
 * ConversionPrograms names the program a document format needs on this
 * server and says when it is missing, unless the document reader in use
 * reads the format itself. None needs one: the app reads every format
 * itself, and pandoc, catdoc and catppt only help when they are installed.
 */
it('needs no program for the formats a program once read, with or without a document reader', function (): void {
    config([
        'services.bin.pandoc' => '/nowhere/pandoc',
        'services.bin.catdoc' => '/nowhere/catdoc',
        'services.bin.catppt' => '/nowhere/catppt',
        'chatvia.ai.document_parser' => 'none',
    ]);
    $programs = app(ConversionPrograms::class);

    expect($programs->programFor('doc'))->toBeNull()
        ->and($programs->missingFor('doc'))->toBeNull()
        ->and($programs->missingFor('ppt'))->toBeNull()
        ->and($programs->missingFor('rtf'))->toBeNull()
        ->and($programs->missingFor('EPUB'))->toBeNull();

    connectAiProviders(AiProvider::Mistral);
    config(['chatvia.ai.document_parser' => 'mistral']);

    expect(app(ConversionPrograms::class)->missingFor('docx'))->toBeNull();
});
