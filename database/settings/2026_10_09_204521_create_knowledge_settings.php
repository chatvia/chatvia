<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The rows of App\Admin\Settings\KnowledgeSettings, all empty: an empty value
 * keeps its config default until an admin saves one.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('knowledge', function (SettingsBlueprint $group): void {
            $group->add('hybrid_search');
            $group->add('search_mode');
            $group->add('rerank');
            $group->add('relevance_threshold');
            $group->add('max_chunks');
            $group->add('query_rewriter');
            $group->add('enhance_chunks');
            $group->add('enhance_text_chunks');
            $group->addEncrypted('firecrawl_api_key');
            $group->add('firecrawl_endpoint');
            $group->add('firecrawl_rate_per_minute');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'knowledge')->delete();
    }
};
