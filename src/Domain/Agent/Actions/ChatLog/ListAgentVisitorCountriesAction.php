<?php

namespace Domain\Agent\Actions\ChatLog;

use App\Country\Models\Country;
use Domain\Agent\Data\Resources\ChatLog\Countries\ChatLogCountriesResourceData;
use Domain\Agent\Data\Resources\ChatLog\Countries\ChatLogCountryData;
use Domain\Agent\Models\Agent;
use Domain\Chat\Models\ChatSession;
use Spatie\LaravelData\DataCollection;

/**
 * Collect the distinct visitor countries that have appeared in the agent's
 * chat sessions — feeds the country filter on the chat-log page.
 */
class ListAgentVisitorCountriesAction
{
    public function execute(Agent $agent): ChatLogCountriesResourceData
    {
        $countryIds = ChatSession::query()
            ->where('agent_id', $agent->id)
            ->whereNotNull('country_id')
            ->distinct()
            ->pluck('country_id');

        $countries = Country::query()
            ->whereIn('id', $countryIds)
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
