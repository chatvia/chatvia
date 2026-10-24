<?php

use App\Admin\Settings\LlmTaskText;
use Domain\Llm\Enums\LlmTask;

/**
 * The names, explanations and groups of the app tasks on the Models for app
 * tasks card: every task has its text and one group, and the quick-task
 * groups hold only the small jobs the model for quick tasks does.
 */
it('names and explains every app task, and lists each in one group', function (): void {
    foreach (LlmTask::appTasks() as $task) {
        expect(LlmTaskText::label($task))->not->toBeEmpty()
            ->and(LlmTaskText::help($task))->not->toBeEmpty();
    }

    expect(array_merge(...array_values(LlmTaskText::GROUPS)))->toEqualCanonicalizing(LlmTask::appTasks());
});

it('lists as quick tasks the small ones the model for quick tasks does', function (): void {
    $quick = array_merge(...array_values(LlmTaskText::quickTaskGroups()));

    expect($quick)->toContain(LlmTask::Guardrails, LlmTask::FollowUp, LlmTask::Titles, LlmTask::Tables)
        ->not->toContain(LlmTask::Images, LlmTask::Translation, LlmTask::EvalJudge)
        ->and(LlmTaskText::quickTaskGroups())->not->toHaveKey('Testing');
});
