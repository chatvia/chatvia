<?php

namespace App\Http\Controllers\Agents\Sources\Text;

use App\Models\Agent;
use App\Models\SourceText;
use App\Services\Tidy\TextCleaner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AgentKnowledgeTextUpdateController
{
    public function __invoke(Request $request, Agent $agent, int $id)
    {
        abort_if(
            boolean: $request->user()->cannot('update', $agent),
            code: 404,
        );

        /** @var SourceText $sourceText */
        $sourceText = $agent
            ->texts()
            ->where('source_texts.id', $id)
            ->firstOrFail();

        $request->validate([
            'content' => ['required', 'min:500'],
        ]);

        $newContent = Str::of(TextCleaner::clean($request->input('content')))->trim()->toString();

        $checksum = md5($newContent);

        $numberOfPages = ceil(str_word_count($newContent) / 450);

        if ($agent->texts()->where('checksum', $checksum)->exists()) {
            return;
        }

        abort_if(
            boolean: $request->user()->canUploadPages($numberOfPages),
            code: 422,
            message: 'This document requires '.$numberOfPages.' pages.',
        );

        Storage::delete($sourceText->filepath);

        $filepath = 'users/'.$agent->user_id.'/agents/'.$agent->id.'/train/text/'.Str::uuid()->toString().'.txt';
        Storage::put($filepath, $newContent);

        $sourceText->update([
            'filepath' => $filepath,
            'checksum' => $checksum,
            'length' => $numberOfPages,
            'status' => 'pending',
            'excerpt' => Str::of($newContent)->limit(200)->replace("\n", ' ')->trim()->toString(),
        ]);

        $sourceText->chunks()->delete();

        $sourceText->startTraining($agent);
    }
}
