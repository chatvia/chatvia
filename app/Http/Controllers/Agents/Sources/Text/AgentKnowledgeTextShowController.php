<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class AgentKnowledgeTextShowController
{
    public function __invoke(Request $request, Agent $agent, int $id)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $text = $agent
            ->texts()
            ->where('source_texts.id', $id)
            ->first();

        $text->content = Storage::get($text->filepath);

        return $text;
    }
}
