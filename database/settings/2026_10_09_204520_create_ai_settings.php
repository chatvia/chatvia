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
            $group->add('document_llm');
            $group->add('default_agent_model');
            $group->add('fast_model');
            $group->add('smart_model');
            $group->add('vision_model');
            $group->add('history_messages');
            $group->add('summary_model');
            $group->add('analysis_model');
            $group->add('document_chat_model');
            $group->add('scaleway_embedding_model');
            $group->add('scaleway_transcription_model');
            $group->add('mistral_ocr_model');
            $group->add('mistral_embedding_model');
            $group->add('cohere_embedding_model');
            $group->add('cohere_rerank_model');
            $group->add('jina_embedding_model');
            $group->add('openai_embedding_model');
            $group->add('openai_transcription_model');
            $group->add('gemini_embedding_model');
            $group->add('openrouter_embedding_model');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'ai')->delete();
    }
};
