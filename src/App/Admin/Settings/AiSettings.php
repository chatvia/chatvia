<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Llm\Enums\LlmTask;
use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * What runs on which AI provider (Settings → AI models): which service
 * handles each step (embeddings, reranking, document reading, speech to
 * text, voice conversations, reading aloud, image, audio and video
 * generation), the models those services call, how many files agents make
 * in one chat, how long an audio file may be and how long and sharp a video
 * is, and the catalog model behind each of
 * the app's own tasks ({@see LlmTask}: one shared model, and one per task).
 * The providers' keys are in {@see AiProviderSettings}; chat models are rows
 * (`llms`). The services are picked in App\Providers\DomainServiceProvider
 * from `chatvia.ai.*`.
 *
 * The embedding provider and its model decide the stored vectors: a change
 * of either ({@see embeddingFor()}) embeds everything again.
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

    #[ConfigKey('chatvia.ai.voice')]
    public ?string $voice = null;

    #[ConfigKey('chatvia.ai.speaker')]
    public ?string $speaker = null;

    #[ConfigKey('chatvia.ai.image_generator')]
    public ?string $image_generator = null;

    #[ConfigKey('chatvia.ai.images_per_chat_per_hour')]
    public ?int $images_per_chat_per_hour = null;

    #[ConfigKey('chatvia.ai.image_model_choice')]
    public ?bool $image_model_choice = null;

    /** @var list<string>|null */
    #[ConfigKey('chatvia.ai.image_models')]
    public ?array $image_models = null;

    #[ConfigKey('chatvia.ai.audio_generator')]
    public ?string $audio_generator = null;

    #[ConfigKey('chatvia.ai.audio_per_chat_per_hour')]
    public ?int $audio_per_chat_per_hour = null;

    #[ConfigKey('chatvia.ai.audio_max_characters')]
    public ?int $audio_max_characters = null;

    #[ConfigKey('chatvia.ai.video_generator')]
    public ?string $video_generator = null;

    #[ConfigKey('chatvia.ai.videos_per_chat_per_hour')]
    public ?int $videos_per_chat_per_hour = null;

    #[ConfigKey('chatvia.ai.video_model_choice')]
    public ?bool $video_model_choice = null;

    /** @var list<string>|null */
    #[ConfigKey('chatvia.ai.video_models')]
    public ?array $video_models = null;

    #[ConfigKey('chatvia.ai.video_duration')]
    public ?int $video_duration = null;

    #[ConfigKey('chatvia.ai.video_resolution')]
    public ?string $video_resolution = null;

    #[ConfigKey('chatvia.ai.chat_files.enabled')]
    public ?bool $chat_files_enabled = null;

    #[ConfigKey('chatvia.ai.chat_files.model')]
    public ?string $chat_files_model = null;

    #[ConfigKey('chatvia.ai.source_summaries.enabled')]
    public ?bool $source_summaries_enabled = null;

    #[ConfigKey('chatvia.ai.source_summaries.model')]
    public ?string $source_summaries_model = null;

    #[ConfigKey('chatvia.ai.chunker.describe_images')]
    public ?bool $describe_images = null;

    #[ConfigKey('chatvia.agent.default_model')]
    public ?string $default_agent_model = null;

    #[ConfigKey('chatvia.ai.tasks.default')]
    public ?string $task_model = null;

    #[ConfigKey('chatvia.ai.tasks.follow_up')]
    public ?string $follow_up_model = null;

    #[ConfigKey('chatvia.ai.tasks.tool_routing')]
    public ?string $tool_routing_model = null;

    #[ConfigKey('chatvia.ai.tasks.guardrails')]
    public ?string $guardrails_model = null;

    #[ConfigKey('chatvia.ai.tasks.titles')]
    public ?string $titles_model = null;

    #[ConfigKey('chatvia.ai.tasks.conversation_analysis')]
    public ?string $conversation_analysis_model = null;

    #[ConfigKey('chatvia.ai.tasks.starters')]
    public ?string $starters_model = null;

    #[ConfigKey('chatvia.ai.tasks.translation')]
    public ?string $translation_model = null;

    #[ConfigKey('chatvia.ai.tasks.form_text')]
    public ?string $form_text_model = null;

    #[ConfigKey('chatvia.ai.tasks.chat_address')]
    public ?string $chat_address_model = null;

    #[ConfigKey('chatvia.ai.tasks.enhancement')]
    public ?string $enhancement_model = null;

    #[ConfigKey('chatvia.ai.tasks.tables')]
    public ?string $tables_model = null;

    #[ConfigKey('chatvia.ai.tasks.images')]
    public ?string $images_model = null;

    #[ConfigKey('chatvia.ai.tasks.eval_judge')]
    public ?string $eval_judge_model = null;

    #[ConfigKey('chatvia.agent.history_messages')]
    public ?int $history_messages = null;

    #[ConfigKey('chatvia.ai.reasoning.enabled')]
    public ?bool $allow_reasoning = null;

    #[ConfigKey('chatvia.ai.chatter_model_choice')]
    public ?bool $chatter_model_choice = null;

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

    #[ConfigKey('services.voyage.embedding_model')]
    public ?string $voyage_embedding_model = null;

    #[ConfigKey('services.openai.embedding_model')]
    public ?string $openai_embedding_model = null;

    #[ConfigKey('services.openai.transcription_model')]
    public ?string $openai_transcription_model = null;

    #[ConfigKey('services.openai.realtime_model')]
    public ?string $openai_realtime_model = null;

    #[ConfigKey('services.openai.realtime_voice')]
    public ?string $openai_realtime_voice = null;

    #[ConfigKey('services.openai.realtime_transcription_model')]
    public ?string $openai_realtime_transcription_model = null;

    #[ConfigKey('services.openai.speech_model')]
    public ?string $openai_speech_model = null;

    #[ConfigKey('services.openai.speech_voice')]
    public ?string $openai_speech_voice = null;

    #[ConfigKey('services.elevenlabs.speech_model')]
    public ?string $elevenlabs_speech_model = null;

    #[ConfigKey('services.elevenlabs.speech_voice')]
    public ?string $elevenlabs_speech_voice = null;

    #[ConfigKey('services.openai.image_model')]
    public ?string $openai_image_model = null;

    #[ConfigKey('services.openrouter.image_model')]
    public ?string $openrouter_image_model = null;

    #[ConfigKey('services.gemini.image_model')]
    public ?string $google_image_model = null;

    #[ConfigKey('services.fal.image_model')]
    public ?string $fal_image_model = null;

    #[ConfigKey('services.gemini.video_model')]
    public ?string $google_video_model = null;

    #[ConfigKey('services.elevenlabs.video_model')]
    public ?string $elevenlabs_video_model = null;

    #[ConfigKey('services.kling.video_model')]
    public ?string $kling_video_model = null;

    #[ConfigKey('services.byteplus.video_model')]
    public ?string $byteplus_video_model = null;

    #[ConfigKey('services.runway.video_model')]
    public ?string $runway_video_model = null;

    #[ConfigKey('services.fal.video_model')]
    public ?string $fal_video_model = null;

    #[ConfigKey('chatvia.voice.max_call_minutes')]
    public ?int $voice_max_call_minutes = null;

    #[ConfigKey('services.gemini.embedding_model')]
    public ?string $gemini_embedding_model = null;

    #[ConfigKey('services.openrouter.embedding_model')]
    public ?string $openrouter_embedding_model = null;

    public static function group(): string
    {
        return 'ai';
    }

    /**
     * The embedding service and model these values put in use, as "service
     * model"; a blank value uses its default. Vectors made with two different
     * ones cannot be compared.
     *
     * @param  array<string, mixed>  $values  property => value
     */
    public static function embeddingFor(array $values): string
    {
        $value = static fn (string $property): string => (string) (filled($values[$property] ?? null)
            ? $values[$property]
            : app(SettingsConfig::class)->defaultOf(self::class, $property));

        $service = $value('embedding_provider');
        $provider = EmbeddingProvider::tryFrom($service);
        $model = $provider instanceof EmbeddingProvider ? self::propertyFor($provider->modelConfig()) : null;

        return $model === null ? $service : "{$service} ".$value($model);
    }
}
