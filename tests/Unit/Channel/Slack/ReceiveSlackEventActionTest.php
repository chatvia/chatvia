<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Slack\Actions\ReceiveSlackEventAction;
use Domain\Channel\Slack\Data\SlackEventData;
use Domain\Channel\Slack\Jobs\HandleSlackEventJob;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;

/**
 * SlackWebhookTest covers which events are answered and a repeated delivery
 * through the webhook. This covers what one app cannot show: the memory of
 * queued events is kept per app, so two apps that report the same event id
 * are each answered, and each only once.
 */
it('remembers queued events per Slack app', function (): void {
    Bus::fake();
    $app = fn (): AgentSlackApp => AgentSlackApp::create([
        'agent_id' => Agent::factory()->create()->id,
        'bot_token' => 'xoxb-token',
        'signing_secret' => 'signing-secret-value-12345678',
        'bot_user_id' => 'U0BOT',
        'team_name' => 'Acme',
        'webhook_secret' => Str::random(48),
        'enabled' => true,
    ]);
    $first = $app();
    $second = $app();
    $mention = new SlackEventData('Ev1', 'app_mention', 'C1', '<@U0BOT> hi', null, 'U1');
    $action = app(ReceiveSlackEventAction::class);

    foreach ([$first, $second, $first, $second] as $receiver) {
        $action->execute($receiver, $mention);
    }

    Bus::assertDispatchedTimes(HandleSlackEventJob::class, 2);
    Bus::assertDispatched(HandleSlackEventJob::class, fn (HandleSlackEventJob $job): bool => $job->app->is($first) && $job->channelId === 'C1' && $job->text === '<@U0BOT> hi');
    Bus::assertDispatched(HandleSlackEventJob::class, fn (HandleSlackEventJob $job): bool => $job->app->is($second));
});
