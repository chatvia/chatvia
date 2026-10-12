<?php

use Illuminate\Support\Facades\DB;
use Spatie\LaravelSettings\Migrations\SettingsBlueprint;
use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * The rows of App\Admin\Settings\ChunkingSettings, all empty: an empty value
 * keeps its config default until an admin saves one.
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->inGroup('chunking', function (SettingsBlueprint $group): void {
            $group->add('text_splitter');
            $group->add('chunker_url');
            $group->addEncrypted('chunker_api_key');
            $group->add('strategy');
            $group->add('tokenizer');
            $group->add('chunk_size');
            $group->add('chunk_overlap');
            $group->add('length_unit');
            $group->add('language');
            $group->add('separators');
            $group->add('separator');
            $group->add('max_header_level');
            $group->add('strip_headers');
            $group->add('buffer_size');
            $group->add('breakpoint_percentile_threshold');
            $group->add('min_sentences_per_chunk');
            $group->add('min_characters_per_chunk');
            $group->add('threshold');
            $group->add('similarity_window');
            $group->add('local_chunk_tokens');
            $group->add('local_overlap_tokens');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'chunking')->delete();
    }
};
