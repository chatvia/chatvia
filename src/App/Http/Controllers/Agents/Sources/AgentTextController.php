<?php

namespace App\Http\Controllers\Agents\Sources;

use Domain\Agents\Data\Resources\AgentResourceData;
use Domain\Agents\Models\Agent;
use Domain\Ingestion\Services\Tidy\TextCleaner;
use Domain\Sources\Text\Models\SourceText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AgentTextController
{
    public function index(Request $request, Agent $agent): Response|Collection
    {
        abort_if($request->user()->cannot('view', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.sources.train.text'), 403);

        if ($request->wantsJson()) {
            return $this->buildTextList($agent);
        }

        return Inertia::render('agents/Sources/Text', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }

    public function show(Request $request, Agent $agent, int $id): SourceText
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        /** @var SourceText $text */
        $text = $agent
            ->texts()
            ->where('source_texts.id', $id)
            ->firstOrFail();

        $text->content = Storage::get($text->filepath);

        return $text;
    }

    public function store(Request $request, Agent $agent): void
    {
        abort_if($request->user()->cannot('update', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.sources.train.text'), 403);

        $request->validate([
            'content' => ['required', 'min:500'],
        ]);

        $content = Str::of(TextCleaner::clean($request->input('content')))->trim()->toString();

        $numberOfPages = (int) ceil(str_word_count($content) / 450);

        abort_if(
            $request->user()->canUploadPages($numberOfPages),
            422,
            'This document requires '.$numberOfPages.' pages.',
        );

        $filepath = 'users/'.$agent->user_id.'/agents/'.$agent->id.'/train/text/'.Str::uuid()->toString().'.txt';

        Storage::put($filepath, $content);

        /** @var SourceText $agentText */
        $agentText = SourceText::create([
            'user_id' => $request->user()->id,
            'filepath' => $filepath,
            'excerpt' => Str::of($content)->limit(200)->replace("\n", ' ')->trim()->toString(),
            'length' => $numberOfPages, // 450 words = 1 page
            'checksum' => md5($content),
        ]);

        $agent->texts()->attach($agentText->id);

        $agentText->startTraining($agent);
    }

    public function update(Request $request, Agent $agent, int $id): void
    {
        abort_if($request->user()->cannot('update', $agent), 404);

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

        $numberOfPages = (int) ceil(str_word_count($newContent) / 450);

        if ($agent->texts()->where('checksum', $checksum)->exists()) {
            return;
        }

        abort_if(
            $request->user()->canUploadPages($numberOfPages),
            422,
            'This document requires '.$numberOfPages.' pages.',
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

    public function destroy(Request $request, Agent $agent, int $id): void
    {
        abort_if($request->user()->cannot('delete', $agent), 404);
        abort_if(! has_permission_to_agent($agent, 'agent.sources.delete'), 403);

        /** @var SourceText $text */
        $text = $agent->texts()->where('source_texts.id', $id)->firstOrFail();

        abort_if($request->user()->cannot('delete', $text), 403);

        $text->agents()->detach($agent->id);
        $text->delete();
        $text->chunks()->delete();
    }

    private function buildTextList(Agent $agent): Collection
    {
        $texts = $agent
            ->texts()
            ->select('source_texts.id', 'source_texts.excerpt', 'length', 'status', 'source_texts.created_at', 'source_texts.updated_at', 'expected_chunks')
            ->withCount('agents')
            ->latest('source_texts.created_at')
            ->get();

        return $texts->map(function ($text) {
            $text['progress'] = $text->ingestionProgress();
            $text['word_count'] = str_word_count($text->content);

            return $text;
        });
    }

    public function unlink(Request $request, Agent $agent, int $id): JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        /** @var SourceText $text */
        $text = $agent->texts()->where('source_texts.id', $id)->firstOrFail();

        abort_if($request->user()->cannot('delete', $text), 403);

        $text->agents()->detach($agent->id);

        return response()->json(['message' => 'Text unlinked from agent successfully.']);
    }
}
