<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Chunks\CreateSourceChunkAction;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Support\Str;

/**
 * Regression: the action deduped with exists()->create(), so two concurrent
 * page jobs carrying the same checksum (blank/duplicate pages are an expected
 * case) could both pass the check and the second insert failed the job — and
 * with it the whole ingestion batch. createOrFirst absorbs the unique
 * violation and preserves the null-on-duplicate contract.
 */
it('returns the chunk on first ingest and null on a duplicate checksum', function (): void {
    $agent = Agent::factory()->create();

    $source = SourceText::create([
        'user_id' => $agent->user_id,
        'filepath' => 'users/'.$agent->workspaceExternalId().'/agents/'.$agent->external_id.'/train/text/'.Str::uuid()->toString().'.txt',
        'excerpt' => 'dedup',
        'length' => 1,
        'checksum' => md5(Str::uuid()->toString()),
    ]);
    $agent->texts()->attach($source->id);

    $page = new PageData(
        pageNumber: 1,
        content: 'Identical page content',
        checksum: hash('xxh128', 'Identical page content'),
    );

    $action = app(CreateSourceChunkAction::class);

    $first = $action->execute($agent, $source, $page);
    $second = $action->execute($agent, $source, $page);

    expect($first)->not->toBeNull()
        ->and($first->wasRecentlyCreated)->toBeTrue()
        ->and($second)->toBeNull()
        ->and($source->chunks()->count())->toBe(1);
});
