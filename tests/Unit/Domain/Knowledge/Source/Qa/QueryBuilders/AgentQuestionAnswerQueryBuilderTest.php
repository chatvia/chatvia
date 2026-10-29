<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Qa\Actions\CreateAgentQaAction;
use Domain\Knowledge\Source\Qa\Data\QaEntryData;
use Domain\Knowledge\Source\Qa\Models\AgentQuestionAnswer;
use Illuminate\Support\Facades\Bus;

/**
 * The whole-question match finds the agent's entry whose question equals the
 * message once normalized, and nothing for a part of a question or another
 * agent's entry.
 */
beforeEach(function (): void {
    Bus::fake();

    $this->agent = Agent::factory()->create();
    $this->qa = app(CreateAgentQaAction::class)->execute(
        $this->agent,
        new QaEntryData('Hours', ['What are your opening hours?', 'When do you close?'], 'From nine to five.'),
    );
});

it('matches a whole question, ignoring case, punctuation and spacing', function (string $message): void {
    expect(AgentQuestionAnswer::query()->wholeQuestionMatch($this->agent->id, $message)?->id)->toBe($this->qa->id);
})->with([
    'other case and spacing' => '  what are your   OPENING hours ',
    'second question' => 'When do you close',
]);

it('does not match a part of a question, an empty message or another agent\'s entry', function (): void {
    $other = Agent::factory()->create();

    expect(AgentQuestionAnswer::query()->wholeQuestionMatch($this->agent->id, 'opening hours'))->toBeNull()
        ->and(AgentQuestionAnswer::query()->wholeQuestionMatch($this->agent->id, 'What are your opening hours on Sunday?'))->toBeNull()
        ->and(AgentQuestionAnswer::query()->wholeQuestionMatch($this->agent->id, '?!'))->toBeNull()
        ->and(AgentQuestionAnswer::query()->wholeQuestionMatch($other->id, 'What are your opening hours?'))->toBeNull();
});
