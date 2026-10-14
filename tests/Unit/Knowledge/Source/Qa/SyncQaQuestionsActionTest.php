<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Qa\Actions\CreateAgentQaAction;
use Domain\Knowledge\Source\Qa\Actions\SyncQaQuestionsAction;
use Domain\Knowledge\Source\Qa\Actions\UpdateAgentQaAction;
use Domain\Knowledge\Source\Qa\Data\QaEntryData;
use Domain\Knowledge\Source\Qa\Jobs\EmbedQaQuestionsJob;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswerQuestion;
use Domain\Knowledge\Source\Qa\Services\QuestionNormalizer;
use Illuminate\Support\Facades\Bus;

/**
 * Every question of a Q&A entry gets its own row with its normalized form,
 * so a message can be matched against each question as a whole. The rows
 * follow the entry however it is saved.
 */
function qaQuestionRows(AgentQuestionAnswer $qa): array
{
    return AgentQuestionAnswerQuestion::query()
        ->where('agent_question_answer_id', $qa->id)
        ->orderBy('id')
        ->get(['agent_id', 'question', 'normalized'])
        ->toArray();
}

it('stores one normalized row per question', function (): void {
    $agent = Agent::factory()->create();

    $qa = app(CreateAgentQaAction::class)->execute($agent, new QaEntryData(
        title: 'Opening hours',
        questions: ['When are you OPEN?', '', '  What are your hours?! ', 'when are you open'],
        answer: 'We are open from nine to five.',
    ));

    expect(qaQuestionRows($qa))->toBe([
        ['agent_id' => $agent->id, 'question' => 'When are you OPEN?', 'normalized' => 'when are you open'],
        ['agent_id' => $agent->id, 'question' => 'What are your hours?!', 'normalized' => 'what are your hours'],
    ]);
});

it('rebuilds the rows when the questions change, and only then', function (): void {
    $agent = Agent::factory()->create();
    $qa = app(CreateAgentQaAction::class)->execute($agent, new QaEntryData('Hours', ['When are you open?'], 'From nine to five.'));

    app(UpdateAgentQaAction::class)->execute($qa, new QaEntryData('Hours', ['Are you open on Sunday?'], 'From nine to five.'));

    expect(array_column(qaQuestionRows($qa), 'normalized'))->toBe(['are you open on sunday']);

    $qa->update(['answer' => 'From ten to six.']);

    expect(array_column(qaQuestionRows($qa), 'normalized'))->toBe(['are you open on sunday']);
});

it('leaves out a question that is only punctuation', function (): void {
    $agent = Agent::factory()->create();
    $qa = AgentQuestionAnswer::query()->create(['agent_id' => $agent->id, 'title' => 'Odd', 'questions' => "???\nReal question?", 'answer' => 'An answer.']);

    app(SyncQaQuestionsAction::class)->execute($qa);

    expect(array_column(qaQuestionRows($qa), 'question'))->toBe(['Real question?']);
});

it('normalizes case, accents, Arabic diacritics, punctuation and spaces', function (string $text, string $normalized): void {
    expect(QuestionNormalizer::normalize($text))->toBe($normalized);
})->with([
    'case and punctuation' => ['What are your OPENING hours?', 'what are your opening hours'],
    'accents' => ['Où est le café ?', 'ou est le cafe'],
    'Arabic diacritics and tatweel' => ['مَتَى تَفْتَحُــون؟', 'متى تفتحون'],
    'symbols and spaces' => ['  price   in €  /  $ ', 'price in'],
    'only punctuation' => ['?!', ''],
]);

it('queues the embeddings of the questions after saving', function (): void {
    Bus::fake();
    $agent = Agent::factory()->create();

    $qa = app(CreateAgentQaAction::class)->execute($agent, new QaEntryData('Hours', ['When are you open?'], 'From nine to five.'));

    Bus::assertDispatched(EmbedQaQuestionsJob::class, fn (EmbedQaQuestionsJob $job): bool => $job->qa->is($qa));
});
