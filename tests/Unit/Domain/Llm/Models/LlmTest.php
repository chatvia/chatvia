<?php

declare(strict_types=1);

use Domain\Llm\Enums\ReasoningEffort;
use Domain\Llm\Models\Llm;

/**
 * A model's facts (from the models.dev catalog) decide what the app sends it:
 * the effort value for the effort chosen, whether sampling settings go, and
 * whether tools are offered. The effort lists below are the catalog's own.
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
    'none or high, low asked: the one that thinks' => [['none', 'high'], ReasoningEffort::Low, 'high'],
    'a tie goes to more thinking' => [['low', 'high', 'max'], ReasoningEffort::Medium, 'high'],
    'unknown words are ignored' => [['none', 'default'], ReasoningEffort::Medium, 'none'],
    'not known, off: nothing' => [null, ReasoningEffort::Off, null],
    'not known, an effort goes as named' => [null, ReasoningEffort::Medium, 'medium'],
]);

it('fits a request to the model: its least effort by default, no sampling settings without temperature, and tools only when it calls them', function (): void {
    $settings = ['temperature' => 0.2, 'top_p' => 0.9, 'top_k' => 40, 'presence_penalty' => 0.1, 'frequency_penalty' => 0.1, 'tools' => [['type' => 'function']], 'tool_choice' => 'auto', 'parallel_tool_calls' => false];
    $luna = llmWithEfforts(['none', 'low', 'medium', 'high', 'xhigh', 'max'], takesTemperature: false);
    $noTools = new Llm(['name' => 'model', 'capabilities' => ['reasoning' => false, 'tool_call' => false]]);

    expect($luna->requestSettings(['temperature' => 0]))->toBe(['reasoning_effort' => 'none'])
        ->and($luna->requestSettings([], ReasoningEffort::High))->toBe(['reasoning_effort' => 'high'])
        ->and(llmWithEfforts(null, takesTemperature: false)->sampling($settings))->toBe(['tools' => [['type' => 'function']], 'tool_choice' => 'auto', 'parallel_tool_calls' => false])
        ->and(llmWithEfforts(null, takesTemperature: true)->sampling($settings))->toBe($settings)
        ->and(llmWithEfforts(null)->sampling($settings))->toBe($settings)
        ->and($noTools->callsTools())->toBeFalse()
        ->and($noTools->requestSettings(['temperature' => 0.2, 'tools' => [['type' => 'function']], 'tool_choice' => 'auto', 'parallel_tool_calls' => false]))->toBe(['temperature' => 0.2])
        ->and(llmWithEfforts(null)->callsTools())->toBeTrue()
        ->and(llmWithEfforts(null)->requestSettings($settings))->toBe($settings);
});
