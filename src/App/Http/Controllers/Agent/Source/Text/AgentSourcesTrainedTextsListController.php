<?php

namespace App\Http\Controllers\Agent\Source\Text;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Text\Actions\ListAttachableTextsForAgentAction;
use Domain\Knowledge\Source\Text\Data\TrainedTextRowData;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Lists the workspace's trained texts the agent does not use yet (the
 * "attach an existing text" dialog).
 */
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
