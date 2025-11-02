<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use App\Models\Agent;
use App\Models\AgentText;
use App\Services\Tidy\TextCleaner;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AgentKnowledgeTextUpdateController
{
    public function __invoke(Request $request, Agent $agent, int $id)
    {
        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 404,
        );

        /** @var AgentText $agentText */
        $agentText = $agent->texts()->where('id', $id)->firstOrFail();

        $request->validate([
            'content' => ['required', 'min:500'],
        ]);

        $content = Str::of(TextCleaner::clean($request->input('content')))->trim()->toString();

        $checksum = md5($content);

        $numberOfPages = ceil(str_word_count($content) / 450);

        if ($agent->texts()->where('checksum', $checksum)->exists()) {
            return;
        }

        abort_if(
            boolean: $request->user()->canUploadPages($numberOfPages),
            code: 422,
            message: 'This document requires '.$numberOfPages.' pages.',
        );

        $agentText->update([
            'content' => $content,
            'checksum' => $checksum,
            'length' => $numberOfPages,
            'status' => 'pending',
            'excerpt' => Str::of($content)->limit(200)->replace("\n", ' ')->trim()->toString(),
        ]);

        $agentText->chunks()->delete();

        $agentText->startTraining();
    }
}
