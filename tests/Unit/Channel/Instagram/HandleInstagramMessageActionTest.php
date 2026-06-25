<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Instagram\Actions\HandleInstagramMessageAction;
use Domain\Channel\Instagram\Models\AgentInstagramAccount;
use Domain\Channel\Instagram\Models\InstagramThread;
use Domain\Channel\Instagram\Services\InstagramReplyRunner;
use Domain\Channel\Instagram\Services\InstagramThreadResolver;
use Domain\Channel\Services\ChannelAccessGate;
use Domain\Conversation\Services\Memory\ChatMemory;
use Tests\Support\Instagram\FakeInstagramClient;

/**
 * The handle Action wires the inbound message end to end: resolve the thread,
 * run the agent for a reply, send it back, and stamp the thread. The pipeline
 * (real LLM) is stubbed here — this asserts the wiring, not the model's answer.
 */
function instagramHandleAccount(bool $public = true): AgentInstagramAccount
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

function stubInstagramReplyRunner(string $reply): InstagramReplyRunner
{
    return new class($reply) extends InstagramReplyRunner
    {
        public ?string $received = null;

        public function __construct(private string $canned) {}

        public function reply(InstagramThread $thread, string $message): string
        {
            $this->received = $message;

            return $this->canned;
        }
    };
}

function instagramHandleAction(FakeInstagramClient $client, InstagramReplyRunner $runner): HandleInstagramMessageAction
{
    return new HandleInstagramMessageAction(
        new InstagramThreadResolver(app(ChatMemory::class), $client),
        $runner,
        $client,
        new ChannelAccessGate,
    );
}

it('resolves the thread, sends the agent reply, and stamps last_message_at', function (): void {
    $account = instagramHandleAccount();
    $client = new FakeInstagramClient;

    instagramHandleAction($client, stubInstagramReplyRunner('Hello from the agent'))->execute($account, '5550001234', 'hi there');

    expect($client->sent)->toHaveCount(1)
        ->and($client->sent[0]['token'])->toBe('IGAA-secret-token-1234567890')
        ->and($client->sent[0]['igsid'])->toBe('5550001234')
        ->and($client->sent[0]['markdown'])->toBe('Hello from the agent');

    // A "typing…" indicator fires the moment the message lands.
    expect($client->typing)->not->toBeEmpty()
        ->and($client->typing[0]['igsid'])->toBe('5550001234');

    $thread = InstagramThread::query()->firstOrFail();
    expect($thread->igsid)->toBe('5550001234')
        ->and($thread->last_message_at)->not->toBeNull();
});

it('keeps one thread mapping across multiple inbound messages', function (): void {
    $account = instagramHandleAccount();
    $client = new FakeInstagramClient;
    $action = instagramHandleAction($client, stubInstagramReplyRunner('ok'));

    $action->execute($account, '900', 'first');
    $action->execute($account, '900', 'second');

    expect(InstagramThread::query()->count())->toBe(1);
    expect($client->sent)->toHaveCount(2);
});

it('refuses to answer on a protected agent and creates no thread', function (): void {
    $account = instagramHandleAccount(public: false);
    $client = new FakeInstagramClient;
    $runner = stubInstagramReplyRunner('should never be sent');

    instagramHandleAction($client, $runner)->execute($account, '5550001234', 'hello');

    // Gated before the pipeline: a single "unavailable" notice, no thread row.
    expect($client->sent)->toHaveCount(1)
        ->and($client->sent[0]['markdown'])->toContain('available')
        ->and($client->sent[0]['markdown'])->not->toBe('should never be sent');

    expect(InstagramThread::query()->count())->toBe(0);
});
