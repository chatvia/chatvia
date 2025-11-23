<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentKnowledgeTextUnlinkController
{
    public function __invoke(Request $request, Agent $agent, string $id)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $text = $agent->texts()->where('source_texts.id', $id)->firstOrFail();

        abort_if(
            boolean: $request->user()->cannot('delete', $text),
            code: 403,
        );

        // Only unlink, don't delete
        $text->agents()->detach($agent->id);

        return response()->json([
            'message' => 'Text unlinked from agent successfully.',
        ]);
    }
}
