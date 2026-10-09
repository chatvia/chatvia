<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Services\ChatContext;
use Domain\Conversation\Services\Turn\SystemPromptBuilder;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Str;

/**
 * The system prompt names a signed-in chatter only on protected agents.
 */
function systemPromptFor(Agent $agent, ?string $chatterName, ?string $source = null): string
{
    $data = new StreamRequestData(
        agent: $agent,
        message: new StreamRequestMessageData(model: Llm::factory()->create(), message: 'Hi'),
        externalSessionId: (string) Str::uuid(),
        conversationId: (string) Str::uuid(),
        chatterName: $chatterName,
        source: $source,
    );

    return app(SystemPromptBuilder::class)->systemPrompt($data);
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

// Forms are NOT listed in the system prompt — the agent discovers them at runtime
// through the Forms MCP server (find-forms → render-form), and the web-only channel
// gate lives in those tools. That behaviour is covered by FindFormsToolTest, so the
// former system-prompt forms-block assertions were removed when the listing moved.

it('puts the system prompt first and replaces a trailing visitor message with the enriched one', function (): void {
    $agent = Agent::factory()->create();
    $request = new StreamRequestData(
        agent: $agent,
        message: new StreamRequestMessageData(model: Llm::factory()->create(), message: 'Hi'),
        externalSessionId: (string) Str::uuid(),
        conversationId: (string) Str::uuid(),
    );
    $context = new ChatContext(
        requestData: $request,
        payload: new UserTurnData(
            external_session_id: $request->externalSessionId,
            external_conversation_id: $request->conversationId,
            external_message_id: (string) Str::uuid(),
            model: 'm',
            message: 'Hi',
            sent_at: now(),
            message_for_llm: 'Hi [with reference material]',
        ),
        llmMessages: [
            ['role' => 'user', 'content' => 'Earlier question'],
            ['role' => 'assistant', 'content' => 'Earlier answer'],
            ['role' => 'user', 'content' => 'Hi'],
        ],
        userMessageId: null,
        assistantMessageId: (string) Str::uuid(),
    );

    $messages = app(SystemPromptBuilder::class)->messagesFor($context);

    expect(array_column($messages, 'role'))->toBe(['system', 'user', 'assistant', 'user'])
        ->and($messages[3]['content'])->toBe('Hi [with reference material]');
});
