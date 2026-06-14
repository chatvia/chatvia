<?php

namespace App\Http\Controllers\Agent\Source\Text;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Text\Actions\ListAttachableTextsForAgentAction;
use Domain\Knowledge\Source\Text\Data\TrainedTextRowData;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AgentSourcesTrainedTextsListController
{
    /**
     * @return Collection<int, TrainedTextRowData>
     */
    public function __invoke(Request $request, Agent $agent, ListAttachableTextsForAgentAction $list): Collection
    {
        return $list->execute($request->user(), $agent);
    }
}
