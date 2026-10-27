<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Services\Turn\SystemPromptBuilder;
use Domain\Guardrail\Services\GuardrailInspector;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Str;
use Tests\Support\Conversation\TestChatContext;

/**
 * The system prompt of a turn: it opens with the guardrails leak marker (while
 * the injection guard is on), names a signed-in chatter only on a protected
 * agent, and tells the model which language to answer in. It goes first in
 * the messages, with the visitor's last message replaced by the enriched one.
 */
function spbPrompt(Agent $agent, ?string $chatterName, ?string $source = null, ?string $locale = null, ?string $language = null): string
{
    $data = new StreamRequestData(
        agent: $agent,
        message: new StreamRequestMessageData(model: Llm::factory()->create(), message: 'Hi'),
        externalSessionId: (string) Str::uuid7(),
        conversationId: (string) Str::uuid7(),
        chatterName: $chatterName,
        source: $source,
        locale: $locale,
    );

    return app(SystemPromptBuilder::class)->systemPrompt($data, $language);
}

it('tells a protected agent the signed-in chatter name', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);

    expect(spbPrompt($agent, 'Ada Lovelace'))->toContain('Ada Lovelace');
});

it('does not name the visitor on a public agent', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);

    expect(spbPrompt($agent, 'Ada Lovelace'))->not->toContain('Ada Lovelace');
});

it('omits the identity block when no chatter is signed in', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);

    expect(spbPrompt($agent, null))->not->toContain('signed-in user');
});

it('puts the system prompt first and replaces a trailing visitor message with the enriched one', function (): void {
    $context = TestChatContext::make(
        message: 'Hi',
        llmMessages: [
            ['role' => 'user', 'content' => 'Earlier question'],
            ['role' => 'assistant', 'content' => 'Earlier answer'],
            ['role' => 'user', 'content' => 'Hi'],
        ],
        messageForLlm: 'Hi [with reference material]',
    );

    $messages = app(SystemPromptBuilder::class)->messagesFor($context);

    expect(array_column($messages, 'role'))->toBe(['system', 'user', 'assistant', 'user'])
        ->and($messages[3]['content'])->toBe('Hi [with reference material]');
});

it('tells the model to answer in the language of the visitor message, not the sources language', function (): void {
    $context = TestChatContext::make(
        message: 'Who is Dania?',
        llmMessages: [],
        messageForLlm: 'Who is Dania? [with reference material]',
        queryLanguage: 'en',
    );

    $messages = app(SystemPromptBuilder::class)->messagesFor($context);

    expect($messages[0]['content'])->toContain('Write your whole answer in English');
});

it('keeps the generic language rule when no language was detected', function (): void {
    $agent = Agent::factory()->create();

    expect(spbPrompt($agent, null))
        ->toContain("Write your whole answer in the language of the user's message");
});

it('names the browser language for a message too short to show its own when none was detected', function (): void {
    $agent = Agent::factory()->create();

    expect(spbPrompt($agent, null, locale: 'da-DK,da;q=0.9'))
        ->toContain('The visitor\'s browser is set to Danish')
        ->toContain("answer in\n  Danish.");
});

it('leaves the browser language out when the message language was detected or the browser sent none', function (?string $locale, ?string $language): void {
    $agent = Agent::factory()->create();

    expect(spbPrompt($agent, null, locale: $locale, language: $language))->not->toContain('browser is set to');
})->with([
    'detected' => ['da-DK', 'en'],
    'no header' => [null, null],
    'wildcard' => ['*', null],
    'unknown code' => ['zz', null],
]);

it('asks an agent that cites for block numbers, only where chips can show', function (): void {
    $agent = Agent::factory()->create();

    expect(spbPrompt($agent, null))->toContain('never mention the blocks')
        ->not->toContain("add the block's number");

    $agent->setSetting('sources_display', 'citations', 'appearance');
    $citing = $agent->fresh();

    expect(spbPrompt($citing, null))->toContain("add the block's number")
        ->not->toContain('never mention the blocks')
        ->and(spbPrompt($citing, null, 'telegram'))->toContain('never mention the blocks');
});

it('opens the system prompt with the guardrails leak marker', function (): void {
    $agent = Agent::factory()->create();
    $marker = app(GuardrailInspector::class)->leakMarker($agent);

    expect($marker)->toStartWith('CV-')
        ->and(spbPrompt($agent, null))->toStartWith("Internal reference: {$marker}.");
});

it('leaves the leak marker out when the injection guard is off', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting('injection', false, 'guardrails');

    expect(spbPrompt($agent->fresh(), null))->not->toContain('Internal reference');
});

it('gives an agent that does not search its knowledge the tools-only rules', function (): void {
    $agent = Agent::factory()->create();
    $agent->setSetting('search', false, 'knowledge');

    $prompt = spbPrompt($agent->fresh(), null, language: 'de');

    expect($prompt)->not->toContain('<source>')
        ->and($prompt)->toContain('come only from your tools and')
        ->and($prompt)->toContain('Write your whole answer in German');
});

it('leaves the knowledge search out of a voice call of an agent that does not search', function (): void {
    $agent = Agent::factory()->create();
    $builder = app(SystemPromptBuilder::class);

    expect($builder->voicePrompt($agent, null, []))->toContain('knowledge search tool');

    $agent->setSetting('search', false, 'knowledge');

    expect($builder->voicePrompt($agent->fresh(), null, []))->not->toContain('knowledge search tool')
        ->and($builder->voicePrompt($agent->fresh(), null, []))->toContain('what your tools return');
});
