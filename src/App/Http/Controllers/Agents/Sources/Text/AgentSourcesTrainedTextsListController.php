<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use Domain\Agents\Models\Agent;
use Domain\Sources\Text\Actions\ListAttachableTextsForAgentAction;
use Domain\Sources\Text\Data\TrainedTextRowData;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class AgentSourcesTrainedTextsListController
{
    /**
     * @return Collection<int, TrainedTextRowData>
     */
    public function __invoke(Request $request, Agent $agent, ListAttachableTextsForAgentAction $list): Collection
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        return $list->execute($request->user(), $agent);
    }
}
