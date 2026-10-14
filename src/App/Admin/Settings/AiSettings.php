<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * What runs on which AI provider (Settings → AI models): which service
 * handles each step (embeddings, reranking, document reading, speech to
 * text), the models those services call, and the catalog model behind each
 * of the app's own jobs (LlmRole). The providers' keys are in
 * {@see AiProviderSettings}; chat models are rows (`llms`). The services are
 * picked in App\Providers\DomainServiceProvider from `chatvia.ai.*`.
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

    #[ConfigKey('chatvia.ai.rerank.openrouter_model')]
    public ?string $openrouter_rerank_model = null;

    #[ConfigKey('chatvia.ai.rerank.custom.url')]
    public ?string $rerank_custom_url = null;

    #[ConfigKey('chatvia.ai.rerank.custom.key'), ShouldBeEncrypted]
    public ?string $rerank_custom_key = null;

    #[ConfigKey('chatvia.ai.rerank.custom.model')]
    public ?string $rerank_custom_model = null;

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

    #[ConfigKey('services.scalewayai.embedding_model')]
    public ?string $scaleway_embedding_model = null;

    #[ConfigKey('services.scalewayai.transcription_model')]
    public ?string $scaleway_transcription_model = null;

    #[ConfigKey('services.mistral.ocr_model')]
    public ?string $mistral_ocr_model = null;

    #[ConfigKey('services.mistral.embedding_model')]
    public ?string $mistral_embedding_model = null;

    #[ConfigKey('services.cohere.embedding_model')]
    public ?string $cohere_embedding_model = null;

    #[ConfigKey('services.cohere.rerank_model')]
    public ?string $cohere_rerank_model = null;

    #[ConfigKey('services.jina.embedding_model')]
    public ?string $jina_embedding_model = null;

    #[ConfigKey('services.openai.embedding_model')]
    public ?string $openai_embedding_model = null;

    #[ConfigKey('services.openai.transcription_model')]
    public ?string $openai_transcription_model = null;

    #[ConfigKey('services.gemini.embedding_model')]
    public ?string $gemini_embedding_model = null;

    #[ConfigKey('services.openrouter.embedding_model')]
    public ?string $openrouter_embedding_model = null;

    public static function group(): string
    {
        return 'ai';
    }
}
