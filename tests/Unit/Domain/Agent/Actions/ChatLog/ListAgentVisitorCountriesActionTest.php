<?php

use Domain\Agent\Actions\ChatLog\ListAgentVisitorCountriesAction;
use Domain\Agent\Data\Resources\ChatLog\Countries\ChatLogCountryData;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Facades\DB;
use Support\Localization\Country\Models\Country;

/**
 * The chat-log country filter offers each country the agent's visitors came
 * from exactly once, sorted by name. Sessions without a country and other
 * agents' visitors never add options.
 */
it('lists each visitor country of the agent once, sorted by name', function (): void {
    $countries = Country::query()->whereIn('code', ['se', 'de', 'fr', 'it'])->get()->keyBy('code');
    $agent = Agent::factory()->create();

    Session::factory()->create(['agent_id' => $agent->id, 'country_id' => $countries['se']->id]);
    Session::factory()->create(['agent_id' => $agent->id, 'country_id' => $countries['se']->id]);
    Session::factory()->create(['agent_id' => $agent->id, 'country_id' => $countries['de']->id]);
    Session::factory()->create(['agent_id' => $agent->id, 'country_id' => $countries['fr']->id]);
    Session::factory()->create(['agent_id' => $agent->id, 'country_id' => null]);
    Session::factory()->create(['agent_id' => Agent::factory()->create()->id, 'country_id' => $countries['it']->id]);

    $result = app(ListAgentVisitorCountriesAction::class)->execute($agent);

    expect($result->countries->toCollection()->map(fn (ChatLogCountryData $country): string => $country->code)->all())
        ->toBe(['fr', 'de', 'se']);
});

it('returns no options for an agent without located visitors', function (): void {
    $agent = Agent::factory()->create();
    Session::factory()->create(['agent_id' => $agent->id, 'country_id' => null]);

    expect(app(ListAgentVisitorCountriesAction::class)->execute($agent)->countries->toCollection())->toBeEmpty();
});

it('reads the countries in one query', function (): void {
    $agent = Agent::factory()->create();
    $queries = 0;
    DB::listen(function () use (&$queries): void {
        $queries++;
    });

    app(ListAgentVisitorCountriesAction::class)->execute($agent);

    expect($queries)->toBe(1);
});
