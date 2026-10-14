<?php

declare(strict_types=1);

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
            $group->add('file_types');
            $group->add('enhance_chunks');
            $group->add('enhance_text_chunks');
            $group->addEncrypted('firecrawl_api_key');
            $group->add('firecrawl_endpoint');
            $group->add('firecrawl_rate_per_minute');
            $group->add('chunk_unit');
            $group->add('chunk_size');
            $group->add('chunk_overlap');
            $group->add('markdown_strategy');
            $group->add('text_strategy');
            $group->add('transcript_strategy');
            $group->add('tokenizer');
            $group->add('hybrid_search');
            $group->add('search_mode');
            $group->add('relevance_check');
            $group->add('relevance_threshold');
            $group->add('max_chunks');
            $group->add('max_context_tokens');
            $group->add('similarity_threshold');
            $group->add('qa_similarity_threshold');
            $group->add('query_rewriter');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'knowledge')->delete();
    }
};
