<?php

namespace Domain\Agent\Actions\ChatLog;

use Domain\Agent\Data\Resources\ChatLog\Countries\ChatLogCountriesResourceData;
use Domain\Agent\Data\Resources\ChatLog\Countries\ChatLogCountryData;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Query\Builder;
use Spatie\LaravelData\DataCollection;
use Support\Localization\Country\Models\Country;

/**
 * Collect the distinct visitor countries that have appeared in the agent's
 * chat sessions — feeds the country filter on the chat-log page.
 */
class ListAgentVisitorCountriesAction
{
    public function execute(Agent $agent): ChatLogCountriesResourceData
    {
        // From the countries side: each country is one probe of
        // (agent_id, country_id), instead of reading every session the
        // agent ever had.
        $countries = Country::query()
            ->whereExists(fn (Builder $sessions) => $sessions
                ->selectRaw('1')
                ->from('chat_sessions')
                ->where('chat_sessions.agent_id', $agent->id)
                ->whereColumn('chat_sessions.country_id', 'countries.id'))
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Country $country): ChatLogCountryData => new ChatLogCountryData(
                id: $country->id,
                code: $country->code,
                name: $country->name,
            ));

        return new ChatLogCountriesResourceData(
            countries: new DataCollection(ChatLogCountryData::class, $countries),
        );
    }
}
