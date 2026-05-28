<?php

namespace App\Http\Controllers\Api\V1\Sources;

use Dedoc\Scramble\Attributes\Group;
use Domain\Agents\Models\Agent;
use Domain\Ingestion\Services\Tidy\TextCleaner;
use Domain\Sources\Text\Models\SourceText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Ingest a raw block of text as a knowledge source. After upload the text is
 * cleaned, persisted as a file, and queued for chunking + embedding. Poll
 * the source via `GET /sources` to track the `status` field through the
 * pipeline (`pending` → `processing` → `trained`).
 */
#[Group(name: 'Knowledge Sources', weight: 70)]
class SourceTextController
{
    /**
     * Add text source
     *
     * Minimum length 500 characters. Stored in object storage; the path is
     * never returned, only an opaque id.
     */
    public function store(Request $request, Agent $agent): JsonResponse
    {
        $request->validate([
            'content' => ['required', 'string', 'min:500'],
        ]);

        $content = Str::of(TextCleaner::clean($request->input('content')))->trim()->toString();
        $numberOfPages = (int) ceil(str_word_count($content) / 450);

        $filepath = 'users/'.$agent->user_id.'/agents/'.$agent->id.'/train/text/'.Str::uuid()->toString().'.txt';
        Storage::put($filepath, $content);

        /** @var SourceText $text */
        $text = SourceText::create([
            'user_id' => $agent->user_id,
            'filepath' => $filepath,
            'excerpt' => Str::of($content)->limit(200)->replace("\n", ' ')->trim()->toString(),
            'length' => $numberOfPages,
            'checksum' => md5($content),
        ]);

        $agent->texts()->attach($text->id);
        $text->startTraining($agent);

        return response()->json([
            'data' => [
                'id' => $text->id,
                'kind' => 'text',
                'length_pages' => $text->length,
                'status' => $text->status,
                'excerpt' => $text->excerpt,
            ],
        ], 201);
    }

    /**
     * Delete text source
     */
    public function destroy(Request $request, Agent $agent, int $source): JsonResponse
    {
        /** @var SourceText $text */
        $text = $agent->texts()->where('source_texts.id', $source)->firstOrFail();

        $text->agents()->detach($agent->id);
        $text->chunks()->delete();
        $text->delete();

        return response()->json(null, 204);
    }
}
