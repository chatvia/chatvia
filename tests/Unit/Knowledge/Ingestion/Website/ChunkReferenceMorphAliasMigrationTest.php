<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Sources\CreateSourceChunkAction;
use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Illuminate\Support\Facades\DB;

/**
 * The forward data migration rewrites class-name values in
 * chunk_references.referenceable_type (from any past namespace) to the morph
 * alias, and drops rows that would duplicate an existing alias row.
 */
it('rewrites class names to the morph alias and drops duplicates', function (): void {
    $agent = Agent::factory()->create();
    $source = AgentFileFactory::new()->create();
    $agent->files()->attach($source->id);

    $create = app(CreateSourceChunkAction::class);
    $chunkA = $create->execute($agent, $source, new PageData(pageNumber: 1, content: 'Alpha page', checksum: 'mig-a-'.uniqid()));
    $chunkB = $create->execute($agent, $source, new PageData(pageNumber: 2, content: 'Beta page', checksum: 'mig-b-'.uniqid()));

    DB::table('chunk_references')->insert([
        // Current namespace.
        ['chunk_id' => $chunkA->id, 'referenceable_type' => 'Domain\\Knowledge\\Source\\Web\\Models\\AgentWebsiteUrl', 'referenceable_id' => 7],
        // An older namespace, same link as an alias row below → duplicate.
        ['chunk_id' => $chunkB->id, 'referenceable_type' => 'App\\Models\\AgentWebsiteUrl', 'referenceable_id' => 8],
        ['chunk_id' => $chunkB->id, 'referenceable_type' => 'agent_website_url', 'referenceable_id' => 8],
    ]);

    (require base_path('database/migrations/2026_10_08_602518_use_morph_alias_in_chunk_references.php'))->up();

    $rows = DB::table('chunk_references')
        ->whereIn('chunk_id', [$chunkA->id, $chunkB->id])
        ->orderBy('chunk_id')
        ->get(['chunk_id', 'referenceable_type', 'referenceable_id'])
        ->map(fn ($row): array => (array) $row)
        ->all();

    expect($rows)->toBe([
        ['chunk_id' => $chunkA->id, 'referenceable_type' => 'agent_website_url', 'referenceable_id' => 7],
        ['chunk_id' => $chunkB->id, 'referenceable_type' => 'agent_website_url', 'referenceable_id' => 8],
    ]);
});
