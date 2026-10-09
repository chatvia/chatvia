<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Actions\Sources\UnlinkSourceFileFromAgentAction;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\File\Models\SourceFile;

/**
 * Unlinking takes a shared knowledge file away from one agent and keeps it for
 * the other agents and in the workspace pool. The dashboard only offers it for
 * a file another agent also uses; the last link is refused (the owner deletes
 * the file instead), so no file is left in the pool outside the quota count.
 */
it('unlinks a shared file from this agent only and keeps the file', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['user_id' => $agent->user_id, 'team_id' => $agent->team_id]);
    $file = AgentFileFactory::new()->create(['user_id' => $agent->user_id]);
    $agent->files()->attach($file->id);
    $other->files()->attach($file->id);

    $unlinked = app(UnlinkSourceFileFromAgentAction::class)->execute($agent, $file);

    expect($unlinked)->toBeTrue()
        ->and($agent->files()->whereKey($file->id)->exists())->toBeFalse()
        ->and($other->files()->whereKey($file->id)->exists())->toBeTrue()
        ->and(SourceFile::query()->whereKey($file->id)->exists())->toBeTrue();
});

it('refuses the last link of a file and changes nothing', function (): void {
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['user_id' => $agent->user_id]);
    $agent->files()->attach($file->id);

    $unlinked = app(UnlinkSourceFileFromAgentAction::class)->execute($agent, $file);

    expect($unlinked)->toBeFalse()
        ->and($agent->files()->whereKey($file->id)->exists())->toBeTrue();
});

it('refuses a file this agent does not use', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['user_id' => $agent->user_id, 'team_id' => $agent->team_id]);
    $file = AgentFileFactory::new()->create(['user_id' => $agent->user_id]);
    $other->files()->attach($file->id);

    $unlinked = app(UnlinkSourceFileFromAgentAction::class)->execute($agent, $file);

    expect($unlinked)->toBeFalse()
        ->and($other->files()->whereKey($file->id)->exists())->toBeTrue();
});
