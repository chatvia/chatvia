<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Domain\Llm\Enums\LlmTask;
use InvalidArgumentException;

/**
 * What each of the app's own AI tasks is called and does, in the admin's
 * words. Settings → AI models shows it next to each task's model, and the
 * setup explains the model for quick tasks with it, so both say the same.
 */
final class LlmTaskText
{
    /**
     * The app tasks on the Models for app tasks card, by when they run, in
     * the order it lists them. Image descriptions are not among them: their
     * model sits next to its switch on the Documents card.
     */
    public const array GROUPS = [
        'During a chat (the reply waits for these)' => [LlmTask::FollowUp, LlmTask::ToolRouting, LlmTask::Guardrails],
        'After a reply (in the background)' => [LlmTask::Titles, LlmTask::ConversationAnalysis],
        'Agent setup' => [LlmTask::Starters, LlmTask::Translation, LlmTask::FormText, LlmTask::ChatAddress],
        'Training sources' => [LlmTask::Enhancement, LlmTask::Tables],
        'Testing' => [LlmTask::EvalJudge],
    ];

    /**
     * The tasks the Models for app tasks card lists, in its order.
     *
     * @return list<LlmTask>
     */
    public static function listedTasks(): array
    {
        return array_merge(...array_values(self::GROUPS));
    }

    public static function label(LlmTask $task): string
    {
        return self::text($task)[0];
    }

    /** What the task does, when it runs and which model fits it. */
    public static function help(LlmTask $task): string
    {
        return self::text($task)[1];
    }

    /**
     * The tasks the model for quick tasks does after the setup, by when
     * they run: the small ones but image descriptions, which need a model
     * that reads images ({@see TaskModelPreset}).
     *
     * @return array<string, non-empty-list<LlmTask>>
     */
    public static function quickTaskGroups(): array
    {
        return collect(self::GROUPS)
            ->map(fn (array $tasks): array => array_values(array_filter($tasks, TaskModelPreset::isQuickTask(...))))
            ->filter()
            ->all();
    }

    /**
     * @return array{string, string}
     */
    private static function text(LlmTask $task): array
    {
        return match ($task) {
            LlmTask::FollowUp => ['Follow-up questions', 'Turns a short follow-up like "and the price?" into a full question for the search. It runs before each reply, so a small, fast model suits it.'],
            LlmTask::ToolRouting => ['Tool picking', 'When an agent has tools from several connectors, picks the one a message is about. It runs before each reply, so a small, fast model suits it.'],
            LlmTask::Guardrails => ['Guardrails', 'Checks messages for tricks, off-topic questions and abuse when an agent has these checks on. It runs before each reply, so a small, fast model suits it.'],
            LlmTask::Titles => ['Conversation titles', 'Writes a short title for each conversation from its first messages.'],
            LlmTask::ConversationAnalysis => ['Conversation analysis', 'Finds the topics, mood and language of conversations for Analytics. The model must support structured JSON replies.'],
            LlmTask::Starters => ['Conversation starters', 'Suggests conversation starters for an agent, when asked in its setup.'],
            LlmTask::Translation => ['Translating agent texts', 'Translates an agent\'s title, welcome message and message box hint into each language when they are saved.'],
            LlmTask::FormText => ['Form descriptions', 'Rewrites the description of a form, when asked in the form editor.'],
            LlmTask::ChatAddress => ['Hosted chat addresses', 'Suggests a short web address for an agent\'s hosted chat.'],
            LlmTask::Enhancement => ['AI enhancement', 'Writes a summary and likely questions for each part of a file or web page. Large files make many calls. Turned on in Settings → Knowledge.'],
            LlmTask::Tables => ['Tables', 'Turns the tables inside documents into plain text, so search can find what they say.'],
            LlmTask::Images => ['Image descriptions', 'Describes the pictures in documents so search can find them. Needs a model that reads images. Turned on in Settings → AI models → Describe images.'],
            LlmTask::EvalJudge => ['Evaluation judge', 'Scores an agent\'s answers in evaluations. A stronger model gives fairer scores.'],
            LlmTask::Chat => throw new InvalidArgumentException('The chat model is "Default model for new agents".'),
        };
    }
}
