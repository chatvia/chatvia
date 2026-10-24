<?php

declare(strict_types=1);

namespace Domain\Agent\Queries;

use Domain\Agent\Data\Resources\ChatLog\Unanswered\UnansweredQuestionData;
use Domain\Agent\Enums\UnansweredFilter;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\MessageFeedback;
use Domain\Conversation\Enums\MessageOrigin;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * The agent's replies that need a look, newest first, each with the visitor
 * question it answered: replies for which no knowledge was found (no Q&A
 * match and no passage passed the relevance check) and replies a visitor
 * gave a thumbs-down. This is where staff find the gaps in the knowledge and
 * fix them with "Revise answer". "Not found" comes from the retrieval
 * signals, so a greeting the agent answered without knowledge is listed too.
 * Playground tests are left out.
 */
class UnansweredQuestionsQuery
{
    private const int PER_PAGE = 25;

    /**
     * @return LengthAwarePaginator<int, UnansweredQuestionData>
     */
    public function page(Agent $agent, UnansweredFilter $filter, bool $includeRevised, int $page = 1): LengthAwarePaginator
    {
        // The visitor message the reply answered: the latest one before it in
        // the same conversation. Messages are stored in order, so the id says
        // which came first even within one second.
        $question = Message::query()
            ->from('chat_messages as q')
            ->select('q.content')
            ->whereColumn('q.chat_conversation_id', 'chat_messages.chat_conversation_id')
            ->whereColumn('q.id', '<', 'chat_messages.id')
            ->where('q.role', MessageRole::User->value)
            ->orderByDesc('q.id')
            ->limit(1);

        /** @var LengthAwarePaginator<int, Message> $replies */
        $replies = Message::query()
            ->join('chat_conversations as c', 'c.id', '=', 'chat_messages.chat_conversation_id')
            ->where('c.agent_id', $agent->id)
            ->where('chat_messages.role', MessageRole::Assistant->value)
            ->where(static function (Builder $query): void {
                $query->whereNull('chat_messages.source')
                    ->orWhere('chat_messages.source', '!=', MessageOrigin::Playground->value);
            })
            ->where(static function (Builder $query) use ($filter): void {
                match ($filter) {
                    UnansweredFilter::NotFound => self::notFound($query),
                    UnansweredFilter::ThumbsDown => self::thumbsDown($query),
                    UnansweredFilter::All => $query->where(static fn (Builder $either) => self::notFound($either))
                        ->orWhere(static fn (Builder $either) => self::thumbsDown($either)),
                };
            })
            ->when(! $includeRevised, static fn (Builder $query) => $query->whereNull('chat_messages.agent_question_answer_id'))
            ->select([
                'chat_messages.id',
                'chat_messages.content',
                'chat_messages.feedback',
                'chat_messages.feedback_comment',
                'chat_messages.has_llm_found_answer',
                'chat_messages.is_response_from_qa',
                'chat_messages.agent_question_answer_id',
                'chat_messages.created_at',
                'c.external_id as conversation_external_id',
            ])
            ->selectSub($question, 'question')
            ->orderByDesc('chat_messages.created_at')
            ->orderByDesc('chat_messages.id')
            ->paginate(self::PER_PAGE, page: $page);

        return $replies->through(UnansweredQuestionData::fromReply(...));
    }

    /** @param  Builder<Message>  $query */
    private static function notFound(Builder $query): void
    {
        $query->where('chat_messages.has_llm_found_answer', false)
            ->where('chat_messages.is_response_from_qa', false);
    }

    /** @param  Builder<Message>  $query */
    private static function thumbsDown(Builder $query): void
    {
        $query->where('chat_messages.feedback', MessageFeedback::Negative->value);
    }
}
