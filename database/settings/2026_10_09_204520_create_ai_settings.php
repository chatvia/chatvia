<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The rows of App\Admin\Settings\AiSettings, all empty: an empty value
 * keeps its config default until an admin saves one.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('ai', function (SettingsBlueprint $group): void {
            $group->add('embedding_provider');
            $group->add('reranker');
            $group->add('openrouter_rerank_model');
            $group->add('rerank_custom_url');
            $group->addEncrypted('rerank_custom_key');
            $group->add('rerank_custom_model');
            $group->add('document_parser');
            $group->add('transcriber');
            $group->add('voice');
            $group->add('speaker');
            $group->add('image_generator');
            $group->add('images_per_chat_per_hour');
            $group->add('image_model_choice');
            $group->add('image_models');
            $group->add('audio_generator');
            $group->add('audio_per_chat_per_hour');
            $group->add('audio_max_characters');
            $group->add('video_generator');
            $group->add('videos_per_chat_per_hour');
            $group->add('video_model_choice');
            $group->add('video_models');
            $group->add('video_duration');
            $group->add('video_resolution');
            $group->add('chat_files_enabled');
            $group->add('chat_files_model');
            $group->add('source_summaries_enabled');
            $group->add('source_summaries_model');
            $group->add('describe_images');
            $group->add('default_agent_model');
            $group->add('task_model');
            $group->add('follow_up_model');
            $group->add('tool_routing_model');
            $group->add('guardrails_model');
            $group->add('titles_model');
            $group->add('conversation_analysis_model');
            $group->add('starters_model');
            $group->add('translation_model');
            $group->add('form_text_model');
            $group->add('chat_address_model');
            $group->add('enhancement_model');
            $group->add('tables_model');
            $group->add('images_model');
            $group->add('eval_judge_model');
            $group->add('history_messages');
            $group->add('allow_reasoning');
            $group->add('chatter_model_choice');
            $group->add('scaleway_embedding_model');
            $group->add('scaleway_transcription_model');
            $group->add('mistral_ocr_model');
            $group->add('mistral_embedding_model');
            $group->add('cohere_embedding_model');
            $group->add('cohere_rerank_model');
            $group->add('jina_embedding_model');
            $group->add('voyage_embedding_model');
            $group->add('openai_embedding_model');
            $group->add('openai_transcription_model');
            $group->add('openai_realtime_model');
            $group->add('openai_realtime_voice');
            $group->add('openai_speech_model');
            $group->add('openai_speech_voice');
            $group->add('elevenlabs_speech_model');
            $group->add('elevenlabs_speech_voice');
            $group->add('openai_image_model');
            $group->add('openrouter_image_model');
            $group->add('google_image_model');
            $group->add('fal_image_model');
            $group->add('google_video_model');
            $group->add('elevenlabs_video_model');
            $group->add('kling_video_model');
            $group->add('byteplus_video_model');
            $group->add('runway_video_model');
            $group->add('fal_video_model');
            $group->add('openai_realtime_transcription_model');
            $group->add('voice_max_call_minutes');
            $group->add('gemini_embedding_model');
            $group->add('openrouter_embedding_model');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'ai')->delete();
    }
};
