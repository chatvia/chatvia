<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Data\Api\Agent\AgentSourcesListData;
use App\Models\Agent;
use Illuminate\Http\Request;

class AgentSourcesTrainedListController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 403,
        );

        $request->validate([
            'type' => ['required', 'in:pdf,word,audio,video'],
        ]);

        $data = $request
            ->user()
            ->sourceFiles()
            ->with('agents', function ($query) {
                $query->select('agents.id', 'name');
            })
            ->where('status', 'ingested')
            ->when($request->filled('type'), function ($query) use ($request) {
                $query->where('filetype', $request->input('type'));
            })
            ->whereDoesntHave('agents', function ($q) use ($agent) {
                $q->where('agents.id', $agent->id);
            })
            ->select('id', 'filename', 'filetype', 'length')
            ->get();

        return AgentSourcesListData::fromSources($data);
    }
}
