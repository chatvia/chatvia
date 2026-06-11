<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Messenger\Actions\HandleMessengerMessageAction;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Messenger\Models\MessengerThread;
use Domain\Channel\Messenger\Services\MessengerReplyRunner;
use Domain\Channel\Messenger\Services\MessengerThreadResolver;
use Domain\Channel\Services\ChannelAccessGate;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Str;
use Tests\Support\Messenger\FakeMessengerClient;

/**
 * The handle Action wires the inbound message end to end: resolve the thread,
 * run the agent for a reply, send it back, and stamp the thread. The pipeline
 * (real LLM) is stubbed here — this asserts the wiring, not the model's answer.
 */
function messengerHandlePage(bool $public = true): AgentMessengerPage
{
    $agent = Agent::factory()->create(['is_public' => $public]);

    return AgentMessengerPage::create([
        'agent_id' => $agent->id,
        'page_access_token' => 'EAAG-secret-token',
        'app_secret' => 'app-secret-value-1234567890',
        'page_id' => '1234567890',
        'page_name' => 'Acme Support',
        'webhook_secret' => Str::random(48),
        'enabled' => true,
    ]);
}

function stubMessengerReplyRunner(string $reply): MessengerReplyRunner
{
    return new class($reply) extends MessengerReplyRunner
    {
        public ?string $received = null;

        public function __construct(private string $canned) {}

        public function reply(MessengerThread $thread, string $message): string
        {
            $this->received = $message;

            return $this->canned;
        }
    };
}

function messengerHandleAction(FakeMessengerClient $client, MessengerReplyRunner $runner): HandleMessengerMessageAction
{
    return new HandleMessengerMessageAction(
        new MessengerThreadResolver(app(ChatMemory::class), $client),
        $runner,
        $client,
        new ChannelAccessGate,
    );
}

it('resolves the thread, sends the agent reply, and stamps last_message_at', function (): void {
    $page = messengerHandlePage();
    $client = new FakeMessengerClient;

    messengerHandleAction($client, stubMessengerReplyRunner('Hello from the agent'))->execute($page, '24680', 'hi there');

    expect($client->sent)->toHaveCount(1)
        ->and($client->sent[0]['token'])->toBe('EAAG-secret-token')
        ->and($client->sent[0]['psid'])->toBe('24680')
        ->and($client->sent[0]['markdown'])->toBe('Hello from the agent');

    // A "typing…" indicator fires the moment the message lands.
    expect($client->typing)->not->toBeEmpty()
        ->and($client->typing[0]['psid'])->toBe('24680');

    $thread = MessengerThread::query()->firstOrFail();
    expect($thread->psid)->toBe('24680')
        ->and($thread->last_message_at)->not->toBeNull();
});

it('keeps one thread mapping across multiple inbound messages', function (): void {
    $page = messengerHandlePage();
    $client = new FakeMessengerClient;
    $action = messengerHandleAction($client, stubMessengerReplyRunner('ok'));

    $action->execute($page, '900', 'first');
    $action->execute($page, '900', 'second');

    expect(MessengerThread::query()->count())->toBe(1);
    expect($client->sent)->toHaveCount(2);
});

it('refuses to answer on a protected agent and creates no thread', function (): void {
    $page = messengerHandlePage(public: false);
    $client = new FakeMessengerClient;
    $runner = stubMessengerReplyRunner('should never be sent');

    messengerHandleAction($client, $runner)->execute($page, '24680', 'hello');

    // Gated before the pipeline: a single "unavailable" notice, no thread row.
    expect($client->sent)->toHaveCount(1)
        ->and($client->sent[0]['markdown'])->toContain('available')
        ->and($client->sent[0]['markdown'])->not->toBe('should never be sent');

    expect(MessengerThread::query()->count())->toBe(0);
});
