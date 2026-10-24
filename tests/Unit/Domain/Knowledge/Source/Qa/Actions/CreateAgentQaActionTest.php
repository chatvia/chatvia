<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Qa\Actions\CreateAgentQaAction;
use Domain\Knowledge\Source\Qa\Data\QaEntryData;
use Domain\Knowledge\Source\Qa\Jobs\EmbedQaQuestionsJob;
use Illuminate\Support\Facades\Bus;

/**
 * Curated Q&A entries store their question phrasings one per line; empty
 * phrasings are dropped. Saving one queues the embeddings of its questions.
 */
it('creates a Q&A entry with one question per line', function (): void {
    $agent = Agent::factory()->create();

    $qa = app(CreateAgentQaAction::class)->execute($agent, new QaEntryData(
        title: 'Opening hours',
        questions: ['When are you open?', '', 'What are your hours?'],
        answer: 'We are open from nine to five.',
    ));

    expect($qa->agent_id)->toBe($agent->id)
        ->and($qa->questions)->toBe("When are you open?\nWhat are your hours?")
        ->and($qa->answer)->toBe('We are open from nine to five.');
});

it('queues the embeddings of the questions after saving', function (): void {
    Bus::fake();
    $agent = Agent::factory()->create();

    $qa = app(CreateAgentQaAction::class)->execute($agent, new QaEntryData('Hours', ['When are you open?'], 'From nine to five.'));

    Bus::assertDispatched(EmbedQaQuestionsJob::class, fn (EmbedQaQuestionsJob $job): bool => $job->qa->is($qa));
});
