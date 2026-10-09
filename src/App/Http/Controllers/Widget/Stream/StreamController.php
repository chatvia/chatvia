<?php

namespace App\Http\Controllers\Widget\Stream;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Middleware\Widget\EnsureWidgetIsAccessible;
use App\Http\Responses\SseResponder;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Streaming\Requests\StreamRequestDataFactory;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams the agent's reply to a widget message (server-sent events). This is
 * the endpoint that costs LLM tokens, so it has its own, tighter rate limit.
 */
readonly class StreamController
{
    use InteractsWithWidgetRequest;

    public function __construct(
        private ChatStreamService $chatStreamService,
        private StreamRequestDataFactory $factory,
    ) {}

    public function __invoke(Request $request, WidgetVerification $verification, SseResponder $sse): StreamedResponse
    {
        $agent = $this->widgetAgent();
        $sessionId = $this->widgetSessionId($request);
        $conversationId = $this->widgetConversationId($request);

        // The session must belong to THIS agent, or a session started under
        // agent A could stream against agent B.
        abort_if(! $verification->verifySessionExternalId($sessionId, $agent), JsonResponse::HTTP_FORBIDDEN, 'Invalid session id');
        abort_if($conversationId === null, JsonResponse::HTTP_BAD_REQUEST, 'Missing conversation id');

        // Playground status comes only from the verified widget session (set
        // during the auth exchange from the bootstrap token's `ctx` claim);
        // a hand-made Referer or header cannot switch it on.
        $isPlayground = $request->attributes->getBoolean(EnsureWidgetIsAccessible::PLAYGROUND_ATTRIBUTE);

        return $sse->respond($this->chatStreamService->stream(
            $this->factory->forWidget(
                $request,
                $agent,
                (string) $sessionId,
                $conversationId,
                $isPlayground,
                // The signed-in chatter on a protected agent (null for an
                // anonymous visitor): drives the per-chatter message limit and
                // the analytics chatter id.
                $this->widgetUser($request),
            ),
        ));
    }
}
