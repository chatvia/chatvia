<?php

use Domain\Agent\Actions\Account\DeleteAgentAction;
use Domain\Agent\Jobs\PurgeAgentJob;
use Domain\Agent\Models\Agent;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Deleting an agent hides it at once (soft delete), frees its slug and custom
 * domain, silences its messaging channels and queues the purge of its data.
 */
it('hides the agent at once and queues its purge on the exports queue', function (): void {
    Bus::fake([PurgeAgentJob::class]);

    $agent = Agent::factory()->create(['domain' => 'help.example.test']);
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    app(DeleteAgentAction::class)->execute($agent);

    $trashed = Agent::withTrashed()->find($agent->id);

    expect(Agent::find($agent->id))->toBeNull()
        ->and($trashed->trashed())->toBeTrue()
        ->and($trashed->slug)->toBeNull()
        ->and($trashed->domain)->toBeNull()
        ->and($trashed->external_id)->toBe($agent->external_id)
        // The data waits for the purge.
        ->and(Session::find($session->id))->not->toBeNull();

    Bus::assertDispatched(PurgeAgentJob::class, fn (PurgeAgentJob $job): bool => $job->agentId === $agent->id && jobQueue($job) === 'exports');
});

it('frees the slug and the custom domain for another agent at once', function (): void {
    Bus::fake([PurgeAgentJob::class]);

    $agent = Agent::factory()->create(['domain' => 'help.example.test']);
    $slug = $agent->slug;

    app(DeleteAgentAction::class)->execute($agent);

    $other = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);
    $other->update(['slug' => $slug, 'domain' => 'help.example.test']);

    expect($other->fresh()->slug)->toBe($slug)
        ->and($other->fresh()->domain)->toBe('help.example.test');
});

it('switches off the agent messaging channels', function (): void {
    Bus::fake([PurgeAgentJob::class]);

    $agent = Agent::factory()->create();
    $bot = AgentTelegramBot::create([
        'agent_id' => $agent->id,
        'bot_token' => '123:token',
        'bot_username' => 'demo_bot',
        'webhook_secret' => Str::random(48),
        'enabled' => true,
    ]);

    app(DeleteAgentAction::class)->execute($agent);

    expect($bot->fresh()->enabled)->toBeFalse();
});

it('removes the agent with its data and storage once the purge has run', function (): void {
    Storage::fake();
    Storage::fake('public');

    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);
    $prefix = 'workspaces/'.$agent->workspaceExternalId().'/agents/';

    Session::factory()->create(['agent_id' => $agent->id]);
    $kept = Session::factory()->create(['agent_id' => $other->id]);
    Storage::put($prefix.$agent->external_id.'/notes.txt', 'x');
    Storage::disk('public')->put($prefix.$agent->external_id.'/attachments/a.png', 'x');
    Storage::put($prefix.$other->external_id.'/notes.txt', 'x');

    // The queue runs synchronously in tests, so the purge follows the delete.
    app(DeleteAgentAction::class)->execute($agent);

    expect(Agent::withTrashed()->find($agent->id))->toBeNull()
        ->and(Session::query()->where('agent_id', $agent->id)->exists())->toBeFalse()
        ->and(Session::find($kept->id))->not->toBeNull();

    Storage::assertMissing($prefix.$agent->external_id.'/notes.txt');
    Storage::disk('public')->assertMissing($prefix.$agent->external_id.'/attachments/a.png');
    Storage::assertExists($prefix.$other->external_id.'/notes.txt');
});

it('keeps the agent as it was and queues nothing when the delete fails', function (): void {
    Bus::fake([PurgeAgentJob::class]);

    $agent = Agent::factory()->create(['domain' => 'help.example.test']);
    $slug = $agent->slug;

    Agent::deleting(static fn (): bool => throw new RuntimeException('boom'));

    expect(fn () => app(DeleteAgentAction::class)->execute($agent))->toThrow(RuntimeException::class);

    $fresh = Agent::find($agent->id);

    expect($fresh)->not->toBeNull()
        ->and($fresh->slug)->toBe($slug)
        ->and($fresh->domain)->toBe('help.example.test');

    Bus::assertNotDispatched(PurgeAgentJob::class);
});
