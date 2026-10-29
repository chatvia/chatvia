<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Retrieval\Services\KnowledgeSearchSwitch;

/**
 * Whether an agent searches its knowledge: the admin's install-wide switch
 * wins, and the agent's own switch counts only while the admin lets agents
 * choose.
 */
it('decides from the install switch, the agent choice and the agent switch', function (bool $enabled, bool $choice, bool $agentSearches, bool $searches, bool $canChange): void {
    config(['chatvia.vector.search.enabled' => $enabled, 'chatvia.vector.search.agent_choice' => $choice]);
    $agent = Agent::factory()->create();
    $agent->setSetting('search', $agentSearches, 'knowledge');

    $switch = app(KnowledgeSearchSwitch::class);
    $state = $switch->state($agent->fresh());

    expect($switch->for($agent->fresh()))->toBe($searches)
        ->and($state->enabled)->toBe($searches)
        ->and($state->canChange)->toBe($canChange)
        ->and($state->installEnabled)->toBe($enabled);
})->with([
    'all on' => [true, true, true, true, true],
    'agent off' => [true, true, false, false, true],
    'no choice ignores the agent' => [true, false, false, true, false],
    'install off' => [false, true, true, false, false],
]);
