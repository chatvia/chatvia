<?php

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
            $group->addEncrypted('scaleway_api_key');
            $group->add('scaleway_endpoint');
            $group->add('scaleway_embedding_model');
            $group->add('scaleway_rerank_model');
            $group->add('scaleway_transcription_model');
            $group->addEncrypted('mistral_api_key');
            $group->add('mistral_base_url');
            $group->add('mistral_ocr_model');
            $group->add('mistral_embedding_model');
            $group->addEncrypted('cohere_api_key');
            $group->add('cohere_api_endpoint');
            $group->add('cohere_embedding_model');
            $group->add('cohere_rerank_model');
            $group->addEncrypted('jina_api_key');
            $group->add('jina_api_endpoint');
            $group->add('jina_embedding_model');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'ai')->delete();
    }
};
