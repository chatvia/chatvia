<?php

declare(strict_types=1);

namespace App\Http\Controllers\Widget\Voice;

use App\Http\Controllers\Widget\Concerns\InteractsWithWidgetRequest;
use App\Http\Middleware\Widget\EnsureWidgetIsAccessible;
use App\Http\Requests\Chat\StartVoiceCallRequest;
use App\Http\Widget\WidgetAgent;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Voice\StartVoiceCallAction;
use Domain\Conversation\Services\Streaming\Requests\StreamRequestDataFactory;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\JsonResponse;

/**
 * Opens a voice call in the widget visitor's conversation (from the
 * conversation header) and returns the WebRTC answer for the browser. Like
 * the stream, it costs the agent's workspace, so it has its own tight rate
 * limit. A refused call is answered by the exception handler.
 */
readonly class StartVoiceCallController
{
    use InteractsWithWidgetRequest;

    public function __construct(
        private StreamRequestDataFactory $factory,
    ) {}

    public function __invoke(
        StartVoiceCallRequest $request,
        #[WidgetAgent] Agent $agent,
        WidgetVerification $verification,
        StartVoiceCallAction $start,
    ): JsonResponse {
        $sessionId = $this->widgetSessionId($request);
        $conversationId = $this->widgetConversationId($request);

        // The session must belong to THIS agent and to the signed-in viewer.
        abort_if(! $verification->verifySessionExternalId($sessionId, $agent, $this->widgetViewer($request)), JsonResponse::HTTP_FORBIDDEN, 'Invalid session id');
        abort_if($conversationId === null, JsonResponse::HTTP_BAD_REQUEST, 'Missing conversation id');

        $call = $start->execute(
            $this->factory->voiceForWidget(
                $request,
                $agent,
                $sessionId ?? '',
                $conversationId,
                $request->attributes->getBoolean(EnsureWidgetIsAccessible::PLAYGROUND_ATTRIBUTE),
                $this->widgetUser($request),
            ),
            $request->sdp(),
        );

        return response()->json($call);
    }
}
