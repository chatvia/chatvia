<?php

namespace App\Http\Controllers\Api\V1\Source;

use App\Http\Requests\Api\V1\Source\StoreSourceTextRequest;
use App\Http\Resources\Api\V1\Source\SourceData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Text\Actions\CreateAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Actions\DeleteAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Http\JsonResponse;

/**
 * Ingest a raw block of text as a knowledge source. After upload the text is
 * cleaned, persisted as a file, and queued for chunking + embedding. Poll
 * the source via `GET /sources` to track the `status` field through the
 * pipeline (`pending` → `ingesting` → `ingested`, or `failed`).
 */
#[Group(name: 'Sources', weight: 70)]
class SourceTextController
{
    /**
     * Add text source
     *
     * Minimum length 500 characters. Stored in object storage; the path is
     * never returned, only an opaque id.
     */
    public function store(StoreSourceTextRequest $request, Agent $agent, CreateAgentSourceTextAction $createText): JsonResponse
    {
        // The source is keyed to the agent's owner (the workspace), whoever
        // holds the API token.
        $text = $createText->execute($agent, $request->textContent());

        return response()->json(['data' => SourceData::fromModel($text)], 201);
    }

    /**
     * Delete text source
     */
    public function destroy(Agent $agent, SourceText $text, DeleteAgentSourceTextAction $deleteText): JsonResponse
    {
        // {text} is looked up through the agent's own texts, so a text of
        // another agent is a 404.
        $deleteText->execute($agent, $text);

        return response()->json(null, 204);
    }
}
