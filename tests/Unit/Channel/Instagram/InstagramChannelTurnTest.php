<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Actions\HandleChannelMessageAction;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Instagram\Models\InstagramThread;
use Domain\Channel\Instagram\Services\InstagramAdapter;
use Domain\Channel\Instagram\Services\InstagramThreadResolver;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Tests\Support\Channel\StubChannelReplyRunner;
use Tests\Support\Instagram\FakeInstagramClient;

/**
 * An Instagram turn through {@see HandleChannelMessageAction} with the
 * {@see InstagramAdapter}: resolve the thread, show typing, run the agent for
 * a reply, send it back, and stamp the thread. The pipeline (real LLM) is
 * stubbed here — this asserts the wiring, not the model's answer.
 */
function instagramTurnAccount(): AgentInstagramAccount
{
    $agent = Agent::factory()->create(['is_public' => true]);

    return AgentInstagramAccount::create([
        'agent_id' => $agent->id,
        'page_access_token' => 'IGAA-secret-token-1234567890',
        'page_id' => '998877',
        'ig_id' => '17840012345',
        'username' => 'acme.support',
        'enabled' => true,
    ]);
}

function instagramTurnAdapter(FakeInstagramClient $client, AgentInstagramAccount $account, string $igsid): InstagramAdapter
{
    return new InstagramAdapter($client, new InstagramThreadResolver(app(StartConversationAction::class), $client), $account, $igsid);
}

it('resolves the thread, sends the agent reply, and stamps last_message_at', function (): void {
    $account = instagramTurnAccount();
    $client = new FakeInstagramClient;
    $runner = new StubChannelReplyRunner('Hello from the agent');

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
        ->and($thread->last_message_at)->not->toBeNull();
});
