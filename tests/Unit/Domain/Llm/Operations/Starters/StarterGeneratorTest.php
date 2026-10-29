<?php

declare(strict_types=1);

use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Models\Llm;
use Domain\Llm\Operations\Starters\StarterGenerator;

/**
 * Suggested starters: the model's JSON list comes back as clean questions.
 */

/** A test model picked for the starters task in Settings → AI. */
function sgStartersModel(string $name = 'test-model'): Llm
{
    $model = testLlm($name);
    config([LlmTask::Starters->configKey() => $model->id]);

    return $model;
}

it('returns the questions the model suggests', function (): void {
    sgStartersModel();
    fakeLlmCompletion('["What are your opening hours?", "  How do I get a refund?  ", ""]');

    expect(app(StarterGenerator::class)->generate(['We open at nine.'], 5))
        ->toBe(['What are your opening hours?', 'How do I get a refund?']);
});
