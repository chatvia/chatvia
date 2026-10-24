<?php

declare(strict_types=1);

use Domain\Llm\Enums\ReasoningEffort;
use Domain\Llm\Models\Llm;

/**
 * A model's facts (from the models.dev catalog) decide what the app sends it:
 * the effort value for the effort chosen, whether sampling settings go, and
 * how long a reply it may be asked for. The effort lists below are the
 * catalog's own. The catalog of usable models is cached, and a provider
 * change drops the cache.
 */
function llmWithEfforts(?array $efforts, ?bool $takesTemperature = null): Llm
{
    // Not listed on models.dev at all when nothing is known.
    $known = $efforts !== null || $takesTemperature !== null;

    return new Llm(['name' => 'model', 'capabilities' => $known ? modelsDevEntry($efforts, $takesTemperature) : null]);
}

it('sends the value the model takes for each effort', function (?array $efforts, ReasoningEffort $effort, ?string $value): void {
    expect(llmWithEfforts($efforts)->effortValue($effort))->toBe($value);
})->with([
    'luna off: no thinking' => [['none', 'low', 'medium', 'high', 'xhigh', 'max'], ReasoningEffort::Off, 'none'],
    'luna medium' => [['none', 'low', 'medium', 'high', 'xhigh', 'max'], ReasoningEffort::Medium, 'medium'],
    'sol off: its least' => [['low', 'medium', 'high', 'xhigh', 'max'], ReasoningEffort::Off, 'low'],
    'o3 high' => [['low', 'medium', 'high'], ReasoningEffort::High, 'high'],
    'none or high, low asked: the one that thinks' => [['none', 'high'], ReasoningEffort::Low, 'high'],
    'none or high, off' => [['none', 'high'], ReasoningEffort::Off, 'none'],
    'a tie goes to more thinking' => [['low', 'high', 'max'], ReasoningEffort::Medium, 'high'],
    'unknown words are ignored' => [['none', 'default'], ReasoningEffort::Medium, 'none'],
    'not known, off: nothing' => [null, ReasoningEffort::Off, null],
    'not known, an effort goes as named' => [null, ReasoningEffort::Medium, 'medium'],
]);

it('leaves out the sampling settings of a model that takes no temperature', function (): void {
    $settings = ['temperature' => 0.2, 'top_p' => 0.9, 'top_k' => 40, 'presence_penalty' => 0.1, 'frequency_penalty' => 0.1, 'tools' => []];

    expect(llmWithEfforts(null, takesTemperature: false)->sampling($settings))->toBe(['tools' => []])
        ->and(llmWithEfforts(null, takesTemperature: true)->sampling($settings))->toBe($settings)
        // Not known: sent as they are.
        ->and(llmWithEfforts(null)->sampling($settings))->toBe($settings);
});

it('fits a request to the model: its sampling and its least effort by default', function (): void {
    $luna = llmWithEfforts(['none', 'low', 'medium', 'high', 'xhigh', 'max'], takesTemperature: false);

    expect($luna->requestSettings(['temperature' => 0]))->toBe(['reasoning_effort' => 'none'])
        ->and($luna->requestSettings([], ReasoningEffort::High))->toBe(['reasoning_effort' => 'high'])
        ->and(llmWithEfforts(null, takesTemperature: true)->requestSettings(['temperature' => 0]))->toBe(['temperature' => 0]);
});

it('asks for no longer a reply than the model writes', function (): void {
    $short = new Llm(['name' => 'model', 'capabilities' => ['reasoning' => false, 'limit' => ['context' => 32000, 'output' => 4096]]]);

    expect($short->replyLength(['max_tokens' => 8096, 'temperature' => 0.2]))->toBe(['max_tokens' => 4096, 'temperature' => 0.2])
        ->and($short->replyLength(['max_completion_tokens' => 8096]))->toBe(['max_completion_tokens' => 4096])
        ->and($short->replyLength(['max_tokens' => 1024]))->toBe(['max_tokens' => 1024])
        // No length asked: the provider's default.
        ->and($short->replyLength(['temperature' => 0.2]))->toBe(['temperature' => 0.2])
        // Its longest reply not known: sent as asked.
        ->and(llmWithEfforts(null, takesTemperature: true)->replyLength(['max_tokens' => 8096]))->toBe(['max_tokens' => 8096]);
});

it('offers tools only to a model that can call them', function (): void {
    $settings = ['temperature' => 0.2, 'tools' => [['type' => 'function']], 'tool_choice' => 'auto', 'parallel_tool_calls' => false];
    $noTools = new Llm(['name' => 'model', 'capabilities' => ['reasoning' => false, 'tool_call' => false]]);

    expect($noTools->callsTools())->toBeFalse()
        ->and($noTools->requestSettings($settings))->toBe(['temperature' => 0.2])
        ->and(new Llm(['name' => 'model', 'capabilities' => ['reasoning' => false, 'tool_call' => true]])->requestSettings($settings))->toBe($settings)
        // Not known: offered as asked.
        ->and(llmWithEfforts(null)->callsTools())->toBeTrue()
        ->and(llmWithEfforts(null)->requestSettings($settings))->toBe($settings);
});

it('flushes the cached catalog of usable models when a provider changes', function (): void {
    $model = testLlm();

    expect(Llm::available()->contains('id', $model->id))->toBeTrue();

    $model->provider->update(['is_enabled' => false]);

    expect(Llm::available()->contains('id', $model->id))->toBeFalse();
});
