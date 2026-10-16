<?php

use Domain\Agent\Models\Agent;
use Domain\Channel\Enums\ChannelConnectFailure;
use Domain\Channel\Exceptions\ChannelConnectException;
use Domain\Channel\Messenger\Models\AgentMessengerPage;
use Domain\Channel\Services\ChannelConnectionGuard;
use Illuminate\Database\Eloquent\Model;
use Pest\Expectation;

/**
 * When two agents race for the same account, the unique index catches the
 * loser; it gets the same "taken" failure as the up-front check instead of a
 * server error.
 */
it('turns a unique-index clash into a taken-by-another-agent failure', function (): void {
    $page = fn (Agent $agent): AgentMessengerPage => AgentMessengerPage::create([
        'agent_id' => $agent->id,
        'page_access_token' => 'token',
        'page_id' => 'PAGE-1',
        'page_name' => 'Acme',
        'is_enabled' => true,
    ]);

    $page(Agent::factory()->create());
    $second = Agent::factory()->create();

    expect(fn (): Model => new ChannelConnectionGuard()->store(fn (): AgentMessengerPage => $page($second)))
        ->toThrow(fn (ChannelConnectException $e): Expectation => expect($e->failure)->toBe(ChannelConnectFailure::TakenByAnotherAgent));
});
