<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Actions\ListUserKnowledgeFilesAction;

/**
 * The account-wide file list shows every file of the person's workspace with
 * the agent that uses it, and nothing of another workspace.
 */
it('lists the workspace files with the agent that uses each', function (): void {
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id, 'status' => 'ingested']);
    $agent->files()->attach($file->id);
    AgentFileFactory::new()->create(); // another workspace's file

    $rows = app(ListUserKnowledgeFilesAction::class)->execute($agent->workspace->owner);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->id)->toBe($file->id)
        ->and($rows[0]->agent->id)->toBe($agent->id)
        ->and($rows[0]->status)->toBe(SourceStatus::Completed);
});
