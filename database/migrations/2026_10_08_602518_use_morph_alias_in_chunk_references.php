<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `chunk_references.referenceable_type` was written as a PHP class name,
 * while the app reads it through the enforced morph map (`agent_website_url`).
 * The class has also changed namespace over time, so older rows hold names
 * the code no longer matches at all. Rewrite every class-name value to its
 * morph alias, matching on the class basename so any past namespace is
 * covered. A row that would collide with an existing alias row is the same
 * link twice, so it is dropped instead of updated.
 */
return new class extends Migration
{
    /** Class basename → morph alias (see AppServiceProvider::enforceMorphMap). */
    private const array ALIASES = [
        'AgentWebsiteUrl' => 'agent_website_url',
        'SourceWebsite' => 'source_website',
        'SourceFile' => 'source_file',
        'SourceText' => 'source_text',
        'SourceYoutubeVideo' => 'source_youtube_video',
    ];

    public function up(): void
    {
        $types = DB::table('chunk_references')
            ->distinct()
            ->pluck('referenceable_type')
            ->filter(fn (string $type): bool => str_contains($type, '\\'));

        foreach ($types as $legacy) {
            $alias = self::ALIASES[class_basename($legacy)] ?? null;

            if ($alias === null) {
                continue;
            }

            DB::transaction(function () use ($legacy, $alias): void {
                DB::statement(
                    'DELETE FROM chunk_references AS legacy
                     USING chunk_references AS current
                     WHERE legacy.referenceable_type = ?
                       AND current.referenceable_type = ?
                       AND current.chunk_id = legacy.chunk_id
                       AND current.referenceable_id = legacy.referenceable_id',
                    [$legacy, $alias],
                );

                DB::table('chunk_references')
                    ->where('referenceable_type', $legacy)
                    ->update(['referenceable_type' => $alias]);
            });
        }
    }

    public function down(): void
    {
        // The alias is the correct value; nothing to restore.
    }
};
