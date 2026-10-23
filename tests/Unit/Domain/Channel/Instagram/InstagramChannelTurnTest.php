<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Actions\HandleChannelMessageAction;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Instagram\Models\InstagramThread;
use Domain\Channel\Instagram\Services\InstagramAdapter;
use Domain\Channel\Instagram\Services\InstagramThreadResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Illuminate\Support\Carbon;
use Tests\Support\Channel\Instagram\FakeInstagramClient;
use Tests\Support\Channel\StubChannelReplyRunner;

/**
 * An Instagram turn through {@see HandleChannelMessageAction} with the
 * {@see InstagramAdapter}: resolve the thread, show typing, run the agent for
 * a reply, send it back, and stamp the thread. The pipeline (real LLM) is
 * stubbed here — this asserts the wiring, not the model's answer.
 */
function instagramTurnAccount(): AgentInstagramAccount
{
    return AgentInstagramAccount::factory()->create(['page_access_token' => 'IGAA-secret-token-1234567890']);
}

function instagramTurnAdapter(FakeInstagramClient $client, AgentInstagramAccount $account, string $igsid): InstagramAdapter
{
    return new InstagramAdapter($client, new InstagramThreadResolver(app(StartConversationAction::class), $client), $account, $igsid);
}

it('sends the agent reply in the sender’s thread and stamps it when it replies', function (): void {
    $account = instagramTurnAccount();
    $client = new FakeInstagramClient;
    $runner = new StubChannelReplyRunner('Hello from the agent');
    $this->travelTo(Carbon::parse('2026-10-01 10:00:00'));
    new InstagramThreadResolver(app(StartConversationAction::class), $client)->resolve($account, '5550001234');
    $this->travelTo(Carbon::parse('2026-10-01 10:05:00'));

    $runner->handler()->execute(instagramTurnAdapter($client, $account, '5550001234'), 'hi there');

    expect($client->sent)->toHaveCount(1)
        ->and($client->sent[0]['token'])->toBe('IGAA-secret-token-1234567890')
        ->and($client->sent[0]['igsid'])->toBe('5550001234')
        ->and($client->sent[0]['markdown'])->toBe('Hello from the agent');

    // A "typing…" indicator fires the moment the message lands.
    expect($client->typing)->not->toBeEmpty()
        ->and($client->typing[0]['igsid'])->toBe('5550001234');

    expect($runner->received())->toBe('hi there')
        ->and($runner->turn->source->value)->toBe('instagram');

    $thread = InstagramThread::query()->firstOrFail();
    expect($thread->igsid)->toBe('5550001234')
        ->and($thread->last_message_at->toDateTimeString())->toBe('2026-10-01 10:05:00');
});
