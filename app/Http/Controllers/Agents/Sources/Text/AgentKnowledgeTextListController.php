<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use App\Models\Agent;
use Illuminate\Http\Request;

class AgentKnowledgeTextListController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        $texts = $agent
            ->texts()
            ->select('source_texts.id', 'length', 'status', 'source_texts.created_at', 'source_texts.updated_at', 'expected_chunks', 'content')
            ->selectRaw("CONCAT(SUBSTRING(content, 1, 200), '...') as excerpt")
            ->withCount('agents')
            ->latest('source_texts.created_at')
            ->get();

        return $texts->map(function ($text) {
            $text['progress'] = $text->ingestionProgress();
            $text['word_count'] = str_word_count($text->content);

            // Remove the full content from the response to reduce payload size
            unset($text['content']);

            return $text;
        });
    }
}
