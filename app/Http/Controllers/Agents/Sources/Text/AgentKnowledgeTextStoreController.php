<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use App\Models\Agent;
use App\Support\Text;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentKnowledgeTextStoreController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 404,
        );

        $request->validate([
            'content' => ['required', 'min:500'],
        ]);

        $content = Str::of(Text::clean($request->input('content')))->trim()->toString();

        $numberOfPages = ceil(str_word_count($content) / 450);

        abort_if(
            boolean: $request->user()->canUploadPages($numberOfPages),
            code: 422,
            message: 'This document requires '.$numberOfPages.' pages.',
        );

        $agent->texts()->create([
            'user_id' => $request->user()->id,
            'content' => $content,
            'excerpt' => Str::of($content)->limit(200)->replace("\n", ' ')->trim()->toString(),
            'length' => $numberOfPages, // number of pages, 450 word = 1 page
            'md5_hash' => md5($content),
        ]);
    }
}
