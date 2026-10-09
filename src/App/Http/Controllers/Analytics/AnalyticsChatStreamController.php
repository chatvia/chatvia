<?php

namespace App\Http\Controllers\Analytics;

use App\Http\Requests\Analytics\AnalyticsChatStreamRequest;
use App\Http\Responses\SseResponder;
use Domain\Analytics\Services\Chat\AnalyticsChatRunner;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams one ephemeral analytics-chat turn for the workspace owner as
 * server-sent events. Nothing is persisted — no conversation, session, or
 * message rows. The owner is resolved to its workspace and handed to the
 * runner, which scopes every tool call to it.
 */
class AnalyticsChatStreamController
{
    public function __invoke(AnalyticsChatStreamRequest $request, AnalyticsChatRunner $runner, SseResponder $sse): StreamedResponse
    {
        return $sse->respond($runner->events($request->user()->workspace(), $request->toData()));
    }
}
