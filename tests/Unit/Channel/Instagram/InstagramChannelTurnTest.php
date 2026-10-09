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
function instagramTurnAccount(bool $public = true): AgentInstagramAccount
{
    $agent = Agent::factory()->create(['is_public' => $public]);

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

it('keeps one thread mapping across multiple inbound messages', function (): void {
    $account = instagramTurnAccount();
    $client = new FakeInstagramClient;
    $action = new StubChannelReplyRunner('ok')->handler();

    $action->execute(instagramTurnAdapter($client, $account, '900'), 'first');
    $action->execute(instagramTurnAdapter($client, $account, '900'), 'second');

    expect(InstagramThread::query()->count())->toBe(1);
    expect($client->sent)->toHaveCount(2);
});

it('refuses to answer on a protected agent and creates no thread', function (): void {
    $account = instagramTurnAccount(public: false);
    $client = new FakeInstagramClient;
    $runner = new StubChannelReplyRunner('should never be sent');

    $runner->handler()->execute(instagramTurnAdapter($client, $account, '5550001234'), 'hello');

    // Gated before the pipeline: a single "unavailable" notice, no thread row.
    expect($client->sent)->toHaveCount(1)
        ->and($client->sent[0]['markdown'])->toContain('available on Instagram')
        ->and($client->sent[0]['markdown'])->not->toBe('should never be sent');

    expect($runner->received())->toBeNull();
    expect(InstagramThread::query()->count())->toBe(0);
});
