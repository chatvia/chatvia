<?php

use Domain\Agent\Data\Resources\ChatLog\Unanswered\UnansweredQuestionData;
use Domain\Agent\Enums\UnansweredFilter;
use Domain\Agent\Models\Agent;
use Domain\Agent\Queries\UnansweredQuestionsQuery;
use Domain\Conversation\Enums\MessageFeedback;
use Domain\Conversation\Enums\MessageOrigin;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;

/**
 * The "Unanswered" list: the agent's replies with no knowledge found or a
 * thumbs-down, newest first, each with the visitor question it answered.
 * Revised replies, playground tests and other agents' replies stay out.
 */
function uqqTurn(Conversation $conversation, string $question, array $reply, ?string $at = null): Message
{
    $time = $at !== null ? now()->parse($at) : now();
    Message::factory()->create(['chat_conversation_id' => $conversation->id, 'role' => 'user', 'content' => $question, 'created_at' => $time]);

    return Message::factory()->fromAssistant()->create([
        'chat_conversation_id' => $conversation->id,
        'content' => 'Reply to '.$question,
        'has_llm_found_answer' => true,
        'created_at' => $time,
        ...$reply,
    ]);
}

/** @return list<string> */
function uqqQuestions(Agent $agent, UnansweredFilter $filter = UnansweredFilter::All, bool $includeRevised = false): array
{
    return collect(app(UnansweredQuestionsQuery::class)->page($agent, $filter, $includeRevised)->items())
        ->map(fn (UnansweredQuestionData $row): string => $row->question)
        ->all();
}

beforeEach(function (): void {
    $this->agent = Agent::factory()->create();
    $this->conversation = Conversation::factory()->create(['agent_id' => $this->agent->id]);
});

it('lists not-found and thumbs-down replies, newest first, with their question', function (): void {
    uqqTurn($this->conversation, 'Answered fine', []);
    uqqTurn($this->conversation, 'Do you ship to Iceland?', ['has_llm_found_answer' => false], '-2 hours');
    $disliked = uqqTurn($this->conversation, 'What does it cost?', ['feedback' => MessageFeedback::Negative, 'feedback_comment' => 'Old price.'], '-1 hour');

    expect(uqqQuestions($this->agent))->toBe(['What does it cost?', 'Do you ship to Iceland?']);

    $row = app(UnansweredQuestionsQuery::class)->page($this->agent, UnansweredFilter::All, false)->items()[0];

    expect($row->message_id)->toBe((string) $disliked->id)
        ->and($row->conversation_id)->toBe($this->conversation->external_id)
        ->and($row->answer)->toBe('Reply to What does it cost?')
        ->and($row->thumbs_down)->toBeTrue()
        ->and($row->not_found)->toBeFalse()
        ->and($row->feedback_comment)->toBe('Old price.');
});

it('filters by reason', function (): void {
    uqqTurn($this->conversation, 'Not found', ['has_llm_found_answer' => false], '-2 hours');
    uqqTurn($this->conversation, 'Disliked', ['feedback' => MessageFeedback::Negative], '-1 hour');

    expect(uqqQuestions($this->agent, UnansweredFilter::NotFound))->toBe(['Not found'])
        ->and(uqqQuestions($this->agent, UnansweredFilter::ThumbsDown))->toBe(['Disliked']);
});

it('hides revised replies unless asked for', function (): void {
    $reply = uqqTurn($this->conversation, 'Fixed already', ['has_llm_found_answer' => false]);
    $qa = AgentQuestionAnswer::query()->create(['agent_id' => $this->agent->id, 'title' => 'Fixed', 'questions' => 'Fixed already', 'answer' => 'Yes.']);
    $reply->update(['agent_question_answer_id' => $qa->id]);

    expect(uqqQuestions($this->agent))->toBe([])
        ->and(uqqQuestions($this->agent, includeRevised: true))->toBe(['Fixed already']);
});

it('leaves out playground tests and other agents\' replies', function (): void {
    uqqTurn($this->conversation, 'From the playground', ['has_llm_found_answer' => false, 'source' => MessageOrigin::Playground]);
    uqqTurn(Conversation::factory()->create(), 'Another agent', ['has_llm_found_answer' => false]);

    expect(uqqQuestions($this->agent))->toBe([]);
});
