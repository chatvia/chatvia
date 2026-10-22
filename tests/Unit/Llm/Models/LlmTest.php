<?php

declare(strict_types=1);

use Domain\Llm\Enums\ReasoningEffort;
use Domain\Llm\Models\Llm;

/**
 * A model's facts (from the models.dev catalog) decide what the app sends it:
 * the effort value for the effort chosen, and whether sampling settings go.
 * The effort lists below are the catalog's own.
 */
function llmWithEfforts(?array $efforts, ?bool $takesTemperature = null): Llm
{
    // Not listed on models.dev at all when nothing is known.
    $known = $efforts !== null || $takesTemperature !== null;

    return new Llm(['internal_name' => 'model', 'capabilities' => $known ? modelsDevEntry($efforts, $takesTemperature) : null]);
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
    $settings = ['temperature' => 0.2, 'top_p' => 0.9, 'presence_penalty' => 0.1, 'frequency_penalty' => 0.1, 'tools' => []];

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
