<?php

namespace Domain\Conversation\Services\Streaming\Sources\Concerns;

use Domain\Conversation\Services\Streaming\Sources\LlmSource;
use Domain\Conversation\Services\Streaming\Sources\PdfSource;

/**
 * Shared tool-usage system note for the streaming sources that run the agentic
 * tool loop ({@see LlmSource}
 * and {@see PdfSource}).
 * Kept in one place so the note's wording and placement can't drift between the
 * two turn paths.
 */
trait InjectsToolSystemNote
{
    /**
     * Append the tool-usage system note to the system message (or prepend one
     * if absent). Called once per turn when the agent has tools. The note
     * always includes the "don't claim success without a real tool result"
     * guard, and adds the approval heads-up when approval is enabled.
     *
     * @param  array<int, array<string, mixed>>  $llmMessages
     */
    private function injectToolSystemNote(array &$llmMessages, bool $approvalEnabled): void
    {
        $note = trim(prompt('tools/approval', ['approval' => $approvalEnabled]));
        if ($note === '') {
            return;
        }

        foreach ($llmMessages as $i => $message) {
            if (($message['role'] ?? null) === 'system') {
                $llmMessages[$i]['content'] = rtrim((string) ($message['content'] ?? ''))."\n\n".$note;

                return;
            }
        }

        array_unshift($llmMessages, ['role' => 'system', 'content' => $note]);
    }
}
