<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Qa\Actions\CreateAgentQaAction;
use Domain\Knowledge\Source\Qa\Actions\UpdateAgentQaAction;
use Domain\Knowledge\Source\Qa\Data\QaEntryData;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswerQuestion;

/**
 * Every question of a Q&A entry gets its own row with its normalized form,
 * so a message can be matched against each question as a whole. The rows
 * are rebuilt when the questions change, and only then.
 */
function sqqRows(AgentQuestionAnswer $qa): array
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

    expect(sqqRows($qa))->toBe([
        ['agent_id' => $agent->id, 'question' => 'When are you OPEN?', 'normalized' => 'when are you open'],
        ['agent_id' => $agent->id, 'question' => 'What are your hours?!', 'normalized' => 'what are your hours'],
    ]);
});

it('rebuilds the rows when the questions change, and only then', function (): void {
    $agent = Agent::factory()->create();
    $qa = app(CreateAgentQaAction::class)->execute($agent, new QaEntryData('Hours', ['When are you open?'], 'From nine to five.'));

    app(UpdateAgentQaAction::class)->execute($qa, new QaEntryData('Hours', ['Are you open on Sunday?'], 'From nine to five.'));

    expect(array_column(sqqRows($qa), 'normalized'))->toBe(['are you open on sunday']);

    $qa->update(['answer' => 'From ten to six.']);

    expect(array_column(sqqRows($qa), 'normalized'))->toBe(['are you open on sunday']);
});
