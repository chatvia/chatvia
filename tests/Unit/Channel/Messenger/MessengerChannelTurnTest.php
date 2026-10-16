<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Actions\HandleChannelMessageAction;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Models\MessengerThread;
use Domain\Channel\Messenger\Services\MessengerAdapter;
use Domain\Channel\Messenger\Services\MessengerThreadResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Tests\Support\Channel\StubChannelReplyRunner;
use Tests\Support\Messenger\FakeMessengerClient;

/**
 * A Messenger turn through {@see HandleChannelMessageAction} with the
 * {@see MessengerAdapter}: resolve the thread, show typing, run the agent for
 * a reply, send it back, and stamp the thread. The pipeline (real LLM) is
 * stubbed here — this asserts the wiring, not the model's answer.
 */
function messengerTurnPage(): AgentMessengerPage
{
    $agent = Agent::factory()->create(['is_public' => true]);

    return AgentMessengerPage::create([
        'agent_id' => $agent->id,
        'page_access_token' => 'EAAG-secret-token',
        'page_id' => '1234567890',
        'page_name' => 'Acme Support',
        'is_enabled' => true,
    ]);
}

function messengerTurnAdapter(FakeMessengerClient $client, AgentMessengerPage $page, string $psid): MessengerAdapter
{
    return new MessengerAdapter($client, new MessengerThreadResolver(app(StartConversationAction::class), $client), $page, $psid);
}

it('resolves the thread, sends the agent reply, and stamps last_message_at', function (): void {
    $page = messengerTurnPage();
    $client = new FakeMessengerClient;
    $runner = new StubChannelReplyRunner('Hello from the agent');

    $runner->handler()->execute(messengerTurnAdapter($client, $page, '24680'), 'hi there');

    expect($client->sent)->toHaveCount(1)
        ->and($client->sent[0]['token'])->toBe('EAAG-secret-token')
        ->and($client->sent[0]['psid'])->toBe('24680')
        ->and($client->sent[0]['markdown'])->toBe('Hello from the agent');

    // A "typing…" indicator fires the moment the message lands.
    expect($client->typing)->not->toBeEmpty()
        ->and($client->typing[0]['psid'])->toBe('24680');

    // The pipeline runs the user's text on the Messenger surface.
    expect($runner->received())->toBe('hi there')
        ->and($runner->turn->source->value)->toBe('messenger')
        ->and($runner->turn->requestIdPrefix)->toBe('msgr_');

    $thread = MessengerThread::query()->firstOrFail();
    expect($thread->psid)->toBe('24680')
        ->and($thread->last_message_at)->not->toBeNull();
});
