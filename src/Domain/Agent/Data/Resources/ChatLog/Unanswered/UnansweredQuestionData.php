<?php

declare(strict_types=1);

namespace Domain\Agent\Data\Resources\ChatLog\Unanswered;

use Domain\Agent\Queries\UnansweredQuestionsQuery;
use Domain\Conversation\Enums\MessageFeedback;
use Domain\Conversation\Models\Message;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One reply in the "Unanswered" list: the visitor's question, the reply, why
 * it is listed (no knowledge found, a thumbs-down, or both, with the visitor's
 * note), and whether staff already revised it. `message_id` is the reply's id
 * for "Revise answer"; `conversation_id` opens it in the chat log.
 */
#[TypeScript]
class UnansweredQuestionData extends Data
{
    public function __construct(
        public string $message_id,
        public string $conversation_id,
        public string $question,
        public string $answer,
        public bool $not_found,
        public bool $thumbs_down,
        public ?string $feedback_comment,
        public bool $revised,
        public string $created_at,
    ) {}

    /**
     * @param  Message  $reply  a row of {@see UnansweredQuestionsQuery}, with `question` and `conversation_external_id` selected
     */
    public static function fromReply(Message $reply): self
    {
        return new self(
            message_id: (string) $reply->id,
            conversation_id: (string) $reply->getAttribute('conversation_external_id'),
            question: (string) $reply->getAttribute('question'),
            answer: $reply->content,
            not_found: $reply->has_llm_found_answer === false && ! $reply->is_response_from_qa,
            thumbs_down: $reply->feedback === MessageFeedback::Negative,
            feedback_comment: $reply->feedback_comment,
            revised: $reply->agent_question_answer_id !== null,
            created_at: $reply->created_at->toISOString(),
        );
    }
}
