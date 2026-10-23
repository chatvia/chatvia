<?php

declare(strict_types=1);

use Domain\Llm\Enums\LlmTask;
use Domain\Llm\Enums\ModelTier;

/**
 * LlmTask: the chat model and the judgement tasks need a large model; the
 * jobs the reply waits for run on the small one.
 */
it('needs a large model for answers and judgements, a small one for the quick tasks', function (): void {
    expect(LlmTask::Chat->tier())->toBe(ModelTier::Large)
        ->and(LlmTask::EvalJudge->tier())->toBe(ModelTier::Large)
        ->and(LlmTask::FollowUp->tier())->toBe(ModelTier::Small)
        ->and(LlmTask::ToolRouting->tier())->toBe(ModelTier::Small)
        ->and(LlmTask::Guardrails->tier())->toBe(ModelTier::Small);
});
