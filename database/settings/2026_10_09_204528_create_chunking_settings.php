<?php

declare(strict_types=1);

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
            $group->add('chunk_unit');
            $group->add('chunk_size');
            $group->add('chunk_overlap');
            $group->add('markdown_strategy');
            $group->add('text_strategy');
            $group->add('transcript_strategy');
            $group->add('tokenizer');
        });
    }

    public function down(): void
    {
        DB::table('settings')->where('group', 'chunking')->delete();
    }
};
