<?php

use Domain\Channel\Slack\Actions\ReceiveSlackEventAction;
use Domain\Channel\Slack\Data\SlackEventData;
use Domain\Channel\Slack\Jobs\HandleSlackEventJob;
use Domain\Channel\Slack\Models\AgentSlackApp;
use Illuminate\Support\Facades\Bus;

/**
 * SlackWebhookTest covers which events are answered and a repeated delivery
 * through the webhook. This covers what one app cannot show: the memory of
 * queued events is kept per app, so two apps that report the same event id
 * are each answered, and each only once.
 */
it('remembers queued events per Slack app', function (): void {
    Bus::fake();
    $first = AgentSlackApp::factory()->create();
    $second = AgentSlackApp::factory()->create();
    $mention = new SlackEventData('Ev1', 'app_mention', 'C1', '<@U0BOT> hi', null, 'U1');
    $action = app(ReceiveSlackEventAction::class);

    $action->execute($first, $mention);
    $action->execute($second, $mention);
    $action->execute($first, $mention);
    $action->execute($second, $mention);

    Bus::assertDispatchedTimes(HandleSlackEventJob::class, 2);
    Bus::assertDispatched(HandleSlackEventJob::class, fn (HandleSlackEventJob $job): bool => $job->app->is($first) && $job->channelId === 'C1' && $job->text === '<@U0BOT> hi');
    Bus::assertDispatched(HandleSlackEventJob::class, fn (HandleSlackEventJob $job): bool => $job->app->is($second));
});
