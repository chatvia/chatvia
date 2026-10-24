<?php

use Domain\Knowledge\Source\File\Enums\KnowledgeFileType;
use Domain\Llm\Enums\AiProvider;

/**
 * The file types workspaces may upload now: the ones the admin turned on that
 * a service can read. PDF, Word, slides and images need a document reader;
 * text, spreadsheets and ebooks the app reads itself.
 */
it('offers PDF, Word, slides and images only with a reader', function (): void {
    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }

    config([
        'services.azure_document_intelligence.endpoint' => null,
        'chatvia.ai.document_parser' => 'auto',
        'chatvia.knowledge.file_types' => array_column(KnowledgeFileType::cases(), 'value'),
    ]);
    $values = fn (): array => array_map(fn (KnowledgeFileType $type): string => $type->value, KnowledgeFileType::enabled());

    expect($values())->not->toContain('pdf', 'word', 'presentation', 'image')
        ->and($values())->toContain('text', 'spreadsheet', 'ebook');

    config(['services.azure_document_intelligence.key' => 'az-key', 'services.azure_document_intelligence.endpoint' => 'https://docs.cognitiveservices.azure.com/']);

    expect($values())->toContain('pdf', 'word', 'presentation', 'image');
});
