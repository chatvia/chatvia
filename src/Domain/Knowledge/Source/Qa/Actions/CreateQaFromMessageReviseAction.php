<?php

namespace Domain\Knowledge\Source\Qa\Actions;

use Domain\Agent\Events\AgentAnswersChanged;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Message;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Turns an owner's correction of an agent reply (Chat log → "revise answer")
 * into a curated Q&A entry, and marks the corrected reply as revised.
 *
 * Seam with the Conversation domain: marking the reply writes the message's
 * `agent_question_answer_id` directly, because Conversation offers no action
 * for it. The message is looked up through the agent's sessions, so only a
 * reply of this agent can be marked.
 */
class CreateQaFromMessageReviseAction
{
    /** Titles are cut to this many characters of the visitor's message. */
    private const int TITLE_LENGTH = 100;

    public function execute(
        Agent $agent,
        string $messageId,
        string $userMessage,
        string $expectedResponse,
    ): AgentQuestionAnswer {
        return DB::transaction(function () use ($agent, $messageId, $userMessage, $expectedResponse): AgentQuestionAnswer {
            $message = Message::query()
                ->whereHas('conversation.session', fn ($query) => $query->where('agent_id', $agent->id))
                ->where('id', $messageId)
                ->where('role', MessageRole::Assistant)
                ->firstOrFail();

            $qa = AgentQuestionAnswer::query()->create([
                'agent_id' => $agent->id,
                'title' => Str::substr($userMessage, 0, self::TITLE_LENGTH),
                'questions' => $userMessage,
                'answer' => $expectedResponse,
            ]);

            $message->update(['agent_question_answer_id' => $qa->id]);

            AgentAnswersChanged::dispatch($agent->id);

            return $qa;
        });
    }
}
