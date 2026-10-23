<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Contracts\ChannelAdapter;
use Domain\Channel\Contracts\ChannelConversationMapping;
use Domain\Channel\Services\ChannelAccessGate;
use Domain\Conversation\Enums\ConversationSource;

/**
 * The gate every channel turn passes first: a deactivated workspace's agents
 * and protected agents answer nowhere, and the sender gets a short notice in
 * the agent's channel language instead. The channel here only records what is
 * sent, and fails if the gate ever tries to store the sender's chat.
 */
beforeEach(function (): void {
    $this->channel = fn (Agent $agent): ChannelAdapter => new class($agent) implements ChannelAdapter
    {
        /** @var list<string> */
        public array $sent = [];

        public function __construct(private readonly Agent $agent) {}

        public function agent(): Agent
        {
            return $this->agent;
        }

        public function source(): ConversationSource
        {
            return ConversationSource::Telegram;
        }

        public function displayName(): string
        {
            return 'Carrier Pigeon';
        }

        public function resolveConversation(): ChannelConversationMapping
        {
            throw new LogicException('The gate never stores the sender’s chat.');
        }

        public function sendMessage(string $markdown): void
        {
            $this->sent[] = $markdown;
        }

        public function requestIdPrefix(): string
        {
            return 'test_';
        }

        public function logContext(): array
        {
            return [];
        }
    };
});

it('lets a public agent of an active workspace answer, with no notice', function (): void {
    $channel = ($this->channel)(Agent::factory()->create(['is_public' => true]));

    expect(app(ChannelAccessGate::class)->allows($channel))->toBeTrue()
        ->and($channel->sent)->toBe([]);
});

it('stops every agent of a deactivated workspace with the unavailable notice', function (bool $public): void {
    $agent = Agent::factory()->create(['is_public' => $public]);
    deactivateWorkspaceOf($agent);
    $channel = ($this->channel)($agent->fresh());

    expect(app(ChannelAccessGate::class)->allows($channel))->toBeFalse()
        ->and($channel->sent)->toBe(['Sorry — this assistant is unavailable right now. Please try again later.']);
})->with(['public agent' => true, 'protected agent' => false]);

it('stops a protected agent with a notice that names the channel', function (): void {
    $channel = ($this->channel)(Agent::factory()->create(['is_public' => false]));

    expect(app(ChannelAccessGate::class)->allows($channel))->toBeFalse()
        ->and($channel->sent)->toBe(['This assistant isn’t available on Carrier Pigeon right now.']);
});

it('sends the notices in the language the owner pinned for the agent', function (): void {
    $protected = Agent::factory()->create(['is_public' => false]);
    $protected->setSetting(key: 'language', value: 'de', group: 'embed');
    $deactivated = Agent::factory()->create(['is_public' => true]);
    $deactivated->setSetting(key: 'language', value: 'de', group: 'widget');
    deactivateWorkspaceOf($deactivated);

    $protectedChannel = ($this->channel)($protected->fresh());
    $deactivatedChannel = ($this->channel)($deactivated->fresh());
    app(ChannelAccessGate::class)->allows($protectedChannel);
    app(ChannelAccessGate::class)->allows($deactivatedChannel);

    expect($protectedChannel->sent)->toBe(['Dieser Assistent ist auf Carrier Pigeon gerade nicht verfügbar.'])
        ->and($deactivatedChannel->sent)->toBe(['Entschuldigung – dieser Assistent ist gerade nicht verfügbar. Bitte versuchen Sie es später erneut.']);
});
