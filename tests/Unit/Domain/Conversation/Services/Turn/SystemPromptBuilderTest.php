<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Turn\StreamRequestData;
use Domain\Conversation\Data\Turn\StreamRequestMessageData;
use Domain\Conversation\Services\Turn\SystemPromptBuilder;
use Domain\Guardrail\Services\GuardrailInspector;
use Domain\Llm\Models\Llm;
use Illuminate\Support\Str;

/**
 * The system prompt of a turn names a signed-in chatter only on a protected
 * agent, never on a public one, and carries the guardrails leak marker only
 * while the injection guard is on. The wording itself is not tested.
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

it('names the signed-in chatter to a protected agent only, never on a public one', function (): void {
    $protected = Agent::factory()->create(['is_public' => false]);
    $public = Agent::factory()->create(['is_public' => true]);

    expect(spbPrompt($protected, 'Ada Lovelace'))->toContain('Ada Lovelace')
        ->and(spbPrompt($public, 'Ada Lovelace'))->not->toContain('Ada Lovelace');
});

it('carries the guardrails leak marker only while the injection guard is on', function (): void {
    $guarded = Agent::factory()->create();
    $unguarded = Agent::factory()->create();
    $unguarded->setSetting('injection', false, 'guardrails');
    $marker = app(GuardrailInspector::class)->leakMarker($guarded);

    expect($marker)->toStartWith('CV-')
        ->and(spbPrompt($guarded, null))->toContain($marker)
        ->and(app(GuardrailInspector::class)->leakMarker($unguarded->fresh()))->toBeNull()
        ->and(spbPrompt($unguarded->fresh(), null))->not->toContain('CV-');
});
