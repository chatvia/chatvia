<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Source\Qa\Actions\CreateAgentQaAction;
use Domain\Knowledge\Source\Qa\Actions\EmbedQaQuestionsAction;
use Domain\Knowledge\Source\Qa\Data\QaEntryData;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswerQuestion;
use Illuminate\Support\Facades\Bus;

/**
 * Embedding a Q&A entry's questions embeds only the ones without an
 * embedding yet, so saving an entry again pays for nothing twice.
 */
it('embeds only the questions that have no embedding yet', function (): void {
    Bus::fake();
    $agent = Agent::factory()->create();
    $qa = app(CreateAgentQaAction::class)->execute($agent, new QaEntryData('Hours', ['What are your opening hours?'], 'We are open from nine to five.'));
    app(EmbedQaQuestionsAction::class)->execute($qa);

    $embeddings = Mockery::mock(EmbeddingsProviderInterface::class);
    $embeddings->shouldNotReceive('embedBatch');
    app()->instance(EmbeddingsProviderInterface::class, $embeddings);

    app(EmbedQaQuestionsAction::class)->execute($qa);

    expect(AgentQuestionAnswerQuestion::query()->where('agent_question_answer_id', $qa->id)->whereNull('embedding')->exists())->toBeFalse();
});
