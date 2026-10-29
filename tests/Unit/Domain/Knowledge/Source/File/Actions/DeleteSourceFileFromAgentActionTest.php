<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Actions\DeleteSourceFileFromAgentAction;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Bus\BatchRepository;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;

/**
 * Deleting one file of the agent: a file still being ingested has its chunk
 * batch cancelled first, so no job writes chunks of a deleted file.
 */
beforeEach(function (): void {
    Storage::fake();
});

it('cancels the chunk batch of a file that is still being ingested, then deletes it', function (): void {
    $agent = Agent::factory()->create();
    $batchId = app(BatchRepository::class)->store(Bus::batch([]))->id;
    $file = AgentFileFactory::new()->create([
        'workspace_id' => $agent->workspace_id,
        'status' => SourceStatus::Processing,
        'internal_chunking_batch_id' => $batchId,
    ]);
    $agent->files()->attach($file->id);

    app(DeleteSourceFileFromAgentAction::class)->execute($agent, $file);

    expect(Bus::findBatch($batchId)->cancelled())->toBeTrue()
        ->and(SourceFile::query()->find($file->id))->toBeNull();
});
