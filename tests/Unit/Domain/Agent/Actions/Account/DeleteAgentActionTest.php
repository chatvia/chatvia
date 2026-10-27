<?php

use Domain\Agent\Actions\Account\DeleteAgentAction;
use Domain\Agent\Jobs\PurgeAgentJob;
use Domain\Agent\Models\Agent;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Domain\Channel\Telegram\Models\AgentTelegramBot;
use Domain\Channel\Whatsapp\Models\AgentWhatsappNumber;
use Domain\Conversation\Models\Session;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Bus;

/**
 * Deleting an agent hides it at once (soft delete), frees its slug, silences
 * its messaging channels and queues the purge of its data.
 */
it('hides the agent at once and queues its purge', function (): void {
    Bus::fake([PurgeAgentJob::class]);

    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    app(DeleteAgentAction::class)->execute($agent);

    $trashed = Agent::withTrashed()->find($agent->id);

    expect(Agent::find($agent->id))->toBeNull()
        ->and($trashed->trashed())->toBeTrue()
        ->and($trashed->slug)->toBeNull()
        ->and($trashed->external_id)->toBe($agent->external_id)
        // The data waits for the purge.
        ->and(Session::find($session->id))->not->toBeNull();

    Bus::assertDispatched(PurgeAgentJob::class, fn (PurgeAgentJob $job): bool => $job->agentId === $agent->id);
});

it('frees the slug for another agent at once', function (): void {
    Bus::fake([PurgeAgentJob::class]);

    $agent = Agent::factory()->create();
    $slug = $agent->slug;

    app(DeleteAgentAction::class)->execute($agent);

    $other = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);
    $other->update(['slug' => $slug]);

    expect($other->fresh()->slug)->toBe($slug);
});

it('switches off each of the agent messaging channels', function (string $model): void {
    Bus::fake([PurgeAgentJob::class]);

    $agent = Agent::factory()->create();
    /** @var class-string<Model> $model */
    $channel = $model::factory()->forAgent($agent)->create();

    app(DeleteAgentAction::class)->execute($agent);

    expect($channel->fresh()->is_enabled)->toBeFalse();
})->with([
    'telegram' => AgentTelegramBot::class,
    'slack' => AgentSlackApp::class,
    'messenger' => AgentMessengerPage::class,
    'whatsapp' => AgentWhatsappNumber::class,
    'teams' => AgentTeamsBot::class,
    'instagram' => AgentInstagramAccount::class,
]);

it('purges the agent once its queued purge runs', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);
    Session::factory()->create(['agent_id' => $agent->id]);
    $kept = Session::factory()->create(['agent_id' => $other->id]);

    // The queue runs synchronously in tests, so the purge follows the delete.
    app(DeleteAgentAction::class)->execute($agent);

    expect(Agent::withTrashed()->find($agent->id))->toBeNull()
        ->and(Session::query()->where('agent_id', $agent->id)->exists())->toBeFalse()
        ->and(Session::find($kept->id))->not->toBeNull();
});

it('keeps the agent as it was and queues nothing when the delete fails', function (): void {
    Bus::fake([PurgeAgentJob::class]);

    $agent = Agent::factory()->create();
    $slug = $agent->slug;

    Agent::deleting(static fn (): bool => throw new RuntimeException('boom'));

    expect(fn () => app(DeleteAgentAction::class)->execute($agent))->toThrow(RuntimeException::class);

    $fresh = Agent::find($agent->id);

    expect($fresh)->not->toBeNull()
        ->and($fresh->slug)->toBe($slug);

    Bus::assertNotDispatched(PurgeAgentJob::class);
});
