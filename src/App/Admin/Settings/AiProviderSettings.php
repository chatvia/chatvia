<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Domain\Llm\Enums\AiProvider;
use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * The API key (and address) of each AI provider account, set once in
 * Settings → AI providers and used for everything the provider does here:
 * its chat models, embeddings, speech to text, reading aloud, documents and
 * reranking ({@see AiProvider}). What runs on which provider is in {@see AiSettings}.
 */
class AiProviderSettings extends AdminSettings
{
    #[ConfigKey('services.scalewayai.api_key'), ShouldBeEncrypted]
    public ?string $scaleway_api_key = null;

    #[ConfigKey('services.scalewayai.endpoint')]
    public ?string $scaleway_endpoint = null;

    #[ConfigKey('services.openrouter.api_key'), ShouldBeEncrypted]
    public ?string $openrouter_api_key = null;

    #[ConfigKey('services.elevenlabs.api_key'), ShouldBeEncrypted]
    public ?string $elevenlabs_api_key = null;

    #[ConfigKey('services.elevenlabs.endpoint')]
    public ?string $elevenlabs_endpoint = null;

    #[ConfigKey('services.openai.api_key'), ShouldBeEncrypted]
    public ?string $openai_api_key = null;

    #[ConfigKey('services.openai.api_endpoint')]
    public ?string $openai_api_endpoint = null;

    #[ConfigKey('services.mistral.api_key'), ShouldBeEncrypted]
    public ?string $mistral_api_key = null;

    #[ConfigKey('services.mistral.base_url')]
    public ?string $mistral_base_url = null;

    #[ConfigKey('services.gemini.api_key'), ShouldBeEncrypted]
    public ?string $gemini_api_key = null;

    #[ConfigKey('services.gemini.api_endpoint')]
    public ?string $gemini_api_endpoint = null;

    #[ConfigKey('services.groq.api_key'), ShouldBeEncrypted]
    public ?string $groq_api_key = null;

    #[ConfigKey('services.anthropic.api_key'), ShouldBeEncrypted]
    public ?string $anthropic_api_key = null;

    #[ConfigKey('services.cohere.api_key'), ShouldBeEncrypted]
    public ?string $cohere_api_key = null;

    #[ConfigKey('services.cohere.api_endpoint')]
    public ?string $cohere_api_endpoint = null;

    #[ConfigKey('services.jina.api_key'), ShouldBeEncrypted]
    public ?string $jina_api_key = null;

    #[ConfigKey('services.jina.api_endpoint')]
    public ?string $jina_api_endpoint = null;

    #[ConfigKey('services.voyage.api_key'), ShouldBeEncrypted]
    public ?string $voyage_api_key = null;

    #[ConfigKey('services.voyage.api_endpoint')]
    public ?string $voyage_api_endpoint = null;

    #[ConfigKey('services.azure_document_intelligence.key'), ShouldBeEncrypted]
    public ?string $azure_api_key = null;

    #[ConfigKey('services.azure_document_intelligence.endpoint')]
    public ?string $azure_endpoint = null;

    #[ConfigKey('services.azure_openai.api_key'), ShouldBeEncrypted]
    public ?string $azure_openai_api_key = null;

    #[ConfigKey('services.azure_openai.endpoint')]
    public ?string $azure_openai_endpoint = null;

    public static function group(): string
    {
        return 'ai_providers';
    }
}
