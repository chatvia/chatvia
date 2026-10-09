<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Actions\Account\DeleteAgentAction;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Session;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Support\Facades\Storage;

/**
 * Deleting an agent removes its rows in one transaction and wipes its storage
 * subtree once they are gone; another agent's data is untouched.
 */
it('deletes the agent, its sessions and its storage subtree', function (): void {
    Storage::fake();
    Storage::fake('public');

    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['user_id' => $agent->user_id]);
    $prefix = 'users/'.$agent->workspaceExternalId().'/agents/';

    Session::factory()->create(['agent_id' => $agent->id]);
    $kept = Session::factory()->create(['agent_id' => $other->id]);
    Storage::put($prefix.$agent->external_id.'/train/file.txt', 'x');
    Storage::disk('public')->put($prefix.$agent->external_id.'/attachments/a.png', 'x');
    Storage::put($prefix.$other->external_id.'/train/file.txt', 'x');

    app(DeleteAgentAction::class)->execute($agent);

    expect(Agent::find($agent->id))->toBeNull()
        ->and(Session::query()->where('agent_id', $agent->id)->exists())->toBeFalse()
        ->and(Session::find($kept->id))->not->toBeNull();

    Storage::assertMissing($prefix.$agent->external_id.'/train/file.txt');
    Storage::disk('public')->assertMissing($prefix.$agent->external_id.'/attachments/a.png');
    Storage::assertExists($prefix.$other->external_id.'/train/file.txt');
});

it('keeps the database rows when the delete fails part-way', function (): void {
    Storage::fake();

    $agent = Agent::factory()->create();
    Session::factory()->create(['agent_id' => $agent->id]);

    // Make the final row delete fail after the sessions were deleted.
    Agent::deleting(static fn (): bool => throw new RuntimeException('boom'));

    expect(fn () => app(DeleteAgentAction::class)->execute($agent))->toThrow(RuntimeException::class);

    expect(Agent::find($agent->id))->not->toBeNull()
        ->and(Session::query()->where('agent_id', $agent->id)->exists())->toBeTrue();
});

it('keeps the stored files of its sources when the delete fails part-way', function (): void {
    Storage::fake();

    $agent = Agent::factory()->create();
    $path = 'users/'.$agent->workspaceExternalId().'/agents/'.$agent->external_id.'/files/manual.pdf';
    Storage::put($path, '%PDF-1.7');
    $file = AgentFileFactory::new()->create(['user_id' => $agent->user_id, 'filepath' => $path]);
    $agent->files()->attach($file->id);

    // The exclusive file is deleted first; the agent row then fails.
    Agent::deleting(static fn (): bool => throw new RuntimeException('boom'));

    expect(fn () => app(DeleteAgentAction::class)->execute($agent))->toThrow(RuntimeException::class);

    expect(SourceFile::query()->find($file->id))->not->toBeNull();
    Storage::assertExists($path);
});
