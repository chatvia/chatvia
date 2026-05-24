<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use Domain\Agents\Models\Agent;
use Domain\Sources\Text\Models\SourceText;
use Illuminate\Http\Request;

class AgentSourcesTrainedTextsListController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $currentAgentTextIds = $agent->texts()->pluck('source_texts.id');

        $texts = SourceText::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'ingested')
            ->whereNotIn('id', $currentAgentTextIds)
            ->with(['agents' => function ($query) {
                $query->select('agents.id', 'agents.name');
            }])
            ->get()
            ->map(function (SourceText $text) {
                return [
                    'id' => $text->id,
                    'excerpt' => substr($text->content, 0, 200),
                    'word_count' => str_word_count($text->content),
                    'agents' => $text->agents->map(function ($agent) {
                        return [
                            'id' => $agent->id,
                            'name' => $agent->name,
                        ];
                    }),
                ];
            });

        return response()->json($texts);
    }
}
