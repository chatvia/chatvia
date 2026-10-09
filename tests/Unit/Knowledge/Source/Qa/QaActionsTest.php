<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Qa\Actions\CreateAgentQaAction;
use Domain\Knowledge\Source\Qa\Actions\UpdateAgentQaAction;
use Domain\Knowledge\Source\Qa\Data\QaEntryData;

/**
 * Curated Q&A entries store their question phrasings one per line; empty
 * phrasings are dropped.
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

it('replaces the title, questions and answer of an entry', function (): void {
    $agent = Agent::factory()->create();
    $qa = app(CreateAgentQaAction::class)->execute($agent, new QaEntryData('Old', ['Old question?'], 'Old answer text.'));

    app(UpdateAgentQaAction::class)->execute($qa, new QaEntryData('New', ['New question?'], 'New answer text.'));

    expect($qa->fresh())
        ->title->toBe('New')
        ->questions->toBe('New question?')
        ->answer->toBe('New answer text.');
});
