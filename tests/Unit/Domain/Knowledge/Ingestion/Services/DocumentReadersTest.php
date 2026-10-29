<?php

use Domain\Knowledge\Ingestion\Services\DocumentReaders;
use Domain\Llm\Enums\AiProvider;

/**
 * Which document reader reads PDF, Word, slides and images: Mistral OCR when
 * it is connected, else Azure Document Intelligence, else none (or none when
 * the admin says so).
 */
beforeEach(function (): void {
    foreach (AiProvider::cases() as $provider) {
        config([$provider->apiKeyConfig() => null]);
    }

    config(['services.azure_document_intelligence.endpoint' => null, 'chatvia.ai.document_parser' => 'auto']);
});

it('picks the reader: Mistral, else Azure, else none', function (): void {
    expect(app(DocumentReaders::class)->driver())->toBe('none');

    config(['services.azure_document_intelligence.key' => 'az-key', 'services.azure_document_intelligence.endpoint' => 'https://docs.cognitiveservices.azure.com/']);

    expect(app(DocumentReaders::class)->driver())->toBe('azure');

    connectAiProviders(AiProvider::Mistral);

    expect(app(DocumentReaders::class)->driver())->toBe('mistral');

    config(['chatvia.ai.document_parser' => 'none']);

    expect(app(DocumentReaders::class)->driver())->toBe('none')
        ->and(app(DocumentReaders::class)->available())->toBeFalse();
});
