<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\ChatService;
use Domain\Conversation\Services\Data\StreamRequestData;
use Domain\Conversation\Services\Data\StreamRequestMessageData;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Str;

/**
 * buildSystemPrompt is private — it's the only producer of the system message,
 * so reach it via reflection to cover the chatter-identity gating directly.
 */
function systemPromptFor(Agent $agent, ?string $chatterName): string
{
    $data = new StreamRequestData(
        agent: $agent,
        message: new StreamRequestMessageData(model: Llm::factory()->create(), message: 'Hi'),
        externalSessionId: (string) Str::uuid(),
        conversationId: (string) Str::uuid(),
        chatterName: $chatterName,
    );

    $service = app(ChatService::class);
    $method = (new ReflectionClass(ChatService::class))->getMethod('buildSystemPrompt');
    $method->setAccessible(true);

    return $method->invoke($service, $data)['content'];
}

it('tells a protected agent the signed-in chatter name', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);

    expect(systemPromptFor($agent, 'Ada Lovelace'))->toContain('Ada Lovelace');
});

it('does not name the visitor on a public agent', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);

    expect(systemPromptFor($agent, 'Ada Lovelace'))->not->toContain('Ada Lovelace');
});

it('omits the identity block when no chatter is signed in', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);

    expect(systemPromptFor($agent, null))->not->toContain('signed-in user');
});
