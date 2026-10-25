<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The rows of App\Admin\Settings\AiProviderSettings, all empty: an empty
 * value keeps its config default until an admin saves one.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('ai_providers', function (SettingsBlueprint $group): void {
            $group->addEncrypted('scaleway_api_key');
            $group->add('scaleway_endpoint');
            $group->addEncrypted('openrouter_api_key');
            $group->addEncrypted('elevenlabs_api_key');
            $group->add('elevenlabs_endpoint');
            $group->addEncrypted('openai_api_key');
            $group->add('openai_api_endpoint');
            $group->addEncrypted('mistral_api_key');
            $group->add('mistral_base_url');
            $group->addEncrypted('gemini_api_key');
            $group->add('gemini_api_endpoint');
            $group->addEncrypted('groq_api_key');
            $group->addEncrypted('anthropic_api_key');
            $group->addEncrypted('cohere_api_key');
            $group->add('cohere_api_endpoint');
            $group->addEncrypted('jina_api_key');
            $group->add('jina_api_endpoint');
            $group->addEncrypted('voyage_api_key');
            $group->add('voyage_api_endpoint');
            $group->addEncrypted('azure_api_key');
            $group->add('azure_endpoint');
            $group->addEncrypted('azure_openai_api_key');
            $group->add('azure_openai_endpoint');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'ai_providers')->delete();
    }
};
