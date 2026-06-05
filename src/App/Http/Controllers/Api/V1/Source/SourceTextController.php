<?php

namespace App\Http\Controllers\Api\V1\Source;

use App\Http\Requests\Api\V1\Source\StoreSourceTextRequest;
use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Text\Actions\CreateAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Actions\DeleteAgentSourceTextAction;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
    public function store(StoreSourceTextRequest $request, Agent $agent, CreateAgentSourceTextAction $createText): JsonResponse
    {
        // Ingestion ownership stays with the agent's owner — the API token may
        // belong to a workspace member but the source ends up on the account.
        /** @var User $owner */
        $owner = $agent->user;

        $text = $createText->execute($agent, $owner, (string) $request->input('content'));

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
    public function destroy(Request $request, Agent $agent, int $source, DeleteAgentSourceTextAction $deleteText): JsonResponse
    {
        /** @var SourceText $text */
        $text = $agent->texts()->where('source_texts.id', $source)->firstOrFail();

        $deleteText->execute($agent, $text);

        return response()->json(null, 204);
    }
}
