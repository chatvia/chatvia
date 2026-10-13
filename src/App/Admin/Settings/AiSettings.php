<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * Which AI service handles each step (embeddings, reranking, document
 * reading, speech to text), the catalog model behind each of the app's own
 * jobs (LlmRole), and the key, address and models of the services that are
 * not chat providers. Chat providers and their models are rows
 * (`llm_providers`, `llms`), not settings. Text splitting is in
 * {@see ChunkingSettings}. The services are picked in
 * App\Providers\DomainServiceProvider from `chatvia.ai.*`.
 *
 * The embedding provider and its model decide the stored vectors, so the panel
 * locks them once a source is trained.
 */
class AiSettings extends AdminSettings
{
    #[ConfigKey('chatvia.ai.models.embedding')]
    public ?string $embedding_provider = null;

    #[ConfigKey('chatvia.ai.reranker')]
    public ?string $reranker = null;

    #[ConfigKey('chatvia.ai.document_parser')]
    public ?string $document_parser = null;

    #[ConfigKey('chatvia.ai.transcriber')]
    public ?string $transcriber = null;

    #[ConfigKey('chatvia.ai.document_llm')]
    public ?string $document_llm = null;

    #[ConfigKey('chatvia.agent.default_model')]
    public ?string $default_agent_model = null;

    #[ConfigKey('chatvia.ai.roles.fast')]
    public ?string $fast_model = null;

    #[ConfigKey('chatvia.ai.roles.smart')]
    public ?string $smart_model = null;

    #[ConfigKey('chatvia.ai.roles.vision')]
    public ?string $vision_model = null;

    #[ConfigKey('chatvia.agent.history_messages')]
    public ?int $history_messages = null;

    #[ConfigKey('chatvia.ai.models.summary')]
    public ?string $summary_model = null;

    #[ConfigKey('chatvia.ai.models.analysis')]
    public ?string $analysis_model = null;

    #[ConfigKey('chatvia.ai.models.document_chat')]
    public ?string $document_chat_model = null;

    #[ConfigKey('services.scalewayai.api_key'), ShouldBeEncrypted]
    public ?string $scaleway_api_key = null;

    #[ConfigKey('services.scalewayai.endpoint')]
    public ?string $scaleway_endpoint = null;

    #[ConfigKey('services.scalewayai.embedding_model')]
    public ?string $scaleway_embedding_model = null;

    #[ConfigKey('services.scalewayai.rerank_model')]
    public ?string $scaleway_rerank_model = null;

    #[ConfigKey('services.scalewayai.transcription_model')]
    public ?string $scaleway_transcription_model = null;

    #[ConfigKey('services.mistral.api_key'), ShouldBeEncrypted]
    public ?string $mistral_api_key = null;

    #[ConfigKey('services.mistral.base_url')]
    public ?string $mistral_base_url = null;

    #[ConfigKey('services.mistral.ocr_model')]
    public ?string $mistral_ocr_model = null;

    #[ConfigKey('services.mistral.embedding_model')]
    public ?string $mistral_embedding_model = null;

    #[ConfigKey('services.cohere.api_key'), ShouldBeEncrypted]
    public ?string $cohere_api_key = null;

    #[ConfigKey('services.cohere.api_endpoint')]
    public ?string $cohere_api_endpoint = null;

    #[ConfigKey('services.cohere.embedding_model')]
    public ?string $cohere_embedding_model = null;

    #[ConfigKey('services.cohere.rerank_model')]
    public ?string $cohere_rerank_model = null;

    #[ConfigKey('services.jina.api_key'), ShouldBeEncrypted]
    public ?string $jina_api_key = null;

    #[ConfigKey('services.jina.api_endpoint')]
    public ?string $jina_api_endpoint = null;

    #[ConfigKey('services.jina.embedding_model')]
    public ?string $jina_embedding_model = null;

    #[ConfigKey('services.openai.api_key'), ShouldBeEncrypted]
    public ?string $openai_api_key = null;

    #[ConfigKey('services.openai.api_endpoint')]
    public ?string $openai_api_endpoint = null;

    #[ConfigKey('services.openai.embedding_model')]
    public ?string $openai_embedding_model = null;

    #[ConfigKey('services.openai.transcription_model')]
    public ?string $openai_transcription_model = null;

    #[ConfigKey('services.gemini.api_key'), ShouldBeEncrypted]
    public ?string $gemini_api_key = null;

    #[ConfigKey('services.gemini.api_endpoint')]
    public ?string $gemini_api_endpoint = null;

    #[ConfigKey('services.gemini.embedding_model')]
    public ?string $gemini_embedding_model = null;

    public static function group(): string
    {
        return 'ai';
    }
}
