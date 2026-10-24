<?php

namespace App\Http\Controllers\Widget\Stream;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Middleware\Widget\EnsureWidgetIsAccessible;
use App\Http\Requests\Widget\WidgetStreamRequest;
use App\Http\Responses\SseResponder;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Streaming\Requests\StreamRequestDataFactory;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;
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

    public function __invoke(
        WidgetStreamRequest $request,
        #[WidgetAgent] Agent $agent,
        WidgetVerification $verification,
        SseResponder $sse,
    ): StreamedResponse {
        $sessionId = $this->widgetSessionId($request);
        $conversationId = $this->widgetConversationId($request);

        // The session must belong to THIS agent, or a session started under
        // agent A could stream against agent B, and to the signed-in viewer,
        // or another visitor could send messages as that chatter by replaying
        // their session id.
        abort_if(! $verification->verifySessionExternalId($sessionId, $agent, $this->widgetViewer($request)), JsonResponse::HTTP_FORBIDDEN, 'Invalid session id');
        abort_if($conversationId === null, JsonResponse::HTTP_BAD_REQUEST, 'Missing conversation id');

        // Playground status comes only from the verified widget session (set
        // during the auth exchange from the bootstrap token's `ctx` claim);
        // a hand-made Referer or header cannot switch it on.
        $isPlayground = $request->attributes->getBoolean(EnsureWidgetIsAccessible::PLAYGROUND_ATTRIBUTE);

        return $sse->respond($this->chatStreamService->stream(
            $this->factory->forWidget(
                $request,
                $agent,
                $sessionId ?? '',
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
