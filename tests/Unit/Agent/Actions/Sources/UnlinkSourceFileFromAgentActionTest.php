<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Actions\Sources\UnlinkSourceFileFromAgentAction;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Models\SourceFile;

/**
 * Unlinking takes a knowledge file away from one agent but keeps the file in
 * the workspace pool. A file shared by several agents is refused: it can only
 * be managed from the pool, so the action leaves every link in place.
 */
it('unlinks a file used only by this agent and keeps the file', function (): void {
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['user_id' => $agent->user_id]);
    $agent->files()->attach($file->id);

    $unlinked = app(UnlinkSourceFileFromAgentAction::class)->execute($agent, $file);

    expect($unlinked)->toBeTrue()
        ->and($agent->files()->count())->toBe(0)
        ->and(SourceFile::query()->whereKey($file->id)->exists())->toBeTrue();
});

it('refuses a file linked to several agents and changes nothing', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['user_id' => $agent->user_id, 'team_id' => $agent->team_id]);
    $file = AgentFileFactory::new()->create(['user_id' => $agent->user_id]);
    $agent->files()->attach($file->id);
    $other->files()->attach($file->id);

    $unlinked = app(UnlinkSourceFileFromAgentAction::class)->execute($agent, $file);

    expect($unlinked)->toBeFalse()
        ->and($agent->files()->whereKey($file->id)->exists())->toBeTrue()
        ->and($other->files()->whereKey($file->id)->exists())->toBeTrue();
});
