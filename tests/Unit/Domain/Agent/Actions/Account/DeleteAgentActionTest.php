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
use Illuminate\Support\Facades\Bus;

/**
 * Deleting an agent hides it at once (soft delete), frees its slug, silences
 * its messaging channels and queues the purge of its data; a failed delete
 * changes nothing and queues nothing.
 */
it('hides the agent, frees its slug, silences its channels and queues its purge', function (): void {
    Bus::fake([PurgeAgentJob::class]);
    $agent = Agent::factory()->create();
    $session = Session::factory()->create(['agent_id' => $agent->id]);
    $channels = collect([
        AgentTelegramBot::class,
        AgentSlackApp::class,
        AgentMessengerPage::class,
        AgentWhatsappNumber::class,
        AgentTeamsBot::class,
        AgentInstagramAccount::class,
    ])->map(fn (string $model): mixed => $model::factory()->forAgent($agent)->create());

    app(DeleteAgentAction::class)->execute($agent);

    $trashed = Agent::withTrashed()->find($agent->id);

    expect(Agent::find($agent->id))->toBeNull()
        ->and($trashed->trashed())->toBeTrue()
        ->and($trashed->slug)->toBeNull()
        ->and(Session::find($session->id))->not->toBeNull()
        ->and($channels->map(fn (mixed $channel): bool => $channel->fresh()->is_enabled)->unique()->all())->toBe([false]);
    Bus::assertDispatched(PurgeAgentJob::class, fn (PurgeAgentJob $job): bool => $job->agentId === $agent->id);
});

it('purges the agent once its queued purge runs, and leaves another agent alone', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);
    Session::factory()->create(['agent_id' => $agent->id]);
    $kept = Session::factory()->create(['agent_id' => $other->id]);

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

    expect(Agent::find($agent->id))->not->toBeNull()
        ->slug->toBe($slug);
    Bus::assertNotDispatched(PurgeAgentJob::class);
});
