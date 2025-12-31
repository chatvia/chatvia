<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use App\Models\Agent;
use App\Models\SourceText;
use App\Services\Tidy\TextCleaner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AgentKnowledgeTextStoreController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 404,
        );

        abort_if(
            boolean: ! has_permission_to_agent($agent, 'agent.sources.train.text'),
            code: 403,
        );

        $request->validate([
            'content' => ['required', 'min:500'],
        ]);

        $content = Str::of(TextCleaner::clean($request->input('content')))->trim()->toString();

        $numberOfPages = ceil(str_word_count($content) / 450);

        abort_if(
            boolean: $request->user()->canUploadPages($numberOfPages),
            code: 422,
            message: 'This document requires '.$numberOfPages.' pages.',
        );

        $filepath = 'users/'.$agent->user_id.'/agents/'.$agent->id.'/train/text/'.Str::uuid()->toString().'.txt';

        Storage::put($filepath, $content);

        /** @var SourceText $agentText */
        $agentText = SourceText::create([
            'user_id' => $request->user()->id,
            'filepath' => $filepath,
            'excerpt' => Str::of($content)->limit(200)->replace("\n", ' ')->trim()->toString(),
            'length' => $numberOfPages, // number of pages, 450 word = 1 page
            'checksum' => md5($content),
        ]);

        // Attach the text to this agent
        $agent->texts()->attach($agentText->id);

        $agentText->startTraining($agent);

    }
}
