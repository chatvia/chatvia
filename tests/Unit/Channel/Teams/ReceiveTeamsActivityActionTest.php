<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Teams\Actions\ReceiveTeamsActivityAction;
use Domain\Channel\Teams\Jobs\HandleTeamsActivityJob;
use Domain\Channel\Teams\Models\AgentTeamsBot;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

/**
 * TeamsWebhookTest covers which activities are answered and a repeated
 * delivery through the webhook. This covers what one bot cannot show: the
 * memory of queued activities is kept per bot, so two bots that report the
 * same activity id are each answered, and each only once.
 */
it('remembers queued activities per Teams bot', function (): void {
    Bus::fake();
    $bot = fn (): AgentTeamsBot => AgentTeamsBot::create([
        'agent_id' => Agent::factory()->create()->id,
        'app_id' => (string) Str::uuid(),
        'app_secret' => 'client-secret-value',
        'webhook_secret' => Str::random(48),
        'enabled' => true,
    ]);
    $first = $bot();
    $second = $bot();
    $activity = [
        'type' => 'message', 'id' => 'act-1', 'text' => 'hi', 'serviceUrl' => 'https://smba.example/',
        'conversation' => ['id' => 'conv-1'], 'from' => ['id' => 'user'], 'recipient' => ['id' => 'bot'],
    ];
    $action = app(ReceiveTeamsActivityAction::class);

    foreach ([$first, $second, $first, $second] as $receiver) {
        $action->execute($receiver, $activity);
    }

    Bus::assertDispatchedTimes(HandleTeamsActivityJob::class, 2);
    Bus::assertDispatched(HandleTeamsActivityJob::class, fn (HandleTeamsActivityJob $job): bool => $job->bot->is($first) && $job->activity === $activity);
    Bus::assertDispatched(HandleTeamsActivityJob::class, fn (HandleTeamsActivityJob $job): bool => $job->bot->is($second));
});
