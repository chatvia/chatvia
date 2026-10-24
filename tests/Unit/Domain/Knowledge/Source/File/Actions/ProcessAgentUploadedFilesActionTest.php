<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Jobs\Document\DocumentIngestionJob;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Actions\ProcessAgentUploadedFilesAction;
use Illuminate\Support\Facades\Bus;

/**
 * Training the agent's uploaded files: each file still waiting starts its
 * ingestion; trained files are left alone.
 */
it('starts training every uploaded file of the agent', function (): void {
    Bus::fake();

    $agent = Agent::factory()->create();
    $uploaded = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'filename' => 'a.pdf', 'mime_type' => 'application/pdf']);
    $trained = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'status' => 'ingested']);
    $agent->files()->attach([$uploaded->id, $trained->id]);

    app(ProcessAgentUploadedFilesAction::class)->execute($agent);

    expect($uploaded->fresh()->status)->toBe(SourceStatus::Processing)
        ->and($trained->fresh()->status)->toBe(SourceStatus::Completed);

    Bus::assertDispatchedTimes(DocumentIngestionJob::class, 1);
});
