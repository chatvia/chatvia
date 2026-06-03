<?php

namespace App\Http\Controllers\Widget\Stream;

use Domain\Conversation\Services\ChatStreamService;
use Domain\Conversation\Services\Streaming\Requests\StreamRequestDataFactory;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

readonly class StreamController
{
    public function __construct(
        private ChatStreamService $chatStreamService,
        private StreamRequestDataFactory $factory,
    ) {}

    public function __invoke(Request $request, WidgetVerification $verification): StreamedResponse
    {
        $sessionId = $request->header('X-SESSION-ID');
        $threadId = $request->header('X-THREAD-ID');
        $agent = current_agent_from_widget();

        // Session must belong to THIS agent — without scoping a caller can
        // submit a session id minted under agent A and stream against agent B.
        $session = $verification->verifySessionExternalId($sessionId, $agent);

        abort_if(! $session, 403, 'Invalid session id');

        // Playground status comes from the verified widget-session payload
        // (set during /auth/exchange when the bootstrap JWT carries
        // `ctx: 'playground'`). The middleware attaches it to the request;
        // anything else — including a hand-crafted Referer — is ignored.
        $isPlayground = $request->attributes->getBoolean('widget_is_playground');

        return $this->chatStreamService->stream(
            $this->factory->forWidget(
                $request,
                $agent,
                $sessionId,
                $threadId,
                $isPlayground,
            ),
        );
    }
}
